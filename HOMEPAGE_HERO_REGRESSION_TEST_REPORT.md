# Homepage Hero Regression Test Report

## Summary

This change adds a comprehensive regression test suite around the homepage hero so the previously fixed layout, robot visual, and CSP/asset issues cannot silently regress.

**No hero UI was redesigned.** Only minimal stable `data-testid` / `data-locale` attributes were added to make tests resilient.

## Tests added

### 1. `tests/Feature/HomepageHeroRegressionTest.php`

HTML-level regression tests for both English and Kurdish homepages.

English (`/?lang=en`):
- Returns 200.
- Contains headline "Courses for Kurdistan" and "delivered through Telegram".
- Contains CTAs "Browse Courses" and "Contact".
- Contains the robot component (`[data-testid="homepage-hero-robot"]`).
- Does **not** contain Kurdish headline text.
- Does **not** contain fake SVG robot markers, `cwt_academy-logo.jpg`, `cwt-academy-robot.jpg`, broken placeholder markup, or visible alt text "Cwt Academy Robot".
- Text markup appears before robot markup in the DOM and uses `lg:order-1` for text / `lg:order-2` for robot.

Kurdish (`/?lang=ku`):
- Returns 200.
- Contains headline "کۆرسەکان بۆ کوردستان" and "تێلیگرامەوە".
- Contains Kurdish CTA buttons.
- Contains the robot component.
- Does **not** contain English headline text.
- Does **not** contain fake SVG robot, CWT shield logo, or broken image alt text.
- Robot markup appears before text markup in the DOM and uses `lg:order-1` for robot / `lg:order-2` for text.

Both locales:
- Hero wrapper has correct `data-locale` attribute.
- Hero is never blank after the navbar.
- No spinner/loader-only robot area.

### 2. `tests/Feature/HomepageHeroAssetTest.php`

Asset regression tests.
- Confirms the old fake SVG robot file is gone (`public/images/hero-robot.svg` does not exist and returns 404).
- Confirms the built app CSS exists and is non-empty.
- Confirms the built `spline-app-*.js` and `spline-demo-*.js` assets exist and are non-empty.
- Confirms the built Spline demo JS references the real glossy black robot scene URL.
- Confirms the Spline scene URL is reachable over the network.

> Note: the real robot is a Spline 3D scene, not a static image, so there is no `/images/*.png|jpg|webp` robot file. The asset tests guard the actual robot asset paths used by the hero component.

### 3. `tests/Feature/HomepageHeroCspTest.php`

CSP regression tests.
- Confirms the homepage response includes a `Content-Security-Policy` header.
- Confirms the local-dev CSP allows `localhost:8000`, `127.0.0.1:8000`, `localhost:5173`, and `127.0.0.1:5173` so mixed `localhost` / `127.0.0.1` origins do not block CSS/JS.
- Confirms the built CSS asset exists for the `style-src 'self'` origin.
- Confirms the CSP allows Spline robot CDN origins (`prod.spline.design`, `unpkg.com`, `gstatic.com`) and `wasm-unsafe-eval`.

### 4. Playwright plan

Playwright is **not installed** in this project, so no dependency was added. A documented optional spec is provided in `docs/PLAYWRIGHT_HERO_REGRESSION_TEST_PLAN.md` with English and Kurdish visual bounding-box assertions, CSP/asset error checks, and broken-image checks.

## Bugs covered

The new tests protect against all of the previously reported hero failures:

1. CSS/JS blocked by CSP due to mixed `localhost` / `127.0.0.1` origins.
2. Homepage becoming white/unstyled (built CSS asset existence + CSP tests).
3. Hero becoming blank after navbar (hero wrapper/text/robot markup presence tests).
4. Robot disappearing (robot component presence + Spline asset existence + scene URL reachable).
5. English robot disappearing (English robot order + component presence).
6. Kurdish robot disappearing (Kurdish robot order + component presence).
7. Fake yellow SVG/cartoon robot returning (`hero-robot.svg` 404 + string-not-contains checks).
8. CWT shield/logo used by mistake (`cwt_academy-logo.jpg` and `cwt-academy-robot.jpg` checks).
9. Kurdish layout becoming wrong (Kurdish robot before text + order classes).
10. English layout becoming wrong (English text before robot + order classes).
11. Broken image alt text "Cwt Academy Robot" (string-not-contains + no `<img` inside hero).
12. Placeholder/spinner-only robot area (no `animate-spin`, `loader`, or "Loading 3D scene" inside hero).
13. English and Kurdish text mixed incorrectly (per-locale headline exclusion checks).

## Files changed

- `resources/views/public/home.blade.php` — added `data-testid="homepage-hero"`, `data-locale="..."`, `data-testid="homepage-hero-text"`.
- `resources/views/components/home-hero-robot.blade.php` — added `data-testid="homepage-hero-robot"`.
- `app/Http/Middleware/SetLocale.php` — added optional `?lang=en|ku` query-string override so tests can request `/` directly with a locale (no session/cookie setup needed).
- `tests/Feature/HomepageHeroRegressionTest.php` — new.
- `tests/Feature/HomepageHeroAssetTest.php` — new.
- `tests/Feature/HomepageHeroCspTest.php` — new.
- `tests/Feature/HomePageDiagnosticTest.php` — updated regexes to tolerate the new `data-testid` attributes.
- `docs/PLAYWRIGHT_HERO_REGRESSION_TEST_PLAN.md` — new optional E2E plan.
- `HOMEPAGE_HERO_REGRESSION_TEST_REPORT.md` — this report.

## Commands run

```bash
php artisan optimize:clear
npm run build
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

## Results

- `npm run build` — success.
- `php artisan test` — **238 passed, 867 assertions**.
- `./vendor/bin/pint --test` — **PASS**.
- `./vendor/bin/phpstan analyse` — **No errors**.

## How to run later

```bash
# Run only the new hero regression tests
php artisan test tests/Feature/HomepageHeroRegressionTest.php
php artisan test tests/Feature/HomepageHeroAssetTest.php
php artisan test tests/Feature/HomepageHeroCspTest.php

# Run full suite with linting/static analysis
php artisan optimize:clear
npm run build
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

If Playwright is added later:

```bash
npm run test:e2e   # after adding the script and tests/e2e/homepage-hero.spec.ts
```

## Confirmation: hero UI unchanged

No hero styling, layout, copy, or robot source was changed. The only markup additions are the minimal test attributes listed above. English still shows text on the left and the real Spline robot on the right; Kurdish still shows the real Spline robot on the left and text on the right.
