UTP VCR - SYSTEM DOCUMENTATION

1. INTRODUCTION

The UTP VCR is a specialized web application built to digitize the vendor code
requisition process. It facilitates secure document submission by vendors (Students,
External Appointments) and multi-level verification by UTP Administrators.

2. SYSTEM REQUIREMENTS

Server OS: Linux (Ubuntu/CentOS) or Windows Server
Web Server: Apache 2.4+
PHP Version: 8.0 or higher
Database: MySQL 5.7+ or MariaDB 10+

Extensions Required:
php-mysqli
php-zip
php-curl

3. INSTALLATION INSTRUCTIONS

--------------------------------------------------------------------
QUICK START WITH DOCKER (recommended — no local dependencies needed)
--------------------------------------------------------------------

With Docker Desktop installed, the whole app (PHP, Apache, Composer
packages, Tesseract OCR) plus a pre-seeded MySQL database run with one
command. Nothing else needs to be installed on your machine.

  1. Install Docker Desktop:  https://www.docker.com/products/docker-desktop/
  2. Copy the example environment file:

         cp .env.example .env

     (On Windows PowerShell:  Copy-Item .env.example .env )

  3. Build and start everything:

         docker compose up --build

  4. Open the site:  http://localhost:8080

The bundled MySQL container is automatically seeded from
database/utp_vendor_hub.sql on first run, so the app works immediately.

Notes:
  - Email (SMTP) and AWS S3 upload features only work if you fill in the
    matching keys in .env. The core app runs fine without them.
  - To stop:            docker compose down
  - To wipe the DB and re-seed:   docker compose down -v   then up again.
  - MySQL is also reachable from the host on port 3307
    (user: utp_user / password: utp_password / db: utp_vendor_hub).

--------------------------------------------------------------------
MANUAL INSTALLATION (without Docker)
--------------------------------------------------------------------

A. Database

Create a database named 'UTP_Vendor_Hub'.
Import the schema file 'UTP_Vendor_Hub.sql'.

B. Application Files

Copy the source code to the web root (e.g., /var/www/html/).
Configure 'config.php' with your database credentials.
Configure the '.env' file with SMTP email credentials for notifications.

C. Permissions

Ensure the following folders are writable by the web server:

/generated_zips/
/kyc6/
/bank_statement/
/declaration_form/
/fyp_form_file/
/appointment_file/
/ic_picture/
/face_captures/

4. USER ROLES

Student: Can register, upload FYP docs, track status.

External Appt: External applicants outside of organization who wants to use the system.

KYC6 Admin: Verifies Identity (IC).

AE Admin: Internal UTP staff members who serve as the point of reference for external applicants

Master Admin: Final approval, Requisition of Vendor Code, ZIP Downloads, Document Verification.

5. TROUBLESHOOTING

Issue: "ZipArchive not found"
Fix: Enable the 'php_zip' extension in php.ini.

Issue: "Email could not be sent"
Fix: Check firewall settings or SMTP credentials in .env. Note that UTP network
may block external SMTP ports (587/465).

6. SECURITY NOTES

This system uses Native PHP with Prepared Statements to prevent SQL Injection.
Passwords are hashed using Bcrypt. 

=======================================

Developed for every UTP stakeholder usage

Date: December 2025
