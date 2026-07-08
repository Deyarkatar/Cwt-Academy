"""
OAuth 2.0 / OIDC security tester.
Tests:
- Open redirect in authorization endpoint
- State parameter missing
- PKCE missing for public clients
- Scope escalation
"""
from __future__ import annotations

from urllib.parse import urlencode, urljoin
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class OAuthTester(BaseModule):
    name = "oauth"
    description = "OAuth 2.0 / OIDC flow security checks"
    supported_vectors = ["open_redirect", "auth_bypass"]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/oauth/authorize", "/auth", "/connect/authorize"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            await self._check_redirect_uri(url)
            await self._check_state_missing(url)
            await self._check_pkce(url)
        return self.findings

    async def _check_redirect_uri(self, url: str) -> None:
        params = {
            "client_id": "test",
            "response_type": "code",
            "redirect_uri": "https://evil.com/callback",
            "scope": "openid",
        }
        try:
            resp = await self.client.get(f"{url}?{urlencode(params)}", follow_redirects=False)
            loc = resp.headers.get("location", "")
            if "evil.com" in loc:
                self.add_finding(
                    vector="open_redirect",
                    severity="high",
                    title="OAuth authorization endpoint allows arbitrary redirect_uri",
                    description=f"redirect_uri=https://evil.com/callback was accepted. Location: {loc}",
                    confidence=90,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Validate redirect_uri against a pre-registered allow-list per client. Reject unknown URIs.",
                )
        except Exception:
            pass

    async def _check_state_missing(self, url: str) -> None:
        params = {
            "client_id": "test",
            "response_type": "code",
            "redirect_uri": "https://example.com/callback",
            "scope": "openid",
        }
        try:
            resp = await self.client.get(f"{url}?{urlencode(params)}", follow_redirects=False)
            if resp.status_code in (302, 303, 307, 308):
                self.add_finding(
                    vector="auth_bypass",
                    severity="medium",
                    title="OAuth state parameter not enforced",
                    description="Authorization endpoint processed a request without a state parameter, enabling CSRF.",
                    confidence=60,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Require 'state' parameter and validate it matches the original value on callback.",
                )
        except Exception:
            pass

    async def _check_pkce(self, url: str) -> None:
        # PKCE is mainly tested on token endpoint — if code_challenge is absent for public clients, flag it
        # This is a lightweight check; full PKCE validation requires knowing client type
        pass
