"""
Forensic-grade HTTP client with rate-limiting, proxy support,
and automatic scope validation. Built on httpx.
"""
from __future__ import annotations

import asyncio
import time
from typing import Any

import httpx

from .config import ScannerConfig
from .result import Evidence


class RateLimiter:
    """Token-bucket rate limiter."""

    def __init__(self, rps: float):
        self._rate = rps
        self._tokens = rps
        self._last = time.monotonic()
        self._lock = asyncio.Lock()

    async def acquire(self) -> None:
        async with self._lock:
            now = time.monotonic()
            elapsed = now - self._last
            self._tokens = min(self._rate, self._tokens + elapsed * self._rate)
            self._last = now
            if self._tokens < 1:
                wait = (1 - self._tokens) / self._rate
                await asyncio.sleep(wait)
                self._tokens = 0
            else:
                self._tokens -= 1


class SafeClient:
    """
    Scoped, rate-limited async HTTP client.
    Every request is validated against authorized targets.
    """

    def __init__(self, config: ScannerConfig):
        self.config = config
        self.limiter = RateLimiter(config.safety.max_requests_per_second)
        self._session: httpx.AsyncClient | None = None
        self._request_count = 0
        self._error_count = 0

    async def __aenter__(self) -> SafeClient:
        limits = httpx.Limits(
            max_connections=self.config.safety.max_concurrent_requests,
            max_keepalive_connections=20,
        )
        transport = httpx.AsyncHTTPTransport(limits=limits)
        proxy = self.config.proxy

        headers = {"User-Agent": self.config.user_agent}
        headers.update(self.config.custom_headers)
        if self.config.auth_bearer:
            headers["Authorization"] = f"Bearer {self.config.auth_bearer}"
        if self.config.auth_cookie:
            headers["Cookie"] = self.config.auth_cookie

        client_kwargs: dict[str, Any] = dict(
            transport=transport,
            headers=headers,
            timeout=httpx.Timeout(
                self.config.safety.request_timeout,
                connect=self.config.safety.connection_timeout,
            ),
            follow_redirects=True,
            http2=True,
        )
        if proxy:
            client_kwargs["proxy"] = proxy

        self._session = httpx.AsyncClient(**client_kwargs)
        return self

    async def __aexit__(self, *args: Any) -> None:
        if self._session:
            await self._session.aclose()

    def _scope_check(self, url: str) -> None:
        if not self.config.safety.is_authorized(url):
            raise ScopeViolationError(
                f"Target {url} is NOT in the authorized scope. "
                f"Allowed: {self.config.safety.allowed_hosts}"
            )

    async def request(
        self,
        method: str,
        url: str,
        **kwargs: Any,
    ) -> httpx.Response:
        self._scope_check(url)
        await self.limiter.acquire()
        if not self._session:
            raise RuntimeError("Client not started. Use async with.")

        self._request_count += 1
        response = await self._session.request(method, url, **kwargs)
        return response

    async def safe_request(
        self,
        method: str,
        url: str,
        **kwargs: Any,
    ) -> httpx.Response:
        last_exc: Exception | None = None
        for attempt in range(1, 4):
            try:
                return await self.request(method, url, **kwargs)
            except (httpx.TimeoutException, httpx.ConnectError) as exc:
                last_exc = exc
                wait_time = min(2 ** attempt, 10)
                await asyncio.sleep(wait_time)
        raise last_exc or RuntimeError("Request failed after retries")

    async def get(self, url: str, **kwargs: Any) -> httpx.Response:
        return await self.request("GET", url, **kwargs)

    async def post(self, url: str, **kwargs: Any) -> httpx.Response:
        return await self.request("POST", url, **kwargs)

    def build_evidence(
        self,
        request: httpx.Request,
        response: httpx.Response | None,
        payload: str | None = None,
        description: str = "",
    ) -> Evidence:
        req_text = f"{request.method} {request.url}\n{request.headers}\n\n{request.content.decode('utf-8', 'replace')[:2048]}"
        resp_text = ""
        if response:
            resp_text = (
                f"HTTP {response.status_code}\n{response.headers}\n\n"
                f"{response.text[:2048]}"
            )
        return Evidence(
            request=req_text,
            response_snippet=resp_text,
            trigger_payload=payload,
            description=description,
        )


class ScopeViolationError(Exception):
    """Raised when a request targets an unauthorized host."""
