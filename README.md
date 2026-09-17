# FieldOps API (`server/`)

Laravel REST backend for the office SPA and both React Native apps.

- Routes: `routes/api.php` (prefix `/api/v1`)
- HTTP: `app/Http/Api/V1/*`
- Use-cases: `app/Application/*`
- Models: `app/Domain/*`
- Docs: [../docs/CODE.md](../docs/CODE.md) · [../docs/API.md](../docs/API.md)

## Layout

```
app/
  Http/Api/V1/          Thin controllers (validate, authorize, call a use-case)
  Http/Middleware/      org = SetCurrentOrganization, plan = EnsurePlanLimit
  Http/Resources/       UserResource, JobResource, InvoiceResource
  Application/          CreateJob, AssignJob, TransitionJobStatus, invoicing, billing
  Domain/               Eloquent models + JobStatusMachine
  Policies/             Permission checks (UI only hides)
  Enums/                JobStatus, UserRole, VerificationStatus, ApprovalStatus
  Support/              BelongsToOrganization, HasPublicUuid
database/seeders/       Demo org Mustermann SHK + ContentPageSeeder
tests/Feature/          Pest + RefreshDatabase (sqlite :memory:)
```

## Run

```powershell
cd server
copy .env.example .env   # if needed
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

PostgreSQL: set `DB_CONNECTION=pgsql`, `DB_SSLMODE=disable` on Windows if SSL handshake hangs. Pest always uses sqlite in-memory (`phpunit.xml`).

```powershell
php artisan test
```

## Conventions

- Controllers do not query across tenants. Models use `BelongsToOrganization`.
- Route keys: `{job}`, `{customer}`, `{invoice}`, `{member}`, `{notification}` bind on `public_id`; `{page}` binds on content `slug`.
- Domain rules throw `DomainException` → HTTP 422 `{ code: "domain" }`.
- German user-facing messages.

## Seeded logins

Super Admin `admin@admin.com` / `12345678`. Everyone else `FieldOps!2026` (see root README).
