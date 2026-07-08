"""
Tests for SafeClient: scope validation and rate limiting.
"""
from __future__ import annotations

import asyncio

import pytest

from core.config import ScannerConfig
from core.http_client import SafeClient, ScopeViolationError


class TestSafeClient:
    @pytest.mark.asyncio
    async def test_scope_blocks_unauthorized(self, test_config: ScannerConfig) -> None:
        async with SafeClient(test_config) as client:
            with pytest.raises(ScopeViolationError):
                await client.get("https://evil.com")

    @pytest.mark.asyncio
    async def test_scope_allows_authorized(self, test_config: ScannerConfig) -> None:
        test_config.safety.allowed_hosts.append("authorized.local")
        async with SafeClient(test_config) as client:
            # This would normally fail with a real network call,
            # but the scope check should pass for an allowed host.
            assert client.config.safety.is_authorized("https://authorized.local")

    def test_rate_limiter_tokens(self, test_config: ScannerConfig) -> None:
        from core.http_client import RateLimiter
        limiter = RateLimiter(rps=10.0)
        # Should not block immediately
        assert limiter._tokens == 10.0

    def test_evidence_building(self, test_config: ScannerConfig) -> None:
        import httpx
        from core.http_client import SafeClient
        client = SafeClient(test_config)
        req = httpx.Request("GET", "http://test.local")
        resp = httpx.Response(200, text="ok", request=req)
        ev = client.build_evidence(req, resp, payload="test", description="test desc")
        assert ev.description == "test desc"
        assert ev.trigger_payload == "test"
