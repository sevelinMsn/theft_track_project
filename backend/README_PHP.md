# Theft Track & Reporting — PHP Backend Guide (Beginners)

This folder contains the **API** (Application Programming Interface) for Theft Track & Reporting.
Each `.php` file is one endpoint the frontend (HTML/JavaScript) calls with `fetch()`.

## How a request works

```
Browser (fraud.html, report.html, …)
    → fetch('http://localhost/theft_track_project/backend/login.php', …)
        → login.php runs
            → db.php connects to MySQL ($conn)
            → helpers.php provides shared functions
        → JSON response: { "success": true, "message": "...", … }
```

## Important files (read in this order)

| File | Purpose |
|------|---------|
| `config.php` | Database name, admin password, constants — **edit settings here** |
| `db.php` | Opens MySQL connection → variable `$conn` |
| `helpers.php` | Shared functions used by every API file |
| `login.php` / `register.php` | User accounts |
| `submit_report.php` | Save a new theft report |
| `track_report.php` | Look up a report by tracking ID |
| `fraud_public.php` | Data for the public Fraud Alerts page |
| `admin_suspects.php` | Admin: add/edit/delete suspects (with photo) |
| `admin/index.php` | Admin dashboard (HTML, not JSON) |

## Standard pattern for API files

Every API script follows the same steps:

1. **Include** `db.php` and `helpers.php`
2. **Check** HTTP method (GET, POST, …)
3. **Read** input (`getJsonInput()` or `$_GET` / `$_POST`)
4. **Validate** required fields
5. **Query** the database with prepared statements (`$stmt->prepare` + `bind_param`)
6. **Respond** with `jsonResponse(true/false, 'message', [ extra data ])`

## Database safety

- Always use **prepared statements** (`?` placeholders) — never paste user input into SQL strings.
- Use `cleanInput()` to trim and limit text length before saving.
- Passwords are stored with `password_hash()` and checked with `password_verify()`.

## Sessions

- **Users:** `startAppSession()` then `$_SESSION['user_id']`, etc.
- **Admins:** `$_SESSION['admin_logged_in']` after login in `admin/index.php`
- Check login with `requireUserSession()` or `requireAdminSession()` in helpers.

## Adding a new feature

1. Add table/columns in `sql/schema.sql` (or an upgrade file).
2. Create `backend/your_feature.php` using the pattern above.
3. Call it from JavaScript with `fetch('../backend/your_feature.php', …)`.

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Database connection failed | Start MySQL in XAMPP; import `sql/schema.sql` |
| Table doesn't exist | Run the matching `sql/upgrade_*.sql` in phpMyAdmin |
| Empty JSON / HTML error | Open the `.php` URL directly in the browser to see the error |
