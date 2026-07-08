# FRONTEND_PERFORMANCE.md

## Issues Identified and Fixed

### 1. Spline 3D Scene Not Lazy-Loaded (FIXED)
- **Severity:** Critical
- **File:** `resources/js/components/ui/spline-demo.tsx`
- **Fix:** Wrapped `SplineScene` in `React.lazy()` + `Suspense`. Added `IntersectionObserver` with 200px rootMargin. Added low-end device detection (`navigator.deviceMemory < 4`). Added mobile + `prefers-reduced-motion` bypass.
- **Impact:** Homepage initial bundle reduced. 3D scene only loads when hero scrolls into view.

### 2. Multiple Blocking Google Fonts (FIXED)
- **Severity:** Critical
- **File:** `resources/views/layouts/app.blade.php`
- **Fix:** Consolidated 3 separate `<link>` tags into 1 with `preload as="style"` and `display=swap`
- **Impact:** -2 HTTP requests, -300-800ms first paint on slow connections

### 3. Inline CSS Fallback Bloat (IDENTIFIED)
- **Severity:** Medium
- **File:** `resources/views/welcome.blade.php`
- **Note:** 36KB inline CSS fallback only triggers when Vite is not built. Production deployments must run `npm run build`.

### 4. No Image Lazy Loading (FIXED)
- **Severity:** Medium
- **File:** `resources/views/components/course-card.blade.php`
- **Fix:** Added `loading="lazy"` and `decoding="async"` to course card images
- **Impact:** Images below the fold no longer block initial render

### 5. Password Strength Meter Polling (IDENTIFIED)
- **Severity:** Low
- **File:** `resources/js/app.js`
- **Note:** `setInterval` polls DOM 10 times for autofill detection. Recommended: use `animationstart` event detection instead.

### 6. No Service Worker (IDENTIFIED)
- **Severity:** Medium
- **Status:** Not implemented — recommendation: add Workbox for asset caching

### 7. Vite Bundle Analysis (IDENTIFIED)
- **Severity:** Medium
- **Status:** No `rollup-plugin-visualizer` configured. Recommendation: add bundle analysis to CI.

## Recommended Optimizations

1. Add `dns-prefetch` for external domains (Cloudflare Turnstile, Spline CDN)
2. Implement resource hints (`prefetch` for catalog page, `preconnect` for API)
3. Add `loading="lazy"` to all non-hero images
4. Consider critical CSS inlining for above-the-fold content
