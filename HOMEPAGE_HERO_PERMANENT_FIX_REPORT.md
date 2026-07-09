# Homepage Hero Robot — Permanent Fix Report

## 1. Why the robot disappeared

The shared hero robot component (`resources/views/components/home-hero-robot.blade.php`) was rendering an empty robot stage with no real image:

```blade
<div class="hero-robot relative ..." data-testid="homepage-hero-robot">
    <div class="hero-robot-stage absolute inset-0 ..."></div>
</div>
```

The React/Spline app in `resources/js/spline-app.tsx` then replaced the entire SSR hero card on mount. Its `HeroFallback` component returned `null`, so while the Spline scene was loading (or if it failed), the robot area was blank. Once React hydrated, the original SSR markup — including any static image — was gone, and only the Spline canvas remained. If Spline was slow, blocked, or errored, the hero showed a dark/empty robot area.

## 2. Exact file that caused it

`resources/views/components/home-hero-robot.blade.php` was empty (no `<img>`).

`resources/js/spline-app.tsx` compounded the problem by removing the SSR markup and supplying a `null` fallback, leaving nothing visible when the 3D runtime was unavailable.

## 3. Real robot asset path now used

- File on disk: `public/images/hero-robot.png`
- Blade component uses: `{{ asset('images/hero-robot.png') }}`
- React component uses: `/images/hero-robot.png`

The asset is a 720×800 PNG of the glossy black robot (previously named `hero-robot-fallback.png`). It was copied to the canonical path `public/images/hero-robot.png`.

## 4. `curl -I` result proving image returns 200

```text
$ curl -I http://localhost:8000/images/hero-robot.png
HTTP/1.1 200 OK
Host: localhost:8000
Content-Type: image/png
Content-Length: 171716
```

## 5. English browser verification

- URL: `http://localhost:8000` (locale set to `en`)
- Text column: `dir="ltr"`, `lg:order-1` (left)
- Robot column: `lg:order-2` (right)
- Robot element contains:

```html
<img src="http://localhost:8000/images/hero-robot.png"
     data-testid="homepage-hero-robot-image"
     class="w-full h-full object-contain" ... />
```

## 6. Kurdish browser verification

- URL: `http://localhost:8000` after visiting `/locale/ku`
- HTML is `lang="ku" dir="rtl"`
- Robot column: `lg:order-1` (left)
- Text column: `dir="rtl"`, `lg:order-2` (right)
- Same real robot image element is present.

## 7. Tests added / updated

Updated `tests/Feature/HomepageHeroRegressionTest.php` with permanent regression checks:

- English homepage: 200, contains "Courses for Kurdistan", "delivered through Telegram", "Browse Courses", "Contact", `data-testid="homepage-hero-robot"`, `images/hero-robot`, `<img`; does NOT contain `cwt_academy-logo.jpg`, `hero-robot.svg`, `fake-svg-robot`, or "Cwt Academy Robot".
- Kurdish homepage: 200, contains Kurdish headline and Telegram text, `data-testid="homepage-hero-robot"`, `images/hero-robot`, `<img`; rejects the same bad assets.
- DOM order assertions: English text before robot; Kurdish robot before text.
- Layout assertions: correct `lg:order-*` classes for both locales.
- Robot asset test: `public/images/hero-robot.png` exists, is non-empty, has an image MIME type, and the asset URL resolves to `/images/hero-robot.png`.

## 8. Commands run and results

```bash
php artisan optimize:clear       # OK
npm run build                    # OK
php artisan test                 # 244 passed (928 assertions)
./vendor/bin/pint --test         # PASS
./vendor/bin/phpstan analyse     # [OK] No errors

# Browser / asset smoke tests
curl -s http://localhost:8000/ | grep homepage-hero-robot-image  # found
curl -I http://localhost:8000/images/hero-robot.png              # HTTP/1.1 200 OK, image/png
```

## 9. What changed

- `public/images/hero-robot.png` — new canonical real robot asset.
- `resources/views/components/home-hero-robot.blade.php` — now renders a real `<img>` with the robot asset.
- `resources/js/spline-app.tsx` — `HeroFallback` now re-renders the captured SSR hero HTML instead of `null`, so the robot image and text survive Spline load failures.
- `resources/js/components/ui/spline-demo.tsx` — robot column now includes the real `<img>` behind the Spline canvas; added `data-testid` markers for text and robot columns.
- `tests/Feature/HomepageHeroRegressionTest.php` — strengthened regression assertions.
- Build artifacts regenerated under `public/build/assets/`.

## 10. Why this will not regress

1. The Blade component always emits a real `<img>` on the server — no JS required.
2. The React mount captures that SSR markup and uses it as its own loading/error fallback, so if Spline never loads the robot image is still there.
3. The interactive React card also embeds the same image behind the Spline canvas, so even after hydration the static robot remains as a fallback layer.
4. Regression tests assert the image element, the asset path, and the absence of logo/fake SVG/broken alt text in both locales.
