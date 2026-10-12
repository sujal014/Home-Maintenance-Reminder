# Home Maintenance Reminder

Requirements: PHP 8.0+ with the pdo_sqlite extension.

Run:

    cd home-maintenance-reminder
    php -S localhost:8000

Then open http://localhost:8000 - you will be sent to the login page.
Click "Create an account" the first time.

Or place the folder in XAMPP/MAMP's htdocs and visit
http://localhost/home-maintenance-reminder/

Accounts
- Register with name, email and password; log in with email and password.
- One account per email address (case-insensitive). Registering an email that
  already exists is rejected with a message pointing to the login page.
- Each user sees only their own tasks.

The SQLite database (data/maintenance.db) is created automatically on first use.
Make sure the data/ folder is writable by PHP.

Upgrading from the first version: tasks created before accounts existed have no
owner and will not be shown. Delete data/maintenance.db to start fresh.
