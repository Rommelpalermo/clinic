<?php
declare(strict_types=1);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

function sendJson(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function readText(array $input, string $key, string $default = ''): string
{
    return trim((string)($input[$key] ?? $default));
}

function optionalNumber(array $input, string $key): int|float|null
{
    $value = $input[$key] ?? '';
    if ($value === '' || $value === null) {
        return null;
    }
    if (!is_numeric($value) || (float)$value < 0) {
        sendJson(['error' => "Invalid value for {$key}."], 422);
    }
    return str_contains((string)$value, '.') ? (float)$value : (int)$value;
}

try {
    $pdo = require __DIR__ . '/db.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'auth-status') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        sendJson([
            'setupRequired' => $adminCount === 0,
            'authenticated' => isset($_SESSION['admin']),
            'username' => $_SESSION['admin']['username'] ?? null,
        ]);
    }

    if ($method === 'GET') {
        if (!isset($_SESSION['admin'])) {
            sendJson(['error' => 'Authentication required.'], 401);
        }

        $patients = $pdo->query(
                'SELECT id, name, first_name AS firstName, last_name AS lastName,
                    date_of_birth AS dateOfBirth, gender, contact_number AS contactNumber,
                    email, course, year_level AS year, blood_type AS blood,
                    emergency_contact AS emergencyContact,
                    emergency_contact_number AS emergencyContactNumber,
                    allergies, existing_conditions AS existingConditions, status
             FROM patients ORDER BY name'
        )->fetchAll();

        $inventoryRows = $pdo->query(
            'SELECT id, name, supplier, category, quantity, unit, minimum_stock,
                    unit_cost, expiry_date, location, description
             FROM inventory ORDER BY id'
        )->fetchAll();
        $inventory = array_map(static function (array $row): array {
            $expiryDate = $row['expiry_date'];
            $expiryTime = $expiryDate ? strtotime($expiryDate) : false;
            $warn = $expiryTime !== false
                && $expiryTime >= strtotime('today')
                && $expiryTime <= strtotime('+90 days');

            return [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'supplier' => $row['supplier'],
                'category' => $row['category'],
                'qty' => (int)$row['quantity'],
                'unit' => $row['unit'],
                'min' => (int)$row['minimum_stock'],
                'unitCost' => (float)$row['unit_cost'],
                'expiry' => $expiryTime === false ? '—' : date('M j, Y', $expiryTime),
                'expiryDate' => $expiryDate,
                'location' => $row['location'],
                'description' => $row['description'],
                'warn' => $warn,
                'status' => (int)$row['quantity'] <= (int)$row['minimum_stock'] ? 'lowstock' : 'instock',
            ];
        }, $inventoryRows);

        $visitRows = $pdo->query(
            'SELECT v.id, v.patient_id, p.name, v.complaint, v.severity, v.visit_date,
                    v.diagnosis, v.status, v.attending_staff, v.temperature,
                    v.blood_pressure, v.pulse, v.respiratory_rate, v.weight,
                    v.treatment, v.medications, v.notes
             FROM visits v JOIN patients p ON p.id = v.patient_id
             ORDER BY v.visit_date DESC, v.id DESC'
        )->fetchAll();
        $visits = array_map(static function (array $row): array {
            $visitTime = strtotime($row['visit_date']);
            return [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'patientId' => $row['patient_id'],
                'reason' => $row['complaint'],
                'severity' => $row['severity'],
                'date' => date('M j', $visitTime),
                'fullDate' => date('M j, Y', $visitTime),
                'dateKey' => date('Y-m-d', $visitTime),
                'dx' => $row['diagnosis'],
                'status' => $row['status'],
                'attendingStaff' => $row['attending_staff'],
                'vitals' => [
                    'temperature' => $row['temperature'],
                    'bloodPressure' => $row['blood_pressure'],
                    'pulse' => $row['pulse'],
                    'respiratoryRate' => $row['respiratory_rate'],
                    'weight' => $row['weight'],
                ],
                'treatment' => $row['treatment'],
                'medications' => $row['medications'],
                'notes' => $row['notes'],
            ];
        }, $visitRows);

        $inventoryValue = (float)$pdo->query(
            'SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM inventory'
        )->fetchColumn();

        sendJson([
            'patients' => $patients,
            'inventory' => $inventory,
            'visits' => $visits,
            'inventoryValue' => $inventoryValue,
        ]);
    }

    if ($method !== 'POST') {
        sendJson(['error' => 'Method not allowed.'], 405);
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJson(['error' => 'Request body must be valid JSON.'], 400);
    }
    if (!is_array($input)) {
        sendJson(['error' => 'Request body must be a JSON object.'], 400);
    }

    $type = readText($input, 'type');
    if ($type === 'auth') {
        $action = readText($input, 'action');

        if ($action === 'setup') {
            $username = strtolower(readText($input, 'username'));
            $password = (string)($input['password'] ?? '');
            if (!preg_match('/^[a-z0-9._@-]{3,80}$/', $username)
                || strlen($password) < 12 || strlen($password) > 72) {
                sendJson(['error' => 'Use a valid username and a password between 12 and 72 characters.'], 422);
            }

            $lockAcquired = (int)$pdo->query("SELECT GET_LOCK('clinic_admin_setup', 5)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                sendJson(['error' => 'Admin setup is busy. Please try again.'], 503);
            }

            $setupError = null;
            if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0) {
                $setupError = 'An admin account already exists. Sign in instead.';
            } else {
                $statement = $pdo->prepare(
                    "INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, 'admin')"
                );
                $statement->execute([
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
            }
            $pdo->query("SELECT RELEASE_LOCK('clinic_admin_setup')");

            if ($setupError !== null) {
                sendJson(['error' => $setupError], 409);
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = ['username' => $username];
            sendJson(['authenticated' => true, 'username' => $username], 201);
        }

        if ($action === 'login') {
            $username = strtolower(readText($input, 'username'));
            $password = (string)($input['password'] ?? '');
            $statement = $pdo->prepare(
                "SELECT username, password_hash FROM users WHERE username = :username AND role = 'admin' LIMIT 1"
            );
            $statement->execute(['username' => $username]);
            $admin = $statement->fetch();
            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                sendJson(['error' => 'Incorrect username or password.'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = ['username' => $admin['username']];
            sendJson(['authenticated' => true, 'username' => $admin['username']]);
        }

        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => '/',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            }
            session_destroy();
            sendJson(['authenticated' => false]);
        }

        sendJson(['error' => 'Unknown authentication action.'], 422);
    }

    if (!isset($_SESSION['admin'])) {
        sendJson(['error' => 'Authentication required.'], 401);
    }

    if ($type === 'email_notification') {
        $patientId = readText($input, 'patientId');
        $subject = readText($input, 'subject');
        $message = readText($input, 'message');
        if ($patientId === '' || $subject === '' || $message === '') {
            sendJson(['error' => 'Patient, subject, and message are required.'], 422);
        }
        if (strlen($subject) > 180 || preg_match('/[\r\n]/', $subject) || strlen($message) > 5000) {
            sendJson(['error' => 'The subject or message is too long or invalid.'], 422);
        }

        $statement = $pdo->prepare('SELECT name, email FROM patients WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $patientId]);
        $patient = $statement->fetch();
        if (!$patient) {
            sendJson(['error' => 'Patient not found.'], 404);
        }
        if (!filter_var($patient['email'], FILTER_VALIDATE_EMAIL)) {
            sendJson(['error' => 'This patient does not have a valid email address on file.'], 422);
        }

        $sender = getenv('CLINIC_MAIL_FROM') ?: '';
        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            sendJson(['error' => 'Email sending is not configured. Set CLINIC_MAIL_FROM and configure PHP mail on the server.'], 503);
        }
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = "From: {$sender}\r\nContent-Type: text/plain; charset=UTF-8";
        if (!@mail($patient['email'], $encodedSubject, $message, $headers)) {
            sendJson(['error' => 'The email could not be sent. Check the PHP mail server configuration.'], 503);
        }
        sendJson(['sent' => true, 'patient' => $patient['name']]);
    }

    if ($type === 'patient') {
        $action = readText($input, 'action', 'create');
        $id = readText($input, 'id');
        $firstName = readText($input, 'firstName');
        $lastName = readText($input, 'lastName');
        $name = readText($input, 'name', trim("{$firstName} {$lastName}"));
        $course = readText($input, 'course');
        $year = readText($input, 'year');
        $blood = readText($input, 'blood', 'Unknown');
        if ($id === '' || $name === '' || $course === '' || $year === '') {
            sendJson(['error' => 'Patient ID, name, course, and year are required.'], 422);
        }
        $dateOfBirth = readText($input, 'dateOfBirth') ?: null;
        $gender = readText($input, 'gender');
        $contactNumber = readText($input, 'contactNumber');
        $email = readText($input, 'email');
        $emergencyContact = readText($input, 'emergencyContact');
        $emergencyContactNumber = readText($input, 'emergencyContactNumber');
        $allergies = readText($input, 'allergies');
        $existingConditions = readText($input, 'existingConditions');
        $patientData = compact(
            'id', 'name', 'firstName', 'lastName', 'dateOfBirth', 'gender',
            'contactNumber', 'email', 'course', 'year', 'blood',
            'emergencyContact', 'emergencyContactNumber', 'allergies', 'existingConditions'
        );
        if ($action === 'update') {
            $statement = $pdo->prepare(
                'UPDATE patients
                 SET name = :name, first_name = :firstName, last_name = :lastName,
                     date_of_birth = :dateOfBirth, gender = :gender,
                     contact_number = :contactNumber, email = :email,
                     course = :course, year_level = :year, blood_type = :blood,
                     emergency_contact = :emergencyContact,
                     emergency_contact_number = :emergencyContactNumber,
                     allergies = :allergies, existing_conditions = :existingConditions
                 WHERE id = :id'
            );
            $statement->execute($patientData);
            sendJson(['id' => $id]);
        }
        if ($action !== 'create') {
            sendJson(['error' => 'Unknown patient action.'], 422);
        }
        $statement = $pdo->prepare(
            'INSERT INTO patients
             (id, name, first_name, last_name, date_of_birth, gender, contact_number, email,
              course, year_level, blood_type, emergency_contact, emergency_contact_number,
              allergies, existing_conditions)
             VALUES (:id, :name, :firstName, :lastName, :dateOfBirth, :gender, :contactNumber,
                     :email, :course, :year, :blood, :emergencyContact,
                     :emergencyContactNumber, :allergies, :existingConditions)'
        );
        $statement->execute($patientData);
        sendJson(['id' => $id], 201);
    }

    if ($type === 'inventory') {
        $action = readText($input, 'action', 'create');
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = readText($input, 'name');
        $supplier = readText($input, 'supplier');
        $category = readText($input, 'category');
        $unit = readText($input, 'unit', 'pieces');
        $allowedCategories = ['Consumable', 'Equipment', 'Ppe', 'Medication', 'First Aid'];
        $allowedUnits = ['pieces', 'boxes', 'bottles', 'packs', 'rolls'];
        $quantity = optionalNumber($input, 'qty') ?? 0;
        $minimumStock = optionalNumber($input, 'min') ?? 0;
        $unitCost = optionalNumber($input, 'unitCost') ?? 0;
        $expiryDate = readText($input, 'expiryDate') ?: null;
        if ($name === '' || !in_array($category, $allowedCategories, true)
            || !in_array($unit, $allowedUnits, true)) {
            sendJson(['error' => 'Provide an item name, valid category, and valid unit.'], 422);
        }
        $itemData = [
            'name' => $name,
            'supplier' => readText($input, 'supplier'),
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'minimum_stock' => $minimumStock,
            'unit_cost' => $unitCost,
            'expiry_date' => $expiryDate,
            'location' => readText($input, 'location'),
            'description' => readText($input, 'description'),
        ];
        if ($action === 'update') {
            if ($id === false) {
                sendJson(['error' => 'A valid inventory item ID is required.'], 422);
            }
            $statement = $pdo->prepare(
                'UPDATE inventory
                 SET name = :name, supplier = :supplier, category = :category,
                     quantity = :quantity, unit = :unit, minimum_stock = :minimum_stock,
                     unit_cost = :unit_cost, expiry_date = :expiry_date,
                     location = :location, description = :description
                 WHERE id = :id'
            );
            $statement->execute($itemData + ['id' => $id]);
            sendJson(['id' => $id]);
        }
        if ($action !== 'create') {
            sendJson(['error' => 'Unknown inventory action.'], 422);
        }
        $statement = $pdo->prepare(
            'INSERT INTO inventory
             (name, supplier, category, quantity, unit, minimum_stock, unit_cost, expiry_date, location, description)
             VALUES (:name, :supplier, :category, :quantity, :unit, :minimum_stock,
                     :unit_cost, :expiry_date, :location, :description)'
        );
        $statement->execute($itemData);
        sendJson(['id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($type === 'visit') {
        $patientId = readText($input, 'patientId');
        $complaint = readText($input, 'reason');
        $severity = readText($input, 'severity', 'mild');
        $status = readText($input, 'status', 'inprogress');
        if ($patientId === '' || $complaint === '') {
            sendJson(['error' => 'Patient and chief complaint are required.'], 422);
        }
        if (!in_array($severity, ['mild', 'moderate', 'severe'], true)
            || !in_array($status, ['inprogress', 'completed', 'followup'], true)) {
            sendJson(['error' => 'Invalid severity or visit status.'], 422);
        }
        $requestedItems = $input['medicationRequests'] ?? [];
        if (!is_array($requestedItems)) {
            sendJson(['error' => 'Medication requests must be a list.'], 422);
        }
        $medicationTotals = [];
        foreach ($requestedItems as $requestedItem) {
            if (!is_array($requestedItem)) {
                sendJson(['error' => 'Each medication request must include an item and quantity.'], 422);
            }
            $itemId = filter_var($requestedItem['itemId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($requestedItem['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($itemId === false || $quantity === false) {
                sendJson(['error' => 'Medication item and quantity must be valid positive whole numbers.'], 422);
            }
            $medicationTotals[$itemId] = ($medicationTotals[$itemId] ?? 0) + $quantity;
        }
        $vitals = is_array($input['vitals'] ?? null) ? $input['vitals'] : [];
        $medicationSummary = [];
        $pdo->beginTransaction();
        try {
            $lockItem = $pdo->prepare(
                'SELECT id, name, category, quantity FROM inventory WHERE id = :id FOR UPDATE'
            );
            $deductStock = $pdo->prepare(
                'UPDATE inventory SET quantity = quantity - :quantity
                 WHERE id = :id AND quantity >= :quantity'
            );
            $stockError = null;
            foreach ($medicationTotals as $itemId => $quantity) {
                $lockItem->execute(['id' => $itemId]);
                $item = $lockItem->fetch();
                if (!$item || $item['category'] !== 'Medication') {
                    $stockError = 'A requested medication is not available in the medication inventory.';
                    break;
                }
                if ((int)$item['quantity'] < $quantity) {
                    $stockError = "Not enough stock for {$item['name']}. Available: {$item['quantity']}.";
                    break;
                }
                $deductStock->execute(['quantity' => $quantity, 'id' => $itemId]);
                if ($deductStock->rowCount() !== 1) {
                    $stockError = "Could not reserve stock for {$item['name']}. Please try again.";
                    break;
                }
                $medicationSummary[] = "{$item['name']} x{$quantity}";
            }
            if ($stockError !== null) {
                $pdo->rollBack();
                sendJson(['error' => $stockError], 422);
            }

            $statement = $pdo->prepare(
                'INSERT INTO visits
                 (patient_id, complaint, severity, diagnosis, status, attending_staff, temperature,
                  blood_pressure, pulse, respiratory_rate, weight, treatment, medications, notes)
                 VALUES (:patient_id, :complaint, :severity, :diagnosis, :status, :attending_staff,
                         :temperature, :blood_pressure, :pulse, :respiratory_rate, :weight,
                         :treatment, :medications, :notes)'
            );
            $statement->execute([
                'patient_id' => $patientId,
                'complaint' => $complaint,
                'severity' => $severity,
                'diagnosis' => readText($input, 'dx', 'Not recorded') ?: 'Not recorded',
                'status' => $status,
                'attending_staff' => readText($input, 'attendingStaff'),
                'temperature' => optionalNumber($vitals, 'temperature'),
                'blood_pressure' => readText($vitals, 'bloodPressure'),
                'pulse' => optionalNumber($vitals, 'pulse'),
                'respiratory_rate' => optionalNumber($vitals, 'respiratoryRate'),
                'weight' => optionalNumber($vitals, 'weight'),
                'treatment' => readText($input, 'treatment'),
                'medications' => implode(', ', $medicationSummary),
                'notes' => readText($input, 'notes'),
            ]);
            $visitId = (int)$pdo->lastInsertId();
            $pdo->commit();
            sendJson(['id' => $visitId], 201);
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    sendJson(['error' => 'Unknown record type.'], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    sendJson(['error' => 'The database request could not be completed.'], 500);
}