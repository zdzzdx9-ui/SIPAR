# SIPAR Audit Findings

**Audit date:** 2026-11-11

**Scope:** Responsive UI, loan/return workflow, access control, database query patterns, and asset delivery.

## Executive summary

| Area | Result | Notes |
|---|---|---|
| Responsive UI and touch targets | Improved; visual device verification remains | Application controls are now sized to at least 48 px on viewports up to 1024 px. No automated device/browser screenshot pass was available. |
| Loan allocation and stock concurrency | Pass by code inspection; production stress test outstanding | Loan creation and approval use transactions and row locks. Technician handover/return operations also lock transaction and inventory records. |
| Upload validation and transaction safety | Pass with improvement | Server-side validation requires a supported image and item condition. Failed handover/return transactions now remove staged photos instead of leaving orphan files. |
| Status synchronization | Partial | POST operations redirect to fresh server-rendered pages, but already-open pages do not receive live updates through polling, SSE, or WebSockets. |
| RBAC, CSRF, input handling | No additional actionable finding from the scoped review | Role middleware, CSRF-protected forms, request validation, Eloquent queries, and escaped Blade output were reviewed. |
| Query and asset performance | Partial | Common lists eager-load relations; the return-search endpoint was changed to eager-load handover/return details. Large-catalog behavior and the two-second asset-load target were not measured. |

## Findings

### HIGH — SQL backup is under the web document root

**File:** [`backup_sipar.sql`](./backup_sipar.sql), line 545

**Confidence:** 8/10

The database dump is located under `C:\xampp\htdocs`, which is XAMPP's configured document root. It contains user records, password hashes, sessions, and loan data. A direct HTTP download could bypass Laravel authentication if Apache serves this path. Apache was not running during the audit, so an HTTP request could not be verified.

**Recommended action:** Move backups outside the served tree, restrict access to the directory, and review access logs. If this file was exposed, assess session invalidation and affected account credentials. This audit did not move or delete the untracked backup.

## Improvements applied during audit

- Raised mobile/tablet interactive targets (buttons, close controls, navigation, password toggles, pagination, and form selects/uploads) to at least 48 px.
- Removed per-detail aggregate queries from the technician return-search endpoint by eager-loading handover and return details.
- Added cleanup of staged handover/return photos when a transaction fails or is a duplicate, with regression assertions for validation failures.

## Follow-up validation

- Full Laravel suite: **81 passed (797 assertions)**.
- Production asset build: **passed**.
- PHP formatting check on changed PHP files: **passed**.
- Targeted viewports, real production concurrency/load, and end-to-end response-time measurements were not run. The two-second asset target cannot be certified without representative images, deployed infrastructure, and network testing.
