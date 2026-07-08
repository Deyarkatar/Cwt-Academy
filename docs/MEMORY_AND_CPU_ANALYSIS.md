# MEMORY_AND_CPU_ANALYSIS.md

## High CPU Risk Areas

| Code | Why | Fix Status |
|------|-----|------------|
| `SecurityHeaders::buildCsp()` | String concatenation in loop every request | Memoized Vite origins per-request |
| `ForceHttps::ipInRange()` | Binary IP math every request | Acceptable — early return for most requests |
| `AuditLogger::redact()` | Recursive array traversal | Acceptable — small arrays |
| Password strength regex | 6 regex on every input | Identified — minor impact |
| Spline 3D runtime | Heavy WebGL/JS heap | Lazy loaded with IntersectionObserver |

## High RAM Risk Areas

| Code | Why | Fix Status |
|------|-----|------------|
| `CategoryController::index()->get()` | Loads ALL categories | Already paginated in API controller |
| `AuditLog::create()` with `toArray()` | Full model serialization | Identified — consider `only()` |
| `welcome.blade.php` inline CSS | 36KB per request | Production should build assets |
| Spline React component | Heavy 3D runtime | Lazy loaded, skipped on low-end devices |
| Student dashboard collection filter | Filtered after pagination | Fixed — SQL-level filtering |

## OPcache Configuration

```ini
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.jit=tracing
opcache.jit_buffer_size=128M
```

- **Impact:** ~30-50ms per request saved
- **Requirement:** Must restart PHP-FPM on deployment
