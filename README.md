# RestroGrow POS - Restaurant Management System

## Project Structure

All application files are located in the `main/` directory.

### Quick Start

1. Point your web server document root to the `main/` directory
2. Import the database schema from `main/database/database_schema.sql`
3. Configure database connection in `main/db_connection.php`
4. Access the application at your domain

### Directory Structure

```
main/
├── admin/          # Admin panel
├── api/            # API endpoints
├── assets/         # CSS, JS, images
├── config/         # Configuration files
├── controllers/    # Business logic controllers
├── database/       # SQL schema files
├── docs/           # Documentation
├── frontend/       # Public landing page
├── public/         # Public files
├── sujay/          # Testing dashboard
├── superadmin/     # Superadmin panel
├── uploads/        # User uploaded files
├── views/          # Dashboard views
├── website/        # Public website
├── db_connection.php
└── index.php       # Main entry point
```

### Deployment

For Hostinger or similar hosting:
- Set document root to: `main/`
- Or create a symlink: `public_html -> main`

### Documentation

See `main/docs/` for detailed documentation.

