# Contributing to NOEI CMS

Thank you for your interest in contributing to **NOEI CMS**! We are building a modern, fast, secure, and shared-hosting-first PHP/MySQL Content Management System.

---

## 🧭 Core Architectural Tenets

When writing code or submitting Pull Requests, you must strictly uphold these non-negotiable principles:

1. **Zero Mandatory Infrastructure Overhead:**
   - Core features MUST run on standard shared hosting (PHP 8.1+, MySQL/MariaDB, Apache/LiteSpeed).
   - Never introduce mandatory runtime dependencies on Node.js, NPM build steps, Redis, Composer at runtime, Docker, or SSH/CLI access.

2. **Security by Design:**
   - **PDO Prepared Statements Only:** Never interpolate or concatenate variables into SQL strings. Always use prepared statements with bound parameters.
   - **Mandatory CSRF Verification:** All `POST`, `PUT`, `PATCH`, and `DELETE` requests must validate session CSRF tokens.
   - **Output Escaping:** Escape all user-supplied data in HTML views using `e(?string $value)`.
   - **File Upload Protection:** Verify file MIME types using `finfo_file` and prevent script execution in upload directories.

3. **Coding Standards:**
   - Follow **PSR-12** formatting and **PSR-4** autoloading.
   - Declare `declare(strict_types=1);` at the top of every PHP file.
   - Explicit parameter types and return type hints on all functions and methods.

4. **Multi-Byte Internationalization (i18n):**
   - Preserve `utf8mb4` encoding across database queries, connections, and regex parsing (handling English, Bangla, and multi-byte unicode scripts).

---

## 🛠️ Development & Pull Request Workflow

1. **Fork the Repository:**
   Fork the repository to your GitHub account and clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/NOEI.git
   cd NOEI
   ```

2. **Create a Feature Branch:**
   ```bash
   git checkout -b feature/my-new-feature
   ```

3. **Run Automated Test Suites:**
   Verify that all 10 test suites pass before submitting changes:
   ```bash
   php tests/test_core.php
   php tests/test_milestone2.php
   php tests/test_milestone3.php
   php tests/test_milestone4.php
   php tests/test_milestone5.php
   php tests/test_milestone6.php
   php tests/test_milestone7.php
   php tests/test_milestone8.php
   php tests/test_milestone9.php
   php tests/test_milestone10.php
   ```

4. **Commit & Push:**
   Write clear, descriptive commit messages:
   ```bash
   git commit -m "feat(module): add extension sandbox capability checks"
   git push origin feature/my-new-feature
   ```

5. **Open a Pull Request:**
   Submit your pull request against the `main` branch. Ensure CI matrix tests pass across PHP 8.1, 8.2, and 8.3.

---

## 🐛 Reporting Bugs

- Search existing issues to ensure the bug hasn't been reported.
- Use the [Bug Report Template](.github/ISSUE_TEMPLATE/bug_report.md) with detailed steps to reproduce, environment info (PHP version, OS, web server), and error logs from `/storage/logs/`.
