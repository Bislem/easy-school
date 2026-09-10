# Easy School RBAC

`App\Services\AuthorizationService` is the single authorization boundary. The
`permission` middleware protects every `/admin` route, denies unmapped routes,
checks route-bound records, and returns a plain `403 Forbidden` response. The
tenant global scope remains responsible for tenant isolation. Platform
super-administrators continue to use the separate platform guard and routes.

Tenant Administrator is a protected system role. Its effective permission set is
always the complete tenant catalogue and its data scope is always `tenant`; it
does not grant access to platform routes or another tenant.

## Data scopes

- `tenant`: every record permitted by the tenant global scope.
- `site`: records attached to one of the user's assigned school sites.
- `assigned`: assigned groups/classes/students (and records belonging to them).
- `own`: the authenticated user's own user, staff, student, payroll, timetable,
  attendance, observation, or related record.

Multiple roles union their permissions. For a permission supplied by multiple
roles, the broadest scope wins: `tenant > site > assigned > own`. Unsupported
resource/scope combinations return no records and record access is denied.

## Permission catalogue

The executable catalogue is `App\Support\PermissionCatalog::all()` and is the
source used by provisioning, validation, backend guards, and the frontend.

- Employees: `employees.view`, `employees.create`, `employees.update`,
  `employees.delete`, `employees.export`
- Payroll: `salaries.view`, `salaries.manage`, `salaries.approve`,
  `salaries.export`, `payslips.view`, `payslips.manage`, `hr_records.view`,
  `hr_records.manage`
- Payments: `payments.view`, `payments.collect`, `payments.cancel`,
  `payments.refund`, `payments.export`, `school_fees.view`, `school_fees.manage`
- Expenses and finance: `expenses.view`, `expenses.create`, `expenses.update`,
  `expenses.delete`, `expenses.approve`, `financial_reports.view`,
  `financial_reports.export`
- Students and parents: `students.view`, `students.create`, `students.update`,
  `students.delete`, `students.export`, `parents.view`, `parents.create`,
  `parents.update`, `parents.delete`
- Enrollment and groups: `enrollments.view`, `enrollments.manage`, `groups.view`,
  `groups.manage`, `assigned_groups.view`
- Teaching: `timetables.view`, `timetables.manage`, `grades.view`,
  `grades.manage`, `homework.view`, `homework.manage`, `observations.view`,
  `observations.manage`
- Attendance and discipline: `student_attendance.view`,
  `student_attendance.record`, `student_attendance.justify`,
  `staff_attendance.view`, `staff_attendance.record`,
  `staff_attendance.justify`, `absences.view`, `absences.manage`,
  `discipline.view`, `discipline.manage`
- Administration: `administrative_documents.view`,
  `administrative_documents.manage`, `roles.view`, `roles.manage`, `users.view`,
  `users.manage`, `academic_years.view`, `academic_years.manage`, `badges.view`,
  `badges.manage`, `certificates.view`, `certificates.manage`, `reports.view`,
  `reports.export`, `audit.view`

Sensitive operations deliberately use separate permissions: salary approval,
refunds, payment cancellation, exports, and role management. Frontend navigation
receives only the effective permission keys through Inertia and hides inaccessible
entries; the backend check remains authoritative.
