# Assignment #2 - Secure Authentication System

This implementation is isolated in `auth2_*` files so it does not break existing project logic.

## 1) Setup

1. Configure DB connection (optional via environment):
   - `AUTH2_DB_HOST`
   - `AUTH2_DB_USER`
   - `AUTH2_DB_PASS`
   - `AUTH2_DB_NAME`
   - `AUTH2_DB_PORT`
2. Run table setup once:
   - Open: `http://localhost/System/setup_auth2_tables.php`
3. Change JWT secret in `auth2_lib.php`:
   - `AUTH2_JWT_SECRET`

## 2) Required pages/APIs

- Register page: `auth2_register.html`
- Login page: `auth2_login.html`
- 2FA verification page: `auth2_verify_2fa.html`
- Dashboard page: `auth2_dashboard.php`
- Profile page: `auth2_profile.php`
- Admin page: `auth2_admin.php`
- Manager page: `auth2_manager.php`
- User page: `auth2_user.php`

Core APIs:
- `auth2_register_api.php`
- `auth2_login_api.php`
- `auth2_verify_2fa_api.php`
- `auth2_protected_api.php`

## 3) Security features implemented

- Password hashing using `password_hash()` + `password_verify()`
- 2FA secret generation per user
- QR code generation for authenticator apps
- TOTP 6-digit code verification
- JWT generation only after password + 2FA success
- Bearer token / cookie auth for protected routes
- RBAC for exactly 3 roles: `Admin`, `Manager`, `User`

## 4) Required flow mapping

1. Register user (`auth2_register.html` -> `auth2_register_api.php`)
2. Password hashed and stored in DB (`auth2_users.password_hash`)
3. 2FA secret generated (`auth2_users.twofa_secret`)
4. QR code displayed in registration response
5. User scans QR in Google/Microsoft Authenticator
6. Login with email + password (`auth2_login.html`)
7. Enter 2FA code (`auth2_verify_2fa.html`)
8. System verifies password + 2FA
9. JWT returned by `auth2_verify_2fa_api.php`
10. Access protected routes with token
11. Access is role-restricted

## 5) Demo checklist (for section)

1. Register 3 users with roles: Admin, Manager, User
2. Open database and show `password_hash` is not plain text
3. Show QR code after register
4. Perform login then 2FA
5. Show generated JWT token in response
6. Use token:
   - `auth2_protected_api.php?route=dashboard`
   - `auth2_protected_api.php?route=admin`
   - `auth2_protected_api.php?route=manager`
   - `auth2_protected_api.php?route=user`
7. Show unauthorized access blocked (403/401)

