# clinic
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/Rommelpalermo/clinic.git
git push -u origin main

## MySQL setup

Start Apache and MySQL in XAMPP. Import `database.sql` into MySQL with phpMyAdmin, or run this from Command Prompt:

```bat
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\clinic\database.sql
```

The PHP API in `api.php` reads and writes the `clinic` database through PDO. `db.php` defaults to XAMPP's local `root` account with a blank password. Override the connection with `CLINIC_DB_HOST`, `CLINIC_DB_NAME`, `CLINIC_DB_USER`, and `CLINIC_DB_PASSWORD` environment variables when needed.

## Admin account

Open `http://localhost/clinic/clinic/login.html` to create the first admin account. Choose a username and a password between 12 and 72 characters; the password is stored as a hash. Once the first admin exists, the page becomes the sign-in form. Clinic API data and changes require an authenticated admin session.

When recording a visit, add medication requests from inventory items in the `Medication` category. The requested quantities are deducted atomically with the visit; requests exceeding available stock are rejected without recording the visit.

## Patient email notifications

Staff can send follow-up checkup reminders from the dashboard or send a custom patient update from the Patients list. Patient email addresses must be saved on the patient record. Email delivery uses PHP's `mail()` function, so set a valid `CLINIC_MAIL_FROM` environment variable and configure an SMTP relay for PHP before sending. On XAMPP, set the SMTP server and sender in `C:\xampp\php\php.ini` under `[mail function]`, then restart Apache. PHP's `mail()` confirms that the server accepted a message; it cannot confirm inbox delivery.
