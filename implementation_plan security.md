# Security Vulnerability Remediation Plan

This implementation plan details the strategy to fix multiple security vulnerabilities identified in the application, including SQL Injection, Insecure File Uploads, Cross-Site Scripting (XSS), Cross-Site Request Forgery (CSRF), and Session/Authorization issues.

## User Review Required

> [!IMPORTANT]
> - Implementing CSRF tokens will require modifying **all** HTML forms in the application that submit via POST.
> - Session regeneration and timeout might cause users to be logged out if they are idle. Is a 30-minute timeout acceptable?
> - Let me know if there are any specific file types or sizes we should allow for the file uploads.

## Proposed Changes

---

### 1. SQL Injection Mitigation

**Context:** Direct concatenation of variables like `$filterMonth` and `$filterYear` into SQL queries in reports.

#### [MODIFY] modules/reports/profit_loss.php
- Strictly cast `$filterMonth` and `$filterYear` to `(int)`.
- Use prepared statements or validate the casted integers before constructing the query string.

#### [MODIFY] modules/reports/export_excel.php
- Apply the same strict `(int)` casting and validation for date parameters.

#### [MODIFY] modules/reports/export_csv.php
- Apply the same strict `(int)` casting and validation for date parameters.

#### [MODIFY] modules/timesheet/approval.php
- Review and apply prepared statements for any dynamic SQL queries involving user input.

---

### 2. Insecure File Uploads

**Context:** Lack of MIME type checking, size validation, and safe renaming for uploaded files.

#### [MODIFY] includes/functions.php
- Enhance the `uploadFile` helper function to include MIME type validation (using `finfo_file`), strict extension allowlists, size limit enforcement, and secure random filename generation.

#### [MODIFY] modules/procurement/receiving/create.php
- Update upload logic to utilize the secure `uploadFile` helper or add strict MIME/size/extension checks before calling `move_uploaded_file`.

#### [MODIFY] modules/timesheet/import_csv.php
- Implement strict CSV MIME type checking (`text/csv`), file size limits, and securely read the file.

---

### 3. Input Validation

**Context:** Lack of input validation for date and numeric parameters.

#### [MODIFY] modules/reports/timesheet.php
- Validate `start_date` and `end_date` using `DateTime::createFromFormat()` or regex `'/^\d{4}-\d{2}-\d{2}$/'` before using them in database queries.
- Validate `month` and `year` inputs.

---

### 4. XSS Vulnerability

**Context:** Outputting data directly from the database without HTML entity encoding.

#### [MODIFY] modules/reports/*.php
- Wrap any database output that is rendered in the UI (like names, remarks, text fields) with `htmlspecialchars()` or the existing `sanitize()` helper function.

---

### 5. Permission Checks

**Context:** Inconsistent usage of `requirePermission()`.

#### [MODIFY] Various Modules
- Audit files in `modules/` and ensure that `requirePermission('appropriate_permission')` is called at the top of every route/view.

---

### 6. CSRF Protection

**Context:** Missing CSRF tokens on forms.

#### [MODIFY] includes/functions.php
- Add `generateCsrfToken()` to create and store a token in `$_SESSION`.
- Add `validateCsrfToken($token)` to verify the token upon POST submission.
- Add `csrfField()` helper to output the `<input type="hidden">` HTML.

#### [MODIFY] All Form Files (e.g., create.php, edit.php, import_csv.php)
- Insert `<?= csrfField() ?>` inside the `<form>` tags.
- Add CSRF validation logic at the beginning of the POST handlers.

---

### 7. Session Security

**Context:** Missing session regeneration, timeout, and IP binding.

#### [MODIFY] includes/auth.php
- Implement Session Timeout tracking: compare the current time with `$_SESSION['last_activity']`. Destroy session if it exceeds the timeout threshold (e.g., 30 mins).
- Implement IP Binding: check if `$_SERVER['REMOTE_ADDR']` matches `$_SESSION['ip_address']` established at login.

#### [MODIFY] login.php (or wherever login is handled)
- Call `session_regenerate_id(true)` upon successful login to prevent Session Fixation.
- Set `$_SESSION['last_activity']` and `$_SESSION['ip_address']`.

## Verification Plan

### Automated/Manual Verification
- **SQLi:** Attempt to inject `' OR 1=1 --` into URL parameters (`month`, `year`) and verify the application returns an empty result or error instead of executing the injected logic.
- **File Upload:** Attempt to upload a `.php` file masquerading as a `.csv` or an oversized file. Verify the system rejects it.
- **XSS:** Insert `<script>alert(1)</script>` into a database field (like remark) and verify it renders as safe text.
- **CSRF:** Attempt to submit a POST request using tools like Postman without the valid `csrf_token` and verify the request is blocked.
- **Session:** Wait 30 minutes and verify the user is forced to log in again. Change IP address (or simulate) and verify the session becomes invalid.
