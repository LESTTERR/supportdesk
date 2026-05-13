# Customer Support Ticketing System API

This backend matches the GROUP 3 required API surface while keeping the existing UI endpoints working.

## Required endpoints

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/users/profile`
- `PUT /api/users/profile`
- `POST /api/tickets`
- `GET /api/tickets`
- `GET /api/tickets/{ticket_id}`
- `PUT /api/tickets/{ticket_id}/status`
- `POST /api/tickets/{ticket_id}/reply`
- `GET /api/tickets/user/{user_id}`
- `GET /api/admin/tickets`
- `GET /api/reports/ticket-status`
- `GET /api/reports/response-time`

Apache/XAMPP routes these clean URLs through `repo/supportdesk/api/.htaccess` to `repo/supportdesk/api/router.php`.

## Authentication and roles

- Authentication uses PHP sessions.
- Public registration creates `customer` accounts.
- Customers create and view their own tickets.
- Agents and admins can view tickets and update ticket status.
- Admin-only reporting endpoints are under `/api/admin/*` and `/api/reports/*`.

## Encryption

Sensitive profile fields encrypted with AES-256-GCM:

- `phone`
- `address`

The database stores each encrypted value as three columns:

- `{field}_enc` for ciphertext
- `{field}_iv` for the random 12-byte IV
- `{field}_tag` for the GCM authentication tag

Encryption/decryption is centralized in `repo/supportdesk/helpers/crypto.php`.
The key is configured in `repo/supportdesk/config/security.php` and can be overridden with the `ENCRYPTION_KEY_HEX` environment variable. It must be a 32-byte key represented as 64 hex characters.

Passwords are never encrypted. They use `password_hash()` and `password_verify()`.
