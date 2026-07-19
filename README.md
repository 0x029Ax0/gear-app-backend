# Gear API

Laravel 13 API for managing personal gear inventories. It provides Sanctum
bearer authentication, system and user-owned categories, owner-scoped gear CRUD,
validated image uploads, and queued product imports from public product pages.

## Requirements

- PHP 8.3+ with `mbstring`, `fileinfo`, `gd`, and a PDO driver
- Composer 2
- Node.js 22+ and npm
- PostgreSQL for normal development/production (SQLite is convenient for tests)
- A queue worker and scheduler process in every non-local deployment

## Local setup

```bash
git clone <repository-url> gear-app-backend
cd gear-app-backend
composer install
cp .env.example .env
php artisan key:generate
# Set DB_* in .env, then:
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The bundled `composer setup` script performs the install, environment creation,
key generation, migration, and frontend build. Never commit `.env` or an
`APP_KEY`; use a secret manager for deployed environments.

## Configuration

`.env.example` documents the complete runtime surface. The important production
settings are:

- `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_KEY`.
- `DB_*` for PostgreSQL. The application also supports SQLite for tests.
- `FILESYSTEM_DISK`, `GEAR_IMAGES_DISK`, and `PRODUCT_IMPORT_IMAGE_DISK` for
  image storage.
- `QUEUE_CONNECTION=database` (the default) and the `DB_QUEUE_*` settings.
- `PRODUCT_IMPORT_*` limits for URL validation, fetch size, image size, rate
  limiting, pending jobs, redirects, and import expiry.

Images are stored through Laravel's filesystem abstraction. For a public local
disk, run `php artisan storage:link`; for object storage, configure the disk and
set `GEAR_IMAGES_DISK`/`PRODUCT_IMPORT_IMAGE_DISK` accordingly.

## Database, queues, and scheduler

Run migrations with `php artisan migrate --force`. The default database queue
requires the jobs migration and a long-running worker:

```bash
php artisan queue:work --tries=3 --timeout=120
```

Product imports are dispatched asynchronously. Poll `GET /api/v1/product-imports/{id}`
until its status is `completed` or `failed`. Run the Laravel scheduler every
minute; it removes expired import records hourly:

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

The `product-imports:cleanup` command can also be run manually. In containers,
run the web server, queue worker, and scheduler as separate supervised processes.

## API

The complete OpenAPI 3.0.3 contract is in [docs/openapi.yaml](docs/openapi.yaml).
All endpoints are under `/api/v1`. Register or log in to obtain a Sanctum bearer
token, then send `Authorization: Bearer <token>` for protected resources.

Main resources:

- `GET /api/v1/health`
- `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`
- `GET|POST /api/v1/categories`, `GET|PUT|DELETE /api/v1/categories/{category}`
- `GET|POST /api/v1/gear-items`, `GET|PUT|DELETE /api/v1/gear-items/{gear_item}`
- `POST|DELETE /api/v1/gear-items/{gearItem}/image`
- `GET|POST /api/v1/product-imports`, `GET|DELETE /api/v1/product-imports/{product_import}`

API errors use a stable `{message, code}` shape; validation errors additionally
include an `errors` map. User-owned resources are scoped to the authenticated
user, and foreign resources return `404` rather than leaking existence.

Product imports only accept public HTTP(S) URLs. DNS resolution, redirects,
response size, content type, and image downloads are bounded to reduce SSRF and
resource-exhaustion risk. Do not disable these limits in production without a
review of the threat model.

## Verification

```bash
vendor/bin/pint --test
php artisan test
npm run build
git diff --check
```

The GitHub Actions workflow runs the PHP test suite, OpenAPI YAML parsing,
frontend build, and Docker build/publish. It publishes immutable SHA and
`latest` images to GHCR. Configure the repository secret
`COOLIFY_WEBHOOK_URL` to trigger the Coolify deployment; without it, tests and
image publication still run and deployment is explicitly skipped.

## License and security

This project is released under the MIT license. Do not report security issues in
public tickets; contact the project maintainer privately with reproduction steps.
