# Gestio Pédagogique

PHP application organized with a small MVC structure and the existing MySQL schema in `SQL/gestion_pedagogique.sql`.

## Structure

- `app/models/` contains database queries for users, academic profiles, registrations, modules, evaluations, and grades.
- `app/controllers/` handles login and role-specific dashboard requests.
- `app/views/` contains the login page and the separate administrator, professor, and student interfaces.
- `app/core/` contains the shared PDO connection, model base, and controller helpers.
- `config/database.php` reads database settings from `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables. Defaults match the existing local MySQL setup.
- `routes/web.php` maps `route` query values to controllers.
- `public/` is the web document root and contains the front controller and static assets.
- `SQL/` and `RAPPORT/` retain the existing project database script and report.

## Run locally

1. Create/import the existing database with `SQL/gestion_pedagogique.sql`.
2. Set the database environment variables if the local MySQL credentials differ from the defaults.
3. Serve the `public/` folder as the document root, for example: `php -S localhost:8000 -t public`.
4. Open `http://localhost:8000` and sign in with an active account from `Utilisateur`.

The app uses only tables and relationships defined in the SQL script. Announcements and notifications currently show an empty state because the schema has no corresponding tables.
