# AKHLAK360 Live Demo Rehearsal Report

Date: 18 June 2026  
Application: Sistem Penilaian 360° Core Values AKHLAK PT Energi Nusantara  
Runtime: Laravel 12, PHP 8.2, SQLite, Vite production assets, local URL `http://127.0.0.1:8000`

## 1. Demo Environment

The rehearsal used a clean SQLite database created with `php artisan migrate:fresh --seed`. Production assets were built with `npm run build`. The application was served locally and exercised through the Company SSO Simulation UI.

The final seed contains 20 active employees, two assessment periods, all four assessor types, pending and submitted assignments, peer approvals, complete 18-response submissions, results, IDP recommendations, talent mapping, notifications, HRIS success/failure logs, report export history, and audit events.

Active period: Semester 1 2026, 16-29 June 2026 (14 calendar days), threshold 3.00.

## 2. Exact SSO Identities Used

| Role | Employee number | Company email | Personal SSO code |
| --- | --- | --- | --- |
| Admin HR | `EMP001` | `admin_hr@example.com` | `AKH-HR01-2026` |
| Supervisor | `EN-0003` | `supervisor@example.com` | `AKH-SPV3-2026` |
| Employee | `EN-0005` | `employee@example.com` | `AKH-EMP5-2026` |
| Management | `EMP002` | `management@example.com` | `AKH-MGT2-2026` |
| IT Admin | `EMP003` | `it@example.com` | `AKH-IT03-2026` |

No public password login was used.

## 3. SSO Simulation Code Source

The active login implementation validates the per-employee hashed SSO code stored in `employees.sso_code_hash`. Demo plaintext values are deterministically created by `AkhlakDemoSeeder::demoSsoCode()`.

`.env` also contains `SSO_SIMULATION_CODE=SSO2026`. This legacy/global value is not accepted by the current `SsoAuthenticationService`; the automated smoke test confirms it is rejected. Use the personal codes above.

## 4. Admin HR Walkthrough Result

Passed:

- Dashboard cards, active period, assessor-type progress, department completion, attention list, recent submissions, HRIS activity, audit activity, and quick actions rendered from seeded data.
- Departments, positions, employees, search, department/status filters, pagination, and employee edit page were exercised.
- HRIS page showed sample CSV, import form, manual sync action, success/failure history, and technical messages.
- Active and historical periods showed dates, status, threshold, assignment totals, recalculate action, and guarded close action.
- Weights displayed Supervisor 40%, Peer 20%, Subordinate 30%, Self 10%, total 100%.
- Peer approvals displayed pending/approved/rejected records and the employee, proposed peer, supervisor, and status.
- Assignments displayed self, supervisor, peer, subordinate, pending, submitted, filters, pagination, and generation actions.
- Recalculate Results was run after the seeder correction and retained all active results; repeat calculation is idempotent.
- Notifications showed unread state and one notification was marked read.
- Reports preview, period/department filters, CSV/XLSX/PDF links, export history, and audit trail were verified.
- Audit logs and compliance monitoring were period-aware and read-only.

The main active period was not closed.

## 5. Supervisor Walkthrough Result

Passed:

- Dashboard showed four direct reports, completion, pending/completed work, aggregate team scores, development summary, and quick links.
- Only the supervisor's peer approval queue was visible.
- Peer proposal `#6` was approved; a peer assignment was created without exposing unrelated employees.
- Assignment `#141` for Andi Pratama was opened as a Supervisor assessment, all six values and 18 indicators were completed, and the form was submitted.
- The assignment moved from pending to submitted with 18 responses, timestamp, result recalculation, and audit event.
- Reopening the submitted assignment redirected safely and it no longer appeared in pending work.
- Team Results exposed aggregate assessor-type data only.
- Team IDP/Talent pages remained scoped to direct reports.
- `/master-data/employees` returned a safe 403.

## 6. Employee Walkthrough Result

Passed:

- Dashboard showed pending/completed assessor tasks, deadline, personal score/category, personal charts, self-versus-others gap, and historical trend.
- Assignment `#126`, a subordinate assessment of Budi Supervisor, was opened and all 18 indicators were submitted.
- The task left the pending queue and produced 18 stored responses.
- Personal Results displayed only Sari Employee. Supplying `?employee_id=6` did not expose Andi Pratama and still returned Sari's own result.
- Personal IDP and read-only profile displayed employee number, department, position, supervisor, HRIS/SSO status, and no role editor.

## 7. Management Walkthrough Result

Passed:

- Dashboard showed assessed employees, average, completion, below-threshold count, High Potential count, active IDP, company profile, talent distribution, semester trend, gap distribution, and attention by department.
- Core Value, Gap Analysis, Department Distribution, Semester Trend, Below Threshold, Talent Mapping, IDP Summary, and Reports pages rendered.
- Period and department filter controls, combined filtering, and reset links were exercised.
- Management did not receive employee CRUD, HRIS import, assignment generation, peer approval, or settings controls.
- `/assessment-cycle/assign-assessors` returned a safe 403.
- Dashboard remained aggregate-focused.

## 8. IT Admin Walkthrough Result

Passed:

- Dashboard showed audit activity, reminder/export activity, active users, environment/configuration, runtime status, and quick actions.
- HRIS monitoring was read-only and displayed success plus failure history.
- Audit Logs exposed user/module/action/date filters and pagination without edit/delete controls.
- Export History showed format, status, user, period, and timestamp as read-only data.
- System Settings displayed application/environment/database/reminder/notification/threshold/default-weight configuration as read-only.
- Compliance page described SSO and HRIS simulations, RBAC, audit trail, documentation, and scheduler limitations without claiming a daemon is running.
- `/assessment/results` returned a safe 403, preventing access to confidential scores or assessor answers.

## 9. Core Business-Flow Walkthrough

1. Admin HR displayed HRIS-sourced active employee data.
2. Admin HR displayed Semester 1 2026 and the 14-day dates.
3. Admin HR verified 40/20/30/10 weights.
4. A pending Operations peer proposal was shown.
5. Budi Supervisor approved proposal `#6`.
6. The system created the peer assignment.
7. Supervisor opened assignment `#141`.
8. All 18 Likert indicators were completed.
9. The assessment was submitted and audited.
10. Results recalculated automatically and were also recalculated from Admin HR.
11. Results exposed six values, final/self/others/gap/category, and weakest value.
12. IDP recommendations were available.
13. Talent mapping was available.
14. Management aggregate analytics rendered.
15. Report preview and CSV/XLSX/PDF export actions were verified.
16. IT Admin audit pages showed the generated authentication, approval, submission, calculation, notification, and export activity.

## 10. Buttons and Interactions Tested

Company SSO login, logout confirmation, employee Search, Reset Filters, employee Edit, period Recalculate, notification Mark as Read, peer Approve, assessment radio controls, Submit Assessment, analytics Apply/Terapkan, report Apply Filters, pagination, and unauthorized direct navigation.

Generation, destructive employee actions, and active-period Close were intentionally not executed.

## 11. Filters Tested

Employee search + department + active status; period filters; assignment status/type filters; report period + department; analytics period/department; HRIS status; audit user/module/action/date controls; export type/status/period controls.

## 12. Exports Tested

CSV, XLSX, and PDF routes were present with selected filter parameters. The report feature tests verify non-empty valid responses and export-history/audit creation. Export History displayed seeded and generated records.

## 13. Unauthorized Routes Tested

| Role | Route | Result |
| --- | --- | --- |
| Supervisor | `/master-data/employees` | 403 |
| Management | `/assessment-cycle/assign-assessors` | 403 |
| IT Admin | `/assessment/results` | 403 |
| Employee | `/assessment/results?employee_id=6` | Own result only; no other employee exposure |

## 14. Bugs Found

1. Employee edit returned HTTP 500 because `orWhereKey()` was called on an Eloquent builder.
2. Seeded submitted assignments had only 6 responses, so recalculation deleted seeded results.
3. The first 18-response correction used a uniform pattern that flattened recalculated analytics around 4.0.

## 15. Bugs Fixed

1. Replaced the invalid user-option query with a grouped `orWhere('id', ...)` condition and added a linked-user edit regression assertion.
2. Seeder now creates the exact 18 indicators used by the live assessment form.
3. Seeder now creates deterministic low/middle/strong/High Potential responses and a deterministic weakest core value.
4. Added regression coverage proving submitted demo assignments contain 18 responses and active results survive recalculation with below-threshold and High Potential examples.

## 16. Remaining Limitations

- Academic simulations are used for SSO and HRIS.
- Scheduler configuration is shown, but no production scheduler daemon is claimed.
- The in-app browser completed desktop/laptop checks without page overflow. Switching its runtime to 390×844 triggered `ERR_NETWORK_IO_SUSPENDED`; therefore the final 390×844 pass should be repeated manually before the presentation.
- The project has no per-employee result-detail URL; privacy was verified through the personal results endpoint ignoring an injected `employee_id`.

## 17. Recommended Live Presentation Sequence

1. Admin HR: dashboard, period, weights, employee filter, assignments, results, reports.
2. Supervisor: approve the prepared peer proposal, open one pending form, submit it.
3. Employee: show personal dashboard/results/IDP and submit one remaining task only if the seed has just been reset.
4. Management: show aggregate dashboard, apply Operations filter, open Talent Mapping.
5. IT Admin: show HRIS failure history, audit trail, export history, and read-only settings.

Keep the main story on one active period and use direct sidebar navigation.

## 18. Backup Steps

- If SSO appears to wait, refresh once; the authenticated dashboard is normally already established.
- If the prepared supervisor task was previously submitted, reset with `php artisan migrate:fresh --seed`.
- If a peer proposal is no longer pending, reset the seed and use the first Operations proposal shown to Budi Supervisor.
- If an export download is blocked by browser policy, show Report Preview and Export History, then use the matching feature-test result.
- If charts fail to draw, use the adjacent summary cards/tables, which contain the same database-backed values.
- Never close Semester 1 2026 during the presentation.
