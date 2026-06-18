# AKHLAK360 Functional Interaction Audit

Date: 18 June 2026  
Project: Laravel 12, Blade, AdminLTE, SQLite  
Roles: `admin_hr`, `supervisor`, `employee`, `management`, `it_admin`

## Executive Summary

The audit covered real HTTP actions, database effects, authorization, validation, flash messages, pagination, downloads, JavaScript confirmations, role menus, and browser-visible behavior. The supplied APSI document was used as the functional acceptance reference.

Final automated status: **109 tests passed, 975 assertions**.  
Baseline before this audit: **102 tests passed, 621 assertions**.  
Application routes: **112**.  
Production frontend build: **passed**.

No known broken application button, search, filter, form, or named route remains in the audited scope.

## Interaction Matrix

| Module | Interactions verified | Result |
| --- | --- | --- |
| Authentication | Login, role redirect, logout, remember-me, forgot-password page, SSO simulation page, invalid credentials, unauthorized routes | Pass |
| Navigation | Role-specific sidebar items, collapsible groups, breadcrumbs, active destinations, legacy redirects, route-name integrity | Pass |
| Search and filters | Master data, periods, assignments, peer approvals, notifications, audit logs, compliance, analytics, reports, IDP, talent mapping, HRIS history | Pass |
| Pagination | Query persistence, next/page links, filtered second pages, row integrity, empty states | Pass |
| CRUD | Departments, positions, employees, periods, assignments, weights, IDP updates, relationship constraints, audit records | Pass |
| Assessment workflow | Assignment generators, manual assignment, fill/submit, duplicate blocking, peer approval/rejection, recalculation, explicit close, reminders | Pass |
| Notifications | Dropdown, unread count, individual/all read actions, ownership, safe destination redirects, reminder creation | Pass |
| Analytics | Period/department filters, reset controls, database-driven charts/tables, role scoping, empty states | Pass |
| Reports | Filtered CSV, XLSX, PDF, non-empty files, export history, generated/failed records, audit records | Pass |
| HRIS | Sample CSV, valid/invalid upload, duplicate update, order-independent supervisor mapping, manual sync, history filters, logs | Pass |
| Profile | Profile/password validation, linked employee details, account deletion protection, role-safe access | Pass |

## Broken Interactions Found and Fixed

1. Excel and PDF controls were disabled or returned warning redirects instead of files.
   - Added `maatwebsite/excel`, completed XLSX generation, and connected the existing DomPDF package.
   - All formats now respect report filters and create successful `report_exports` and audit records.
   - Export exceptions are caught, logged, and recorded as failed exports.

2. Assessment periods had no explicit close action.
   - Added `PATCH assessment-cycle/periods/{period}/close`.
   - Closing recalculates results first, updates status, shows feedback, enforces `admin_hr`, and writes an audit record.

3. HRIS had no sample download or searchable history.
   - Added a documented sample CSV endpoint.
   - Added message, type, status, and date-range history filters with reset and pagination persistence.

4. HRIS supervisor mapping depended on CSV row order.
   - Import now performs employee upserts first and supervisor mapping second.
   - Duplicate employee numbers update existing records.

5. Empty/malformed HRIS files could fail outside a user-safe validation flow.
   - Added required/duplicate-header checks, empty-row checks, structural validation, failed sync logs, audit records, and visible validation errors.

6. Notification dropdown items did not open their actual destination.
   - Added nullable `destination_url`.
   - Clicking a dropdown item marks it read and redirects only to a safe internal path.
   - Reminder, result, seeded, and HRIS notifications now have meaningful destinations.

7. Report buttons used `href="#"` when adapters were unavailable.
   - Removed placeholder links; all report buttons now target working endpoints.

8. Several filtered pages lacked reset actions or explicit button types.
   - Added reset links across analytics, reports, notifications, HRIS, IDP, talent mapping, audit/compliance, results, and periods.
   - Added explicit button types and removed an empty non-functional dashboard form.

9. Period and position search conditions were not grouped.
   - Grouped multi-column searches so additional filters apply to every search branch.

10. Master-data and period list filters accepted unvalidated values.
    - Added safe validation for search lengths, statuses, and department identifiers.

## Tests Added or Expanded

- Real filtered XLSX and PDF downloads, file signatures, export records, and audit records.
- HRIS sample download, malformed headers, failed logging, row-order-independent supervisor mapping, history filters, invalid filters, and pagination persistence.
- Notification destination redirects, read state, and external redirect rejection.
- Explicit period closing, recalculation feedback, authorization, and audit logging.
- Blade route-name existence, placeholder-link absence, explicit button types, and form actions.
- Department/position search pagination, query persistence, row counts, and invalid filter handling.

Existing coverage also verifies all important CRUD, assessment, reminder, recalculation, profile, authorization, validation, flash-message, analytics, and export endpoints.

## Browser Walkthrough

Browser automation used seeded accounts with password `password`.

- `admin_hr`: login, dashboard, collapsible Master Data menu, employee search, filtered URL/result change, report controls and reset.
- `supervisor`: role menu visibility, peer approval page, approve/reject controls, reset action, forbidden master-data access.
- `employee`: role menu visibility, pending queue, real assessment form, all AKHLAK sections, submit control.
- `management`: analytics/report visibility, hidden operational menus, core-value dashboard, reset and database-driven results.
- `it_admin`: HRIS/audit/settings visibility, hidden reports, HRIS sample/manual/import controls, history reset, notification dropdown.
- Shared: account logout, breadcrumbs, notification unread state, mark-read destination, confirmation dialog, success flash.

The in-app browser cannot retain download events. CSV/XLSX/PDF response headers and file integrity were therefore verified in feature tests; XLSX begins with the ZIP signature and PDF begins with `%PDF`.

## Final Verification

- `php artisan migrate:fresh --seed` - passed
- `php artisan route:list --except-vendor` - passed, 112 routes
- `php artisan test` - passed, 109 tests and 975 assertions
- `npm run build` - passed

## Remaining Limitations

- Company SSO and HRIS are academic/local simulations; production OIDC/SAML and external HRIS APIs require real provider credentials and contracts.
- Email reminders depend on production mail transport configuration.
- HTTPS/TLS, uptime, 1,000-user concurrency, and infrastructure monitoring are deployment concerns and were not load-tested locally.
- Browser download capture is unsupported by the available browser tool, although downloadable responses and file contents are covered automatically.
