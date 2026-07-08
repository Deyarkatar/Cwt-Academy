"""
API rate-limit abuse / DoS-resilience tester.
Measures:
- Concurrent request handling
- Token-bucket / leaky-bucket enforcement
- Retry-After header compliance
"""
from __future__ import annotations

import asyncio
import time
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class RateLimitTester(BaseModule):
    name = "rate"
    description = "API rate-limit & abuse resilience testing"
    supported_vectors = ["rate_limit_abuse"]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/api/v1/", "/api/", target])
        concurrency = kwargs.get("concurrency", 50)

        for endpoint in endpoints:
            url = endpoint if endpoint.startswith("http") else target.rstrip("/") + endpoint
            await self._burst_test(url, concurrency)
            await self._retry_after_test(url)
        return self.findings

    async def _burst_test(self, url: str, concurrency: int) -> None:
        """Fire N concurrent GETs and analyze status codes / response times."""
        if self.config.safety.skip_destructive and concurrency > 20:
            self.logger.info("Reducing burst concurrency in safe_mode.")
            concurrency = 20

        sem = asyncio.Semaphore(concurrency)
        results: list[tuple[int, float]] = []

        async def _req() -> None:
            async with sem:
                t0 = time.monotonic()
                try:
                    resp = await self.client.get(url)
                    t1 = time.monotonic()
                    results.append((resp.status_code, t1 - t0))
                except Exception:
                    t1 = time.monotonic()
                    results.append((0, t1 - t0))

        await asyncio.gather(*(_req() for _ in range(concurrency)))

        success_codes = [r[0] for r in results if 200 <= r[0] < 300]
        rate_limited = [r[0] for r in results if r[0] in (429, 503)]
        errors = [r[0] for r in results if r[0] >= 500]
        avg_time = sum(r[1] for r in results) / len(results) if results else 0

        if len(rate_limited) < concurrency * 0.3 and len(errors) < concurrency * 0.1:
            self.add_finding(
                vector="rate_limit_abuse",
                severity="medium",
                title="API endpoint appears to lack strict rate limiting",
                description=(
                    f"Burst test: {concurrency} concurrent requests to {url}.\n"
                    f"2xx={len(success_codes)}, 429={len(rate_limited)}, 5xx={len(errors)}.\n"
                    f"Avg response time: {avg_time:.2f}s."
                ),
                confidence=60,
                evidence=[],
                remediation=(
                    "Enforce per-IP and per-account rate limits (e.g., 100 req/min). "
                    "Return 429 with Retry-After headers. Use API gateway throttling."
                ),
            )
        elif len(rate_limited) > concurrency * 0.5:
            self.add_finding(
                vector="rate_limit_abuse",
                severity="info",
                title="Rate limiting is enforced (429 returned)",
                description=f"{len(rate_limited)} of {concurrency} requests returned 429 — rate limiting is active.",
                confidence=90,
                evidence=[],
            )

    async def _retry_after_test(self, url: str) -> None:
        """If we get 429, check if Retry-After is present."""
        try:
            resp = await self.client.get(url)
            if resp.status_code == 429:
                retry = resp.headers.get("retry-after")
                if not retry:
                    self.add_finding(
                        vector="rate_limit_abuse",
                        severity="low",
                        title="429 response missing Retry-After header",
                        description=f"Endpoint {url} returned 429 but omitted the Retry-After header, making client-side backoff harder.",
                        confidence=80,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="Missing Retry-After in 429 response",
                            )
                        ],
                    )
        except Exception:
            pass
