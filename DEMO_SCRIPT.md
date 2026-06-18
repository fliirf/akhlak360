# AKHLAK360 Live Demo Script (10-15 Minutes)

Start at `http://127.0.0.1:8000/sso/login`. Use personal SSO codes, not passwords and not the legacy `SSO2026` value.

## 1. Admin HR (4 minutes)

Login: `EMP001` / `admin_hr@example.com` / `AKH-HR01-2026`

| Menu path | Click/action | What to say | Expected result | Fallback |
| --- | --- | --- | --- | --- |
| Dashboard | Open dashboard | “This is the operational command center for the active cycle.” | 20 active employees, active period, assignments, completion, overdue, threshold, department progress | Use Department Completion table |
| Master Data → Employees | Search `Sari`, choose Operations + Active, Search | “Employee data is synchronized from the HRIS simulation and remains filterable.” | One Sari Employee row | Reset Filters |
| Employee row | Edit icon | “HR can inspect and maintain the linked employee record.” | Edit Employee page opens | Return to employee list |
| Assessment Cycle → Periods | Show Semester 1 2026; click Recalculate | “The cycle is 16-29 June, exactly 14 days, with threshold 3.00.” | Recalculation keeps 20 results | Do not click Close |
| Assessment Cycle → Weights | Open active period | “Supervisor 40, peer 20, subordinate 30, self 10—total 100%.” | Four values and 100% total | State values from screen |
| Assign Assessors | Filter active period | “All four assessor relationships and pending/submitted status are traceable.” | Assignment table and counters | Use dashboard quick action |
| Reports → Export Reports | Select Semester 1 2026 + Operations, Apply Filters | “Exports honor the same period and organization filters.” | Filtered preview plus CSV/Excel/PDF links | Show Export History |

## 2. Supervisor (3 minutes)

Logout, then login: `EN-0003` / `supervisor@example.com` / `AKH-SPV3-2026`

| Menu path | Click/action | What to say | Expected result | Fallback |
| --- | --- | --- | --- | --- |
| Dashboard | Open dashboard | “A supervisor sees only direct-report workload and aggregate team insight.” | Four direct reports, completion, pending work, aggregate scores | Use Direct Report Assessment Status |
| Assessment Cycle → Peer Approval | Approve the pending Operations proposal | “The supervisor validates the peer; approval creates the assignment automatically.” | Success and proposal leaves pending queue | Show approved audit event |
| Assessment → Pending Assessments | Open a Supervisor task; score all 18 indicators; Submit | “The form covers three behaviors for each of six AKHLAK values.” | Task leaves queue, timestamp/audit/result created | If already submitted, open another pending task |
| Assessment → Team Results | Open page | “Only aggregates are visible—no peer identities or individual answers.” | Assessor-type aggregate table | Use dashboard aggregate table |
| Direct URL check | `/master-data/employees` | “RBAC blocks HR operations.” | 403 | Skip if time is short |

## 3. Employee (2 minutes)

Logout, then login: `EN-0005` / `employee@example.com` / `AKH-EMP5-2026`

| Menu path | Click/action | What to say | Expected result | Fallback |
| --- | --- | --- | --- | --- |
| Dashboard | Open dashboard | “Employees get tasks, personal results, gap, trend, and IDP—not company rankings.” | Personal cards, charts, task/history tables | Personal Results |
| Assessment → Pending Assessments | Open the remaining task, complete 18 indicators, Submit | “Duplicate submission is prevented and the task leaves the queue.” | Pending task disappears | If already submitted, show Submission History |
| Assessment → Personal Results | Open page | “This endpoint always scopes to the authenticated employee.” | Sari’s six values, self/others/gap/category/history | Add `?employee_id=6`; Sari remains visible |
| Profil Saya | Open page | “Identity and HRIS details are read-only; there is no role editor.” | Employee number, department, position, supervisor, SSO/HRIS status | Use dashboard profile card |

## 4. Management (2-3 minutes)

Logout, then login: `EMP002` / `management@example.com` / `AKH-MGT2-2026`

| Menu path | Click/action | What to say | Expected result | Fallback |
| --- | --- | --- | --- | --- |
| Dashboard | Select Semester 1 2026 + Operations, Apply; then Reset | “Every card, chart, and table follows the same filters.” | Values change consistently and reset restores company view | Use Core Value Dashboard |
| Analytics | Open Gap Analysis, Department Distribution, Semester Trend | “Management sees company and department patterns, not individual assessor answers.” | Database-backed charts and summaries | Use adjacent tables |
| IDP & Talent → Talent Mapping | Open page | “Talent categories and development attention are available at aggregate level.” | High Potential and other categories | IDP Summary |
| Reports → Export Reports | Preview filtered report; click PDF or Excel | “Management can export the filtered management view.” | Download and export-history record | Show Export History |
| Direct URL check | `/assessment-cycle/assign-assessors` | “Operational assignment controls remain restricted.” | 403 | Skip if time is short |

## 5. IT Admin (2 minutes)

Logout, then login: `EMP003` / `it@example.com` / `AKH-IT03-2026`

| Menu path | Click/action | What to say | Expected result | Fallback |
| --- | --- | --- | --- | --- |
| Dashboard | Open dashboard | “IT sees technical operations, configuration, reminders, exports, and audit volume.” | Runtime/configuration and activity cards | System Settings |
| Master Data → HRIS Sync | Open page | “Monitoring is read-only for IT and includes a seeded failure example.” | Success/failure sync history; no import form | Audit Logs |
| Audit & Compliance → Audit Logs | Filter module `assessment_forms` or `peer_approvals` | “The complete business flow is auditable.” | Login/approval/submission/calculation events | Clear filters and show recent events |
| Reports → Export History | Open page | “Generated and failed exports remain traceable.” | Format, status, user, period, timestamp | IT dashboard export activity |
| System Settings | Open page | “Configuration is displayed read-only, including default weights and scheduler limitation.” | SQLite/local/simulation settings; no daemon claim | IT dashboard Runtime table |
| Direct URL check | `/assessment/results` | “IT cannot see confidential employee results.” | 403 | Skip if time is short |

## Final Close

Say: “The flow starts from HRIS identity data, passes through governed 360-degree assignments and 18 behavioral indicators, calculates AKHLAK results and development plans, gives management aggregate insight, and leaves a complete technical audit trail.”

If the demo state is dirty, run:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
npm run build
php artisan serve
```
