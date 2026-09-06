# Easy School Parent Mobile API v1

Base path: `/api/mobile/v1`  
Content type: `application/json`  
Authentication: `Authorization: Bearer <token>` and `Accept: application/json`

The mobile application is parent-only. Students remain school entities and are accessed only as children linked to an authenticated parent. A user account with only the `student` role cannot log in to the mobile API, and `/student/*` mobile routes do not exist.

## Parent account scenarios

The parent identity is global and its normalized e-mail address is unique. A school never owns or edits that global identity. Each school creates its own local parent profile and membership, then links children to that profile.

### Parent registers before joining a school

`POST /register` (public, limited to 5 requests/minute)

```json
{
  "first_name": "Yacine",
  "last_name": "Amari",
  "email": "parent@example.dz",
  "phone": "+213...",
  "password": "a-strong-password",
  "password_confirmation": "a-strong-password",
  "device_name": "Yacine iPhone"
}
```

The endpoint returns HTTP 201, a bootstrap token, `contexts: []`, `school: null`, and `context_required: true`. The account can log in immediately even though it is not linked to a school. When a school later adds the same e-mail address, it reuses the account's name and contact data without modifying them.

### School creates the parent first

If an administrator adds an e-mail which does not exist, the backend creates the global parent account, links the selected children, and sends a cryptographically random 20-character temporary password by e-mail. It expires after 30 minutes and must be changed before a school context can be selected.

If the e-mail already belongs to a parent account, the administration interface clearly identifies it, copies its existing identity data read-only, and only creates the school's profile, child links, and membership. An e-mail owned by a non-parent account is rejected.

## Authentication flow

### 1. Log in

`POST /login`

```json
{
  "email": "parent@example.dz",
  "password": "secret",
  "device_name": "Yacine iPhone"
}
```

The response contains a bootstrap token and every active school in which the account has a parent membership:

```json
{
  "data": {
    "token": "1|BOOTSTRAP_TOKEN",
    "token_type": "Bearer",
    "expires_at": "2026-12-05T10:00:00+01:00",
    "user": {
      "id": 8,
      "name": "Yacine",
      "email": "parent@example.dz",
      "phone": "+213...",
      "role": "parent"
    },
    "contexts": [
      {
        "membership_id": 19,
        "role": "parent",
        "profile_id": 4,
        "school": { "id": 7, "name": "School B", "slug": "school-b", "logo_url": null }
      }
    ],
    "context_required": true,
    "password_change_required": false
  }
}
```

Student-only accounts receive HTTP 403 with `error: role_not_allowed`.

An unlinked parent receives the same successful login response with `contexts: []` and `school: null`. The app should show a waiting-for-school-link screen and may refresh `GET /contexts`.

### Temporary-password reset

`POST /reset-password` (public, limited to 3 requests/minute)

```json
{ "email": "parent@example.dz" }
```

The response is deliberately identical whether the address exists or not. For an active parent account, all existing API tokens are revoked and a new random temporary password is e-mailed. It expires after 30 minutes. This avoids account enumeration and invalidates previously authenticated devices.

After logging in with the temporary password, `password_change_required` is `true`. The client must call the authenticated endpoint below before selecting a school:

`PUT /password`

```json
{
  "password": "new-strong-password",
  "password_confirmation": "new-strong-password"
}
```

Until that succeeds, `POST /context` returns HTTP 409 with `error: password_change_required`. An expired temporary password returns HTTP 403 with `error: temporary_password_expired` at login.

For a normal authenticated password change, the request must also contain `current_password`, and the new password must differ from it. A successful change keeps the current token and revokes the parent's tokens on other devices.

### 2. Select a school

`POST /context` using the bootstrap token:

```json
{
  "tenant_id": 7,
  "role": "parent",
  "device_name": "Yacine iPhone"
}
```

`role` is optional for new clients and, when provided for backward compatibility, must be `parent`. The response provides a new context token bound to the selected parent membership. Use that token for all school-data endpoints. Selecting an unassigned school returns HTTP 403 with `membership_not_found`.

Other authentication endpoints:

- `GET /contexts` — list current parent/school memberships.
- `GET /profile` — retrieve the global parent profile without selecting a school.
- `PATCH /profile` — update the parent's identity and contact information.
- `GET /me` — global account plus the selected parent context.
- `POST /logout` — revoke the current token.
- `POST /logout-all` — revoke all mobile tokens on every device.

Older parent tokens containing only the `mobile` ability remain supported. Student context tokens and student memberships are not accepted.

## Parent profile and account security

These endpoints accept either a bootstrap token or a selected-school context token, so an unlinked parent can still manage the global account.

### Read the profile

`GET /profile`

```json
{
  "data": {
    "id": 8,
    "name": "Yacine Amari",
    "first_name": "Yacine",
    "last_name": "Amari",
    "email": "parent@example.dz",
    "phone": "+213..."
  }
}
```

### Update basic information

`PATCH /profile`

```json
{
  "first_name": "Yacine",
  "last_name": "Amari",
  "email": "new-address@example.dz",
  "phone": "+213...",
  "current_password": "required-only-when-email-changes"
}
```

Name and phone changes are synchronized to the parent's linked school profiles. Changing the unique login e-mail requires the current password, marks the new address unverified, keeps the current token, and revokes other device tokens. The response contains `meta.email_changed`.

### Change a normal password

`PUT /password`

```json
{
  "current_password": "CurrentPassword123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

`current_password` is omitted only while replacing an unexpired temporary password.

### Session controls

- `POST /logout` revokes only the token making the request.
- `POST /logout-all` revokes every mobile token, including the current token. Clear local credentials and navigate to login after success.

## Parent and child endpoints

All routes require a parent context token. `{student}` must be linked to the selected school's parent profile and its link must be marked visible by that school. An unlinked or hidden child in the same school returns 403; a record outside the selected tenant returns 404.

| Method | URL | Result |
|---|---|---|
| GET | `/parent/children` | Paginated linked children |
| GET | `/parent/children/{student}` | Child profile |
| GET | `/parent/children/{student}/formations` | Registered formations and financial summary |
| GET | `/parent/children/{student}/planning` | Sessions and timetable |
| GET | `/parent/children/{student}/attendance` | Attendance and absence history |
| GET | `/parent/children/{student}/grades` | Empty data with `meta.supported=false` until a grade model exists |
| GET | `/parent/children/{student}/observations` | Paginated observations |
| POST | `/parent/children/{student}/observations/{observation}/replies` | Reply to a teacher/admin observation thread |
| GET | `/parent/children/{student}/certificates` | Certificates and verification URLs |
| GET | `/parent/children/{student}/documents` | Safe document metadata and URLs |
| GET | `/parent/children/{student}/payments` | Balances, installments and payment records |

Child switching is client-side: use the IDs returned by `GET /parent/children`, keep the selected child ID in application state, and request that child's endpoints. No separate child token is needed. If the school hides a child, it disappears from the list and every direct data endpoint for that ID is denied.

Parents cannot open an official observation thread, but they can reply to a visible top-level observation with `{ "message": "..." }`. The message is limited to 5,000 characters, the author is notified, and the endpoint returns the created reply with HTTP 201.

There is currently no standalone homework model or homework endpoint. Events are currently delivered through event/announcement notifications rather than a separate academic-event resource.

## Notifications and events

| Method | URL | Result |
|---|---|---|
| GET | `/notifications` | Paginated parent notification feed |
| PATCH | `/notifications/{notification}/read` | Mark an owned notification read |
| PATCH | `/notifications/read-all` | Mark all selected-school notifications read |
| GET | `/announcements` | Announcement and event notification types |

## School, devices and communication

| Method | URL | Result |
|---|---|---|
| GET | `/school` | Selected school's public contact/settings card |
| POST | `/devices` | Register or update an FCM device |
| DELETE | `/devices/{device}` | Remove an owned FCM device |
| GET | `/conversations` | Paginated support conversations |
| POST | `/conversations` | Create a support conversation |
| GET | `/conversations/unread-count` | Unread school-message count |
| GET | `/conversations/{ticket}/messages` | Messages for an owned conversation |
| POST | `/conversations/{ticket}/messages` | Reply to an open conversation |

## Response and security conventions

- Resources are returned under `data`; pagination adds `links` and `meta`.
- Date-times use ISO 8601 and date-only values use `YYYY-MM-DD`.
- HTTP 401 means the token is missing, expired or revoked.
- HTTP 403 covers inactive accounts/schools, wrong role and ownership failures.
- HTTP 409 with `mobile_context_required` means the bootstrap token was used before selecting a school.
- HTTP 409 with `password_change_required` means a temporary password must be replaced first.
- HTTP 422 contains an `errors` validation map.
- Never accept a child ID as proof of access. The backend always verifies the `parent_student` relationship inside the selected tenant.
- Switching schools requires another `POST /context` call and replacement of the locally stored context token.
