# PATCHES_APPLIED.md

## 2026-05-26 — Full Performance + Security + Scalability Refactor

### Controllers Created (12)
1. `app/Http/Controllers/Web/HomeController.php`
2. `app/Http/Controllers/Web/CatalogController.php`
3. `app/Http/Controllers/Web/CourseDetailController.php`
4. `app/Http/Controllers/Web/CourseRequestFormController.php`
5. `app/Http/Controllers/Web/TrackingController.php`
6. `app/Http/Controllers/Web/ContactController.php`
7. `app/Http/Controllers/Web/DashboardController.php`
8. `app/Http/Controllers/Web/AdminDashboardController.php`
9. `app/Http/Controllers/Web/AdminRequestController.php`
10. `app/Http/Controllers/Web/AdminTelegramAccessController.php`
11. `app/Http/Controllers/Web/AuthWebController.php`
12. `app/Http/Controllers/Web/LocaleController.php`
13. `app/Http/Controllers/Web/ProfileController.php`
14. `app/Http/Controllers/Web/PendingVerificationController.php`
15. `app/Http/Controllers/Web/AdminPaymentProofDownloadController.php`
16. `app/Http/Controllers/Api/AdminDashboardController.php`

### Migrations Created (1)
1. `database/migrations/2026_05_26_000007_add_missing_production_indexes.php` — 11 indexes

### Docker Files Created (5)
1. `docker/php/Dockerfile`
2. `docker/php/opcache.ini`
3. `docker/php/php.ini`
4. `docker/php/www.conf`
5. `docker/nginx/nginx.conf`

### Files Modified
- `routes/web.php` — 0 closures
- `routes/api.php` — 0 closures
- `.env.example` — Redis defaults
- `config/cache.php` — Redis default
- `config/session.php` — Redis default
- `config/queue.php` — Redis default
- `docker-compose.yml` — Full production stack
- `app/Http/Controllers/HealthCheckController.php` — No file I/O
- `app/Http/Controllers/Admin/PaymentProofController.php` — N+1 fix
- `resources/views/layouts/app.blade.php` — Consolidated fonts
- `resources/js/components/ui/spline-demo.tsx` — Lazy loading
- `resources/views/components/course-card.blade.php` — Lazy images
