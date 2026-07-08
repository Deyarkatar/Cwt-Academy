"""
Unit tests for individual attack modules using mocked HTTP responses.
"""
from __future__ import annotations

from unittest.mock import AsyncMock, MagicMock

import httpx
import pytest

from core.config import ScannerConfig
from core.http_client import SafeClient
from modules.web.sqli_scanner import SQLiScanner
from modules.web.xss_scanner import XSSScanner
from modules.web.fuzzer import WebFuzzer


class MockClient:
    """Lightweight mock that mimics SafeClient interface."""

    def __init__(self, responses: list[httpx.Response]) -> None:
        self._responses = list(responses)
        self._idx = 0
        self.config = ScannerConfig(
            safety=ScannerConfig.model_fields["safety"].annotation(
                allowed_hosts=["test.local"],
                require_explicit_authorization=False,
            )
        )

    async def get(self, url: str, **kwargs: Any) -> httpx.Response:
        resp = self._responses[self._idx % len(self._responses)]
        self._idx += 1
        return resp

    async def post(self, url: str, **kwargs: Any) -> httpx.Response:
        return await self.get(url)

    def build_evidence(self, *args: Any, **kwargs: Any) -> Any:
        from core.result import Evidence
        return Evidence(description=kwargs.get("description", ""))


class TestSQLiScanner:
    @pytest.mark.asyncio
    async def test_error_based_detection(self) -> None:
        req = httpx.Request("GET", "http://test.local?id=1'")
        body = "You have an error in your SQL syntax near ''1'' at line 1"
        resp = httpx.Response(500, text=body, request=req)

        mock_client = MockClient([resp])
        config = mock_client.config
        scanner = SQLiScanner(config, mock_client)
        findings = await scanner.run("http://test.local?id=1")

        assert len(findings) >= 1
        assert findings[0].vector.value == "sqli"
        assert findings[0].severity.value == "high"


class TestXSSScanner:
    @pytest.mark.asyncio
    async def test_reflected_xss_detection(self) -> None:
        payload = "<script>alert(1)</script>"
        req = httpx.Request("GET", f"http://test.local?q={payload}")
        body = f"<html><body>Search results for: {payload}</body></html>"
        resp = httpx.Response(200, text=body, request=req)

        mock_client = MockClient([resp])
        config = mock_client.config
        scanner = XSSScanner(config, mock_client)
        findings = await scanner.run("http://test.local?q=test")

        assert len(findings) >= 1
        assert any(f.vector.value == "xss" for f in findings)


class TestWebFuzzer:
    @pytest.mark.asyncio
    async def test_hidden_endpoint_discovery(self) -> None:
        req = httpx.Request("GET", "http://test.local/admin")
        resp_200 = httpx.Response(200, text="admin panel", request=req)
        resp_404 = httpx.Response(404, text="not found", request=req)

        mock_client = MockClient([resp_404, resp_200])
        config = mock_client.config
        scanner = WebFuzzer(config, mock_client)
        findings = await scanner.run("http://test.local")

        assert any(f.title.startswith("Exposed endpoint") for f in findings)
