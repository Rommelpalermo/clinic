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
