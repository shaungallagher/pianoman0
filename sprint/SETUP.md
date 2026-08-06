Sprint — Setup instructions

1) Ensure you have PHP and MySQL available locally.

2) Configure `.env` in the project root with your DB credentials and optional `BASE_URL` and Hack Club OAuth keys:

Example `.env`:

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=sprint
DB_USER=root
DB_PASS=secret

HACKCLUB_CLIENT_ID=...
HACKCLUB_CLIENT_SECRET=...
HACKCLUB_REDIRECT_URI=https://yourhost/sprint/auth/callback.php

3) Check connectivity:

```bash
php public/db_status.php
# or open http://localhost/sprint/public/db_status.php in your browser
```

4) If the DB is reachable but schema is missing, initialize it from `db.sql`:

```bash
php scripts/init_db.php
```

If you are unable to run MySQL locally, you can use the bundled SQLite fallback which creates `data/sprint.sqlite`:

```bash
php scripts/init_sqlite.php
```

Environment variables for new features:

- `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET` — to enable GitHub account linking (optional).
- `GITHUB_REDIRECT_URI` — optional callback override for GitHub OAuth.
- `API_KEY` — optional API key for protecting non-GET API endpoints in `/api/index.php`.

To add support for file uploads on existing databases, run the migration script:

```bash
php scripts/migrate_add_submission_files.php
```

To add the new OpenID-related columns to an existing MySQL install, run:

```bash
php scripts/migrate_add_user_fields.php
```

5) Verify required tables exist:

```bash
php scripts/check_schema.php
```

6) Run the app (for example with PHP's built-in server for local testing):

```bash
php -S 0.0.0.0:8000 -t public
```

Notes
- The OAuth login flow must be started from the site's login page (do NOT use the provider's "test redirect" button — that won't create a local session and will cause an "Invalid OAuth state" message).
- If OAuth doesn't return `email`/`name`, ensure the OAuth app requests the `email` and `profile` scopes. The code now encodes scopes with RFC3986 to avoid providers mis-parsing space encoding.
