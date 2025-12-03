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

External Appt: Can register (with referral), upload appointment docs.

KYC6 Admin: Verifies Identity (IC).

AE Admin: Verifies Appointment Letters for their referrals.

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
