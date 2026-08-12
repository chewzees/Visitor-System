# Visitor. — Management System

Editorial-styled visitor management for XAMPP (PHP + MySQL).

## Setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Open [http://localhost/Visitor/install.php](http://localhost/Visitor/install.php) (fresh DB) — or just open the app; schema upgrades run automatically.
3. Sign in: **admin** / **password**
4. Delete `install.php` after install.
5. For production: set `APP_DEBUG` to `false` and change `APP_SECRET` in `config/config.php`.

## Default users

| Username   | Password | Role     |
|------------|----------|----------|
| admin      | password | Admin    |
| security1  | password | Security |
| staff1     | password | Staff    |

## Screenshots

### Public pages

#### Login
![Login](docs/screenshots/01-login.png)

#### Forgot password
![Forgot password](docs/screenshots/02-forgot-password.png)

#### User manual
![User manual](docs/screenshots/03-manual.png)

#### Visitor check-in (scan entrance QR)
![Visitor check-in](docs/screenshots/04-checkin.png)

#### Visitor check-in form
![Check-in form](docs/screenshots/05-checkin-form.png)

### Admin / staff pages

#### Dashboard
![Dashboard](docs/screenshots/06-dashboard.png)

#### Visitors list
![Visitors](docs/screenshots/07-visitors.png)

#### Add visitor
![Add visitor](docs/screenshots/08-visitor-form.png)

#### Visitor detail
![Visitor detail](docs/screenshots/09-visitor-view.png)

#### Visitor badge
![Badge](docs/screenshots/10-badge.png)

#### Scan QR
![Scan QR](docs/screenshots/11-scan.png)

#### Entrance QR
![Entrance QR](docs/screenshots/12-entrance-qr.png)

#### Blacklist
![Blacklist](docs/screenshots/13-blacklist.png)

#### Activity log
![Activity log](docs/screenshots/14-activity-log.png)

#### Users
![Users](docs/screenshots/15-users.png)

#### Settings
![Settings](docs/screenshots/16-settings.png)

> Screenshots can be regenerated locally with: `python docs/capture_screenshots.py` (requires Selenium + Chrome).

## Features

- Dashboard metrics (clickable), pending approvals, overdue “still inside”
- Visitors search/filters, approve / check-in / check-out, photos
- Host email notifications (register / check-in / check-out)
- Badge print sheet with signed QR (7-day signature)
- Scan QR + upload image + kiosk mode
- Entrance QR → public check-in (with optional selfie)
- Blacklist enforcement
- Activity log + CSV export
- Users & roles (Staff only sees own hosted visitors)
- Forgot / reset password
- Settings + change password
- EN / 中文 language switch
- User manual

## Config

Edit `config/config.php` for DB, mail, overdue hours, and notification toggles.
