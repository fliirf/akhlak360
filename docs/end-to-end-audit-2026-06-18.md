# AKHLAK360 End-to-End Audit - Pre-Implementation Findings

Date: 18 June 2026

Baseline evidence:

- 120 tests passed with 1,087 assertions at the start of this audit pass.
- 106 application routes are registered after removal/redirect of public password authentication surfaces.
- Existing tests provide broad CRUD and rendering coverage, but several business-state and authorization transitions were not exercised.
- No schema change is required for the findings below.

## Confirmed Findings

| ID | Severity | Module / Role / Route | Controller or Service | View | Expected Behaviour | Actual Behaviour | Proposed Fix |
| --- | --- | --- | --- | --- | --- | --- | --- |
| AUD-01 | Critical | Assessment; supervisor/employee; `GET fill`, `POST submit` | `AssessmentFormController` | `assessment/forms/pending`, `assessment/forms/show` | Only assignments in an active period between its start and end dates may be opened or submitted. | Any pending assignment owned by the user can be opened and submitted, including draft, closed, not-yet-started, and expired periods. | Centralize an open-period check, scope pending queues to open periods, and reject stale submissions before writing responses. |
| AUD-02 | High | Account/Profile; every authenticated role; `/profile`, `/password`, `/confirm-password` | `ProfileController`, Breeze password controllers | Profile partials | HRIS/SSO is the identity source; random passwords are technical attributes only. | Users can change SSO-synchronized name/email, update the technical password, confirm it, or delete their own linked user account. | Make identity/profile employment data read-only, remove password and account-deletion controls, and disable the authenticated password-management endpoints. |
| AUD-03 | High | HRIS; IT Admin; HRIS sample/import/manual routes | `HrisSyncController` | `master-data/hris-sync/index` | IT Admin monitors synchronization history; Admin HR performs employee-data mutations. | IT Admin has the same sample download, CSV import, and manual-sync mutation routes and buttons as Admin HR. | Split read access from mutation access at route level and hide mutation controls from IT Admin. |
| AUD-04 | High | Assignments; Admin HR; assignment create/update | `AssessmentAssignmentController` | `assessment-cycle/assignments/_form` | New assignments belong to the active period and begin pending; submitted state is produced only by completing 18 responses. | Manual forms accept any period and allow `status=submitted`, producing submitted assignments with no responses or timestamp. | Limit form options and validation to active periods, force pending state, and remove status selection. |
| AUD-05 | High | Peer Approval; Admin HR/Supervisor; propose/approve | `PeerApprovalController` | `assessment-cycle/peer-approvals/index` | Assessee, peer, and supervisor must be active; approval must still belong to an open active period. | Proposal validation accepts inactive employees and peers. Approval can create/reset an assignment after the period closes or after involved employees become inactive. | Validate active employees during proposal and re-check period and employee status transactionally during approval. |
| AUD-06 | Medium | Assessment Period; Admin HR; period create/update | `AssessmentPeriodController` | `assessment-cycle/periods/_form` | Proposal periods last no more than 14 inclusive calendar days. | Only chronological ordering is validated; arbitrarily long periods are accepted. | Add an inclusive 14-day maximum validation rule and explanatory form copy. |
| AUD-07 | Medium | Assignment generation; Admin HR; generate subordinate | `AssessmentAssignmentController` | Assignment index | Every active employee with active direct reports can receive subordinate assessments. | Generation additionally depends on position names containing “Supervisor” or “Manager”, so valid leaders with other titles are silently skipped. | Base generation solely on the HRIS direct-report relationship and active status. |
| AUD-08 | Medium | Reminders; scheduler/IT Admin | `SendAssessmentReminders` | IT dashboard/compliance | One reminder event per assignment per due interval; inactive users receive none; counts reflect actual delivery attempts. | Duplicate suppression relies on in-app notifications, so email-only configuration can resend on the same day. The command increments “generated” even when both channels are disabled and does not exclude inactive assessors. | Record per-assignment reminder audit evidence, deduplicate against it, require an active assessor, and count only events with at least one enabled channel. |
| AUD-09 | Medium | Reports; Admin HR/Management; preview/CSV/XLSX/PDF | `ReportController` | Reports | Each result row must display/export the IDP from the same assessment period. | Without a period filter, the first employee IDP is used and can belong to a different semester. | Resolve the IDP by each result's `assessment_period_id` in preview and all export formats. |
| AUD-10 | Medium | HRIS import; Admin HR | `HrisSyncController` | HRIS sync | A row reported as failed should not leave a partially updated employee; existing position metadata should synchronize. | Supervisor mapping failures happen after the employee row is committed, so failed rows remain partially imported. Existing position levels are never updated. | Prevalidate row references, use a transaction for the import phases, and synchronize existing position levels. |
| AUD-11 | Medium | Employee task queues/dashboards | `AssessmentFormController`, `DashboardService` | Pending assessment and role dashboards | Actionable task counts and buttons include only currently open assignments. | Pending counts and lists include draft, closed, future, and expired periods; users see actions that later should be unavailable. | Add a reusable open-period assignment scope and apply it to actionable queues while retaining overdue metrics separately. |
| AUD-12 | Low | Authentication; every role; logout | `AuthenticatedSessionController` | AdminLTE user menu | Logout returns directly to Company SSO. | Logout redirects to `/`, requiring an additional redirect to `/sso/login`. | Redirect directly to the named SSO route while preserving audit/session invalidation. |
| AUD-13 | High | Employee master data; Admin HR; employee index | `Employee::scopeSearch` | Employee list | Search must remain constrained by department and employment-status filters. | Ungrouped `OR` search clauses could bypass later department/status constraints and display out-of-scope rows. | Group name/NIP/email search conditions in a nested predicate and add a combined-filter regression test. |
| AUD-14 | High | Assessment periods; Admin HR; delete period | `AssessmentPeriodController::destroy` | Period list | A period containing any related assessment/configuration/history data must not be deleted. | Only assignments were checked; a period containing weights, peer proposals, results, IDPs, or export history could cascade-delete that data. | Detect every period-owned data relationship and close the period instead of deleting it. |
| AUD-15 | High | Result calculation; all roles | `AssessmentResultService` | Results, dashboards, exports | Only complete 18-response submissions may produce results; recalculation must remove stale derived rows. | Legacy/malformed submitted rows could be averaged, and an old result/IDP could remain when no valid submission existed. | Validate six groups of three scores in range 1–5 before calculation and remove stale result/IDP rows during recalculation. |
| AUD-16 | Medium | Assignment generation and peer proposal; Admin HR | `AssessmentAssignmentController`, `PeerApprovalController` | Assignment and peer pages | Mutating assessment workflows require a period that is active and inside its start/end dates. | Generator and proposal paths accepted an `active` period even when it was future-dated or expired. | Resolve periods through the reusable `open()` scope for all creation/generation/proposal paths. |
| AUD-17 | Medium | Assessment submission; employee/supervisor | `AssessmentFormController::submit` | Assessment form | Concurrent duplicate submissions must not create duplicate responses or conflicting results. | Availability was checked before the transaction without locking the assignment row. | Lock and re-check the assignment, response existence, and period window inside the transaction. |
| AUD-18 | Low | Management dashboard | `DashboardService` | Management dashboard | Applied combined filters have an obvious reset action. | The filter panel only exposed Apply. | Add a canonical reset link and verify it in the browser. |
| AUD-19 | Low | IT settings | Configuration | System settings | Read-only report-format configuration matches implemented exports. | Configuration listed CSV only while CSV, XLSX, and PDF were operational. | Declare all three active formats in the configuration shown to IT Admin. |

## Verified Areas Without Confirmed Defects

- Public SSO-only login, generic failures, role-priority mapping, provisioning, inactive-user middleware, and rate limiting.
- Registration and public password-reset routes are unavailable or redirect to SSO.
- Score aggregation averages multiple assignments by assessor type before applying period weights.
- Missing assessor types are proportionally normalized by the current documented MVP rule.
- Final score, self score, others score, gap, threshold comparison, IDP weakest value, and idempotent result upsert have deterministic automated coverage.
- Supervisor result and talent views are scoped to direct reports and expose aggregate assessor-type data rather than individual peer/subordinate responses.
- Employee result access is scoped to the linked employee.
- Management dashboards expose organization aggregates rather than employee rankings.
- Report formats are real CSV/XLSX/PDF outputs and existing filters are applied to result rows.
- Compliance monitoring is period-scoped and does not claim that configuration proves a live scheduler process.

## Implementation Order

1. Close assessment-period state and assignment-integrity gaps.
2. Enforce SSO/HRIS-managed account boundaries.
3. Separate HRIS monitoring from mutation permissions.
4. Harden peer approval and reminder delivery.
5. Correct report/HRIS period and transaction integrity.
6. Add deterministic regression tests, then repeat full CLI and browser verification.

## Final Verification Evidence

- `php artisan optimize:clear` — passed.
- `php artisan migrate:fresh --seed` — passed.
- `php artisan test` — 124 tests passed, 1,103 assertions.
- `php artisan route:list --except-vendor` — 106 routes.
- `npm run build` — passed with Vite 7.3.5.
- `git diff --check` — passed.

Browser walkthrough used Company SSO simulation code `SSO2026` and all five seeded role identities:

- Admin HR: dashboard metrics, role menu, quick actions, compliance/audit destinations, and logout.
- Supervisor: direct-team dashboard, pending assessments, peer approval controls, and 403 response for employee master data.
- Employee: personal dashboard, pending queue, real form containing 18 indicators/90 Likert radio choices, and no browser console errors.
- Management: four combined filters, reset action, four charts, aggregate department attention, and 403 response for employee master data.
- IT Admin: technical dashboard, read-only HRIS history, no import/manual-sync controls, read-only settings, export history, and 403 response for report generation.
- Responsive checks at 390×844 on every role dashboard (plus the employee form and IT settings) found no document-level horizontal overflow.
- Browser console inspection found no warning or error entries during the walkthrough.
