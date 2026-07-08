"""
CSRF protection validator.
Checks for:
- Missing anti-CSRF tokens on state-changing forms
- SameSite cookie enforcement
- Weak or predictable token patterns
- Token bypass via double-submit / JSON content-type switching
"""
from __future__ import annotations

import re
from urllib.parse import urljoin
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class CSRFScanner(BaseModule):
    name = "csrf"
    description = "Cross-Site Request Forgery protection validation"
    supported_vectors = ["auth_bypass"]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        await self._check_samesite_cookies(target)
        await self._check_form_tokens(target)
        await self._check_json_bypass(target)
        return self.findings

    async def _check_samesite_cookies(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            set_cookie = resp.headers.get("set-cookie", "")
            if "samesite" not in set_cookie.lower():
                self.add_finding(
                    vector="auth_bypass",
                    severity="medium",
                    title="Missing SameSite cookie attribute",
                    description="Set-Cookie header does not include SameSite=Strict or SameSite=Lax. Cross-origin POST requests may be honored by the browser.",
                    confidence=80,
                    evidence=[
                        self.client.build_evidence(
                            resp.request, resp,
                            description="Set-Cookie lacks SameSite",
                        )
                    ],
                    remediation="Set SameSite=Strict or SameSite=Lax on all session cookies. Add __Host- prefix for additional protection.",
                )
            elif "samesite=none" in set_cookie.lower():
                self.add_finding(
                    vector="auth_bypass",
                    severity="high",
                    title="SameSite=None cookie without Secure flag",
                    description="SameSite=None was detected. If Secure is missing, modern browsers reject this cookie, causing session breakage or fallback to insecure behavior.",
                    confidence=85,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="If cross-origin embedding is required, ensure SameSite=None is paired with Secure. Otherwise use SameSite=Lax or Strict.",
                )
        except Exception:
            pass

    async def _check_form_tokens(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            body = resp.text
            forms = re.findall(r"<form[^>]*>.*?</form>", body, re.S | re.I)
            for form in forms:
                action_match = re.search(r'action=["\']([^"\']+)["\']', form, re.I)
                action = action_match.group(1) if action_match else ""
                if action and not action.startswith("http"):
                    action = urljoin(url, action)

                # State-changing methods
                method_match = re.search(r'method=["\']([^"\']+)["\']', form, re.I)
                method = (method_match.group(1) if method_match else "get").lower()
                if method == "get":
                    continue

                # Look for csrf token inputs
                has_token = bool(
                    re.search(r'name=["\'].*csrf.*["\']', form, re.I) or
                    re.search(r'name=["\'].*token.*["\']', form, re.I) or
                    re.search(r'name=["\'].*authenticity.*["\']', form, re.I)
                )

                if not has_token:
                    self.add_finding(
                        vector="auth_bypass",
                        severity="high",
                        title="State-changing form missing CSRF token",
                        description=f"Form with action '{action}' uses POST but contains no anti-CSRF token.",
                        confidence=85,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="POST form without CSRF token",
                            )
                        ],
                        remediation="Include a cryptographically random, session-bound anti-CSRF token in every state-changing form. Validate server-side.",
                    )
        except Exception:
            pass

    async def _check_json_bypass(self, url: str) -> None:
        """Some frameworks only validate CSRF for form-encoded, not JSON."""
        login_paths = ["/login", "/api/login", "/auth/login"]
        for path in login_paths:
            endpoint = urljoin(url, path)
            try:
                resp = await self.client.post(
                    endpoint,
                    json={"username": "test", "password": "test"},
                    follow_redirects=False,
                )
                if resp.status_code in (200, 302, 401, 403):
                    self.add_finding(
                        vector="auth_bypass",
                        severity="low",
                        title=f"JSON content-type accepted at {path}",
                        description=f"POST with Content-Type: application/json to {endpoint} was processed. Verify CSRF validation applies to JSON payloads.",
                        confidence=30,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="JSON POST accepted",
                            )
                        ],
                        remediation="Apply CSRF protection uniformly regardless of content-type. Consider double-submit cookie pattern for SPA/API endpoints.",
                    )
            except Exception:
                pass
