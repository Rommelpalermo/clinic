<?php
declare(strict_types=1);

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

    if ($method === 'GET') {
        $patients = $pdo->query(
            'SELECT id, name, course, year_level AS year, blood_type AS blood, status
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
    if ($type === 'patient') {
        $id = readText($input, 'id');
        $name = readText($input, 'name');
        $course = readText($input, 'course');
        $year = readText($input, 'year');
        $blood = readText($input, 'blood', 'Unknown');
        if ($id === '' || $name === '' || $course === '' || $year === '') {
            sendJson(['error' => 'Patient ID, name, course, and year are required.'], 422);
        }
        $statement = $pdo->prepare(
            'INSERT INTO patients (id, name, course, year_level, blood_type)
             VALUES (:id, :name, :course, :year, :blood)'
        );
        $statement->execute(compact('id', 'name', 'course', 'year', 'blood'));
        sendJson(['id' => $id], 201);
    }

    if ($type === 'inventory') {
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
        $statement = $pdo->prepare(
            'INSERT INTO inventory
             (name, supplier, category, quantity, unit, minimum_stock, unit_cost, expiry_date, location, description)
             VALUES (:name, :supplier, :category, :quantity, :unit, :minimum_stock,
                     :unit_cost, :expiry_date, :location, :description)'
        );
        $statement->execute([
            'name' => $name,
            'supplier' => $supplier,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $unit,
            'minimum_stock' => $minimumStock,
            'unit_cost' => $unitCost,
            'expiry_date' => $expiryDate,
            'location' => readText($input, 'location'),
            'description' => readText($input, 'description'),
        ]);
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
        $vitals = is_array($input['vitals'] ?? null) ? $input['vitals'] : [];
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
            'medications' => readText($input, 'medications'),
            'notes' => readText($input, 'notes'),
        ]);
        sendJson(['id' => (int)$pdo->lastInsertId()], 201);
    }

    sendJson(['error' => 'Unknown record type.'], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    sendJson(['error' => 'The database request could not be completed.'], 500);
}