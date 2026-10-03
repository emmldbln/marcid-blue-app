# Marcid Blue

A private water-station management and daily accounting web application built with PHP, MySQL, JavaScript, and CSS.

Marcid Blue is designed around the day-to-day workflow of a small water-refilling station, including customer records, deliveries, payments, debts, expenses, payroll, driver remittances, and daily closing.

## Features

- Admin authentication and logout
- Customer management
- Daily sales and walk-in records
- Delivery records with customer profiles
- Delivery payments and debt tracking
- Partial and full debt payments
- Expense recording
- Employee payroll and cash advances
- Driver remittance tracking
- Daily closing and cash reconciliation
- Daily records and historical views
- Responsive business-oriented interface

## Tech Stack

- PHP 8.x
- MySQL / MariaDB
- JavaScript
- HTML5
- CSS3
- XAMPP for local development
- InfinityFree-compatible PHP/MySQL hosting

## Project Structure

```text
marcid-blue/
├── assets/              # CSS, JavaScript, images
├── auth/                # Authentication and logout
├── config/              # Environment-specific database configuration
├── includes/            # Shared application components
├── pages/               # Application pages and backends
├── tools/               # Utility scripts
├── index.php            # Application entry point
└── .gitignore
```

## Local Development

The project can be run with XAMPP.

1. Install XAMPP with Apache, PHP, and MySQL.
2. Place the project in:

   `C:\xampp\htdocs\marcid-blue`

3. Create a MySQL database named `marcid_blue`.
4. Import the application's database schema/data as needed.
5. Create `config/local.php` using your local database credentials.

Example:

```php
<?php

return [
    'db_host' => 'localhost',
    'db_name' => 'marcid_blue',
    'db_user' => 'root',
    'db_pass' => 'YOUR_LOCAL_DATABASE_PASSWORD',
];
```

6. Start Apache and MySQL in XAMPP.
7. Open:

   `http://localhost/marcid-blue/`

## Configuration and Security

Database credentials are intentionally **not included in this repository**.

The following files are ignored by Git:

- `config/local.php`
- `config/production.php`
- `.env` files

Create your own environment-specific configuration locally or on your hosting provider.

**Never commit database passwords, API keys, session secrets, or other credentials to GitHub.**

For production hosting, configure the database connection using the hosting provider's MySQL hostname, database name, username, and password.

## Production Notes

The application has been deployed to PHP/MySQL hosting and uses a separate production database configuration.

Before deploying:

1. Upload the application files.
2. Create/import the production MySQL database.
3. Create `config/production.php` on the server.
4. Keep production credentials outside Git.
5. Verify that the application's domain and document root point to the directory containing `index.php`.
6. Test authentication, daily records, closing, payments, payroll, and logout after deployment.

## Database

The application expects a MySQL-compatible database. The database contains tables supporting:

- Users
- Customers
- Daily records
- Daily sales
- Deliveries
- Payments
- Expenses
- Payroll and related accounting records

Database credentials and live business data are not part of this repository.

## Important Deployment Detail

The application uses different URL roots in local and hosted environments. The logout handler detects the local XAMPP path and the production domain root so that logout works in both environments.

## Status

This is a personal portfolio/project application and is actively developed. Business-specific workflows may be customized as the water station's requirements evolve.

## License

No open-source license has been added yet. Unless a license is explicitly added, the repository should be treated as **all rights reserved**.
