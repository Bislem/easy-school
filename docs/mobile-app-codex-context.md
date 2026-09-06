# Easy School mobile app — Codex context

Read [`api-docs/mobile-api.md`](../api-docs/mobile-api.md) as the authoritative API contract.

## Runtime contract

- Base URL: the Easy School backend origin plus `/api/mobile/v1`.
- API version: `v1`.
- JSON header: `Accept: application/json`.
- Authentication: Laravel Sanctum bearer token issued by `POST /login`; token lifetime is 90 days and logout revokes the current token.
- Login needs the parent email, password and a local device name. It returns the schools available to that parent account.
- Role: `parent` only. The mobile app must not expose student-role navigation or call `/student/*` routes.

## Tenant and permissions

The backend resolves the tenant during login and derives it from the authenticated token owner thereafter. Every tenant model is globally scoped by `TenantContext`. Never send a tenant ID with content requests.

Parents may access only students linked to their `SchoolParent` record. Expect 403 for an unlinked same-school resource and 404 when tenant scoping hides a record.

## Response handling

- Single/list resources live under `data`.
- Paginated responses add `links` and `meta`; request more pages with `?page=N`.
- Validation failures are 422 with an `errors` map.
- 401 means the bearer token is missing, expired or revoked.
- 403 may include `role_not_allowed`, `forbidden_role`, `account_inactive` or `tenant_unavailable`.
- Dates/times are ISO 8601; display in the device timezone.

## Feature groups

- Parent: children, child folder, formations, planning, attendance, observations, certificates, documents and finance.
- Shared parent services: school contact card, notifications, announcement/event-filtered notifications, ticket-based conversations and FCM devices.
- Grades endpoints deliberately return `meta.supported=false`: the backend has no grades model yet.
- Events/announcements are notification types, not a separate event entity.

## Push flow

1. Obtain notification permission and an FCM token from the native app.
2. `POST /devices` with token, `ios|android`, and device name.
3. Store the returned device ID.
4. Refresh registration whenever FCM rotates the token.
5. On full sign-out, delete the device, then call `/logout`.

## Messaging flow

“Conversations” map to backend support tickets. List `/conversations`, create a thread with subject/message, open `/{ticket}/messages` (which marks school messages read), and post replies to the same URL. Closed threads reject replies with 422. Use `/conversations/unread-count` for badges.

Do not invent endpoint fields or parse unrelated portal HTML. Follow `api-docs/mobile-api.md` exactly and regenerate client DTOs when that document changes.
