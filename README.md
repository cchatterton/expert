# Expert

A theme-only WordPress Multisite knowledge experiment by Techn.

[Installation, runtime contract and product documentation](expert/readme.md) · [Validation](docs/VALIDATION.md) · [Standards mapping](docs/STANDARDS.md)

Upload the root `expert.zip` using Network Admin → Themes. The `expert/` directory is the complete theme; `scripts/`, `tests/` and `docs/` are repository-only.

Build: `bash scripts/build-theme-zip.sh`

Automated integration tests use a disposable fresh Multisite database. Never run them against production. Run `wp eval-file tests/integration.php`, followed by `wp eval-file tests/updates.php`. Browser checks use Playwright/Chrome, a local test server and an admin password from `EXPERT_TEST_PASSWORD`; see the test file for local fixtures and addresses. No test fixtures or dependencies are packaged in the theme.
