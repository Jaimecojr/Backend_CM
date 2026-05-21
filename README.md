# Contacto Médico — API Backend

API REST construida en **Laravel 12** que alimenta el panel administrativo y el sitio web público de Contacto Médico.

---

## Requisitos previos

| Herramienta | Versión mínima |
|-------------|----------------|
| PHP         | 8.2            |
| Composer    | 2.x            |
| MySQL       | 8.0            |
| Node.js     | 18 (para compilar assets con Vite) |

---

## Instalación local

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Copiar y configurar variables de entorno
cp .env.example .env
php artisan key:generate
```

### Variables de entorno esenciales (`.env`)

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=contactomedico
DB_USERNAME=root
DB_PASSWORD=

# WhatsApp Cloud API (se configura desde el panel → Settings)
# Los valores se guardan en la tabla `settings`, no aquí,
# pero el token del webhook sí va en .env:
WHATSAPP_WEBHOOK_TOKEN=contactomedico_webhook_2026
```

```bash
# 3. Ejecutar migraciones
php artisan migrate

# 4. Enlace de almacenamiento público (necesario para PDFs de carnets)
php artisan storage:link

# 5. Iniciar el servidor de desarrollo
php artisan serve
```

La API queda disponible en `http://localhost:8000`.

---

## Ejecutar los tests

```bash
php artisan test
```

Los tests usan SQLite en memoria — no tocan la base de datos MySQL local.

---

## Estructura relevante

```
app/Http/Controllers/   → Controladores de la API
app/Models/             → Modelos Eloquent
database/migrations/    → Migraciones de la BD
resources/pdf/          → Plantilla carnet.pdf (base para envío por WA)
routes/api.php          → Definición de rutas
storage/app/public/     → Archivos públicos generados (carnets PDF)
```

---

## Configuración de producción

### 1. Variables de entorno en el servidor

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.contactomedico.net

FRONTEND_URL=https://contactomedico.net

DB_CONNECTION=mysql
DB_HOST=<host_mysql>
DB_DATABASE=<nombre_bd>
DB_USERNAME=<usuario>
DB_PASSWORD=<contraseña>

WHATSAPP_WEBHOOK_TOKEN=contactomedico_webhook_2026
```

> Los parámetros de WhatsApp Cloud API (`wa_bearer_token`, `wa_phone_number_id`, etc.) se gestionan desde el panel **Settings** y se almacenan en la tabla `settings` — no van en `.env`.

### 2. Despliegue inicial

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Cron job del scheduler

Agregar en el panel del servidor (cPanel, SSH, etc.):

```
* * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1
```

Esto ejecuta el comando `affiliates:update-expired` que marca como inactivos los afiliados con fecha de vencimiento pasada.

### 4. Webhook de WhatsApp (Meta for Developers)

Una vez desplegado, actualizar la URL del webhook en [Meta for Developers](https://developers.facebook.com) → tu app → WhatsApp → Configuration → Webhooks:

- **Callback URL:** `https://api.contactomedico.net/api/webhook/whatsapp`
- **Verify Token:** `contactomedico_webhook_2026` (igual al valor de `WHATSAPP_WEBHOOK_TOKEN`)
- **Campo suscrito:** `messages`

---

## Notas adicionales

- **`storage:link`** debe ejecutarse una sola vez en el servidor. Sin este enlace la URL pública de los PDFs de carnets no es accesible y Meta no puede descargar el documento.
- **`config:cache`** requiere que **todas** las variables de entorno estén configuradas antes de correrlo. Si alguna variable cambia después, ejecutar `php artisan config:clear` y volver a cachear.
- **CORS:** El origen permitido se lee de `FRONTEND_URL`. Si el dominio del frontend cambia, actualizar esa variable y limpiar la caché de configuración.
