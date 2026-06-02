<h1 align="center">Contacto Médico — Backend API</h1>

<p align="center">
  REST API powering the admin panel and public website of <strong>Contacto Médico</strong>,
  a medical affiliation platform built on modern infrastructure — migrated from a legacy system.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"/>
  <img src="https://img.shields.io/badge/Laravel_Sanctum-4.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Sanctum"/>
  <img src="https://img.shields.io/badge/WhatsApp_Cloud_API-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="WhatsApp"/>
</p>

---

## Overview

This is the backend service for Contacto Médico — a platform that manages medical affiliates, appointments, doctors, counselors, and franchises. It exposes a JSON REST API consumed by both the internal admin panel and the public-facing website.

**Key highlights:**
- Token-based authentication via Laravel Sanctum
- Dual-password support (MD5 legacy + bcrypt) for transparent migration of existing users
- PDF carnet generation and WhatsApp delivery via Meta Cloud API
- Automated affiliate expiry via Laravel Scheduler
- Separate public endpoints that expose only safe, filtered data to the website

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Database | MySQL 8.0 |
| Authentication | Laravel Sanctum (token-based) |
| PDF Generation | FPDI + TCPDF |
| Notifications | WhatsApp Cloud API (Meta) |
| Testing | PHPUnit (SQLite in-memory) |

---

## Prerequisites

| Tool | Min. version |
|---|---|
| PHP | 8.2 |
| Composer | 2.x |
| MySQL | 8.0 |
| Node.js | 18 *(only if compiling assets via Vite)* |

---

## Local Installation

```bash
# 1. Clone the repo
git clone <repo-url>
cd api-cm

# 2. Install PHP dependencies
composer install

# 3. Set up environment variables
cp .env.example .env
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Create the storage symlink (required for public PDF URLs)
php artisan storage:link

# 6. Start the development server
php artisan serve
```

The API will be available at `http://localhost:8000`.

---

## Environment Variables

```env
APP_NAME="Contacto Médico API"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=contactomedico
DB_USERNAME=root
DB_PASSWORD=

# WhatsApp webhook verification token
WHATSAPP_WEBHOOK_TOKEN=
```

> **Note:** WhatsApp Cloud API credentials (`wa_bearer_token`, `wa_phone_number_id`, `wa_template_name`, etc.) are stored in the `settings` database table and managed through the admin panel — they do **not** go in `.env`.

---

## Running Tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database — your local MySQL is untouched.

---

## API Reference

All authenticated routes require the header `Authorization: Bearer <token>`.

### Authentication

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/login` | Login — returns Sanctum token |
| `POST` | `/api/logout` | Invalidate current token |

### Public Endpoints *(no auth)*

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/public/doctors` | Active doctors (safe fields only) |
| `GET` | `/api/public/specialties` | Active specialties |
| `GET` | `/api/public/departments` | All departments |
| `GET` | `/api/public/departments/{id}/cities` | Cities by department |
| `POST` | `/api/public/affiliate-request` | Submit affiliation request |
| `POST` | `/api/public/contact` | Submit contact message |
| `GET` | `/api/public/content-allies` | Strategic allies (website content) |
| `GET` | `/api/public/content-specialists` | Specialists (website content) |

### Affiliates

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/affiliates` | Paginated list with filters (`stade`, `search`) |
| `POST` | `/api/affiliates` | Create affiliate |
| `GET` | `/api/affiliates/{id}` | Get affiliate |
| `PUT` | `/api/affiliates/{id}` | Update affiliate |
| `DELETE` | `/api/affiliates/{id}` | Delete affiliate |
| `GET` | `/api/affiliates/expiring-today` | Affiliates expiring today (dashboard alert) |
| `GET` | `/api/affiliates/check-id-card` | Check if ID card already exists |
| `GET` | `/api/affiliates/by-id-card/{idCard}` | Find affiliate by ID card number |
| `POST` | `/api/affiliates/{id}/carnet` | Generate PDF carnet & send via WhatsApp |
| `GET` | `/api/affiliates/{id}/notes` | Affiliate notes |
| `POST` | `/api/affiliates/{id}/notes` | Add note to affiliate |

### Appointments

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/appointments` | Paginated list with filters (`period`, `search`) |
| `POST` | `/api/appointments` | Create appointment + send WA notification |
| `GET` | `/api/appointments/{id}` | Get appointment |
| `PUT` | `/api/appointments/{id}` | Update appointment + send WA notification |
| `DELETE` | `/api/appointments/{id}` | Delete appointment |
| `GET` | `/api/appointments/today` | Today's appointments (dashboard widget) |

### Doctors & Specialties

| Method | Endpoint | Description |
|---|---|---|
| `GET/POST` | `/api/doctors` | List / create doctors |
| `GET/PUT/DELETE` | `/api/doctors/{id}` | Read / update / delete doctor |
| `GET` | `/api/doctors/by-specialty/{id}` | Doctors filtered by specialty |
| `GET/POST` | `/api/specialties` | List / create specialties |
| `GET/PUT/DELETE` | `/api/specialties/{id}` | Read / update / delete specialty |

### Users, Counselors & Franchises

| Method | Endpoint | Description |
|---|---|---|
| `GET/POST` | `/api/users` | List / create users (franchises) |
| `GET/PUT/DELETE` | `/api/users/{id}` | Read / update / delete user |
| `GET` | `/api/users/active-franchises` | Active franchises (for dropdowns) |
| `POST` | `/api/users/{id}/change-password` | Change user password |
| `GET/POST` | `/api/counselors` | List / create counselors |
| `GET` | `/api/counselors/active` | Active counselors (for dropdowns) |

### Renovations & Beneficiaries

| Method | Endpoint | Description |
|---|---|---|
| `GET/POST` | `/api/renovations` | List / create renovations |
| `GET/PUT/DELETE` | `/api/renovations/{id}` | Read / update / delete renovation |
| `GET/POST` | `/api/beneficiaries` | List / create beneficiaries |
| `GET/PUT/DELETE` | `/api/beneficiaries/{id}` | Read / update / delete beneficiary |

### Admin & Configuration

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/dashboard/stats` | Global metrics (super admin only) |
| `GET` | `/api/dashboard/charts` | Monthly charts data |
| `GET` | `/api/settings` | Get WhatsApp / system settings |
| `PUT` | `/api/settings/{id}` | Update settings |
| `GET` | `/api/membership-forms` | Pending affiliation requests |
| `PATCH` | `/api/membership-forms/{id}/convert` | Mark request as converted |
| `GET` | `/api/contacts` | Contact form messages |
| `GET/POST/PUT/DELETE` | `/api/content-allies` | Website allies content (CRUD + reorder) |
| `GET/POST/PUT/DELETE` | `/api/content-specialists` | Website specialists content (CRUD + reorder) |

---

## Folder Structure

```
app/
├── Auth/
│   └── Md5UserProvider.php     # Custom provider: MD5 legacy + bcrypt support
├── Console/Commands/
│   └── UpdateExpiredAffiliates.php  # Scheduler: marks expired affiliates inactive
├── Http/Controllers/           # One controller per resource / feature
├── Models/                     # Eloquent models
├── Providers/
│   └── AppServiceProvider.php  # Registers Md5UserProvider
config/
├── auth.php                    # Uses 'md5-eloquent' driver
├── cors.php                    # CORS — reads from FRONTEND_URL
database/
├── migrations/                 # 44 migrations covering all modules
└── seeders/                    # Settings seed (WhatsApp config row)
resources/
└── pdf/
    └── carnet.pdf              # Base template for affiliate carnet generation
routes/
└── api.php                     # All routes: public group + auth:sanctum group
storage/app/public/
└── carnets/                    # Generated PDF carnets (served publicly)
tests/
├── Feature/                    # Feature tests per module
└── Unit/                       # Unit tests
```

---

## Production Deployment

### 1. Environment variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.contactomedico.net
FRONTEND_URL=https://contactomedico.net
DB_HOST=<db_host>
DB_DATABASE=<db_name>
DB_USERNAME=<db_user>
DB_PASSWORD=<db_password>
WHATSAPP_WEBHOOK_TOKEN=<your_token>
```

### 2. Deploy commands

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Scheduler cron job

Add to the server's crontab (cPanel, SSH, etc.):

```
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

This triggers `affiliates:update-expired` daily at 00:05, automatically deactivating affiliates whose `validity_end` has passed.

### 4. WhatsApp Webhook (Meta for Developers)

In [Meta for Developers](https://developers.facebook.com) → your app → WhatsApp → Configuration → Webhooks:

- **Callback URL:** `https://api.contactomedico.net/api/webhook/whatsapp`
- **Verify Token:** *(value of `WHATSAPP_WEBHOOK_TOKEN`)*
- **Subscribed field:** `messages`

---

## Notes

- **Dual-password auth:** The custom `Md5UserProvider` supports both MD5 hashes (legacy users) and bcrypt (new/updated users). Migration happens transparently as each user changes their password — do **not** change `auth.php` back to the default `eloquent` driver.
- **`storage:link`** must be run once on the server. Without it, Meta cannot download PDF carnets from their public URL.
- **CORS:** The allowed origin is read from `FRONTEND_URL`. If the frontend domain changes, update that variable and run `php artisan config:clear`.
- **WhatsApp template language code:** Always use `es_CO` (not `es`) — Spanish (COL) in Meta Business Suite. Using `es` causes error `#132001`.
