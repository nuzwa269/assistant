# Local regression tests

These scripts load WordPress core from `COACHPRO_WP_ROOT` (default
`C:/xampp/htdocs/ai-assistant/`) without reading the site's `wp-config.php`.
They require a disposable `coachpro_integration_test` database on
`127.0.0.1:3308`, using local root credentials with an empty password.
The integration script recreates only the test database's `cpt_coachpro_*`
tables and CoachPro options. Do not point these scripts at a live database.

For the existing workspace fixture, start MariaDB in a separate terminal:

```powershell
& C:/xampp/mysql/bin/mysqld.exe --no-defaults --basedir=C:/xampp/mysql "--datadir=$PWD/.test-runtime/mysql" --port=3308 --bind-address=127.0.0.1 --console
```

Run in order:

```powershell
& C:/xampp/php/php.exe tests/integration.php
& C:/xampp/php/php.exe tests/concurrency.php
```

Stop the isolated server after testing:

```powershell
& C:/xampp/mysql/bin/mysqladmin.exe --no-defaults --host=127.0.0.1 --port=3308 --user=root shutdown
```

## Verification on 2026-09-24

- 43 integration checks passed, including stale chat reservation recovery,
  exactly-once refunds, and deletion protection while chat is pending.
- Both five-worker concurrency checks passed: one credit is spent once and
  one payment is approved once.
- All PHP files passed syntax checks; both JavaScript files passed `node --check`.
- `git diff --check` passed.
- With the isolated database stopped, the test bootstrap correctly exits nonzero.

Provider responses are mocked. These checks do not verify live provider calls,
Google OAuth, real file uploads, or browser interactions. Existing implementation
changes were preserved; this continuation changed only the tests and this guide.
