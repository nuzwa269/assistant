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

For Gemini configuration and Quick Chat regression coverage, run
`C:/xampp/php/php.exe tests/gemini.php`. This also runs the integration suite
and recreates its disposable fixtures. It verifies admin create/update validation,
existing resource-prefixed model names, and the dashboard's project/conversation/chat
request sequence using mocked responses and synthetic model IDs. All 43 integration
checks and 13 Gemini checks passed; no live provider key is needed.

For Quick Chat retry and plan-downgrade regressions:

```powershell
node tests/quick-chat.js
& C:/xampp/php/php.exe tests/credit-regressions.php
```

The JavaScript tests exercise the shipped request sender with a mocked transport,
including lost responses, busy/pending requests, confirmed failures, and double
submissions. The PHP script runs the integration suite first, then verifies retry
billing, expiry/direct-downgrade enforcement, preserved data, and restored access
after upgrade. Excess prebuilt activations remain stored but are paused, ordered
by activation time and ID; deactivating the allowed assistant promotes the next one.
These are automated transport/REST tests, not browser click-through tests.

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
