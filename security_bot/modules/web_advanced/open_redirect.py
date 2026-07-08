"""
Open Redirect scanner.
Detects unvalidated redirect/forward parameters that accept arbitrary URLs.
"""
from __future__ import annotations

from urllib.parse import urlencode, urljoin, urlparse
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class OpenRedirectScanner(BaseModule):
    name = "redirect"
    description = "Unvalidated redirect/forward detection"
    supported_vectors = ["open_redirect"]

    PARAMS = ["redirect", "redirect_uri", "return", "return_to", "next", "url", "target", "goto", "link", "callback"]

    PAYLOADS = [
        "https://evil.com",
        "//evil.com",
        "\\x5c\x5cevil.com",
        "https:evil.com",
        "//evil.com/%2f..",
        "https://evil.com?target=",
        "//evil.com@target.com",  # credential confusion
        "/\\.evil.com",  # path confusion
        "javascript:alert(1)",  # JS redirect
        "data:text/html,<script>alert(1)</script>",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        for param in self.PARAMS:
            for payload in self.PAYLOADS:
                url = f"{target}?{urlencode({param: payload})}"
                await self._probe(url, param, payload)
        return self.findings

    async def _probe(self, url: str, param: str, payload: str) -> None:
        try:
            resp = await self.client.get(url, follow_redirects=False)
            loc = resp.headers.get("location", "")
            if payload in loc or (payload.startswith("//") and payload[2:] in loc):
                self.add_finding(
                    vector="open_redirect",
                    severity="medium",
                    title=f"Open redirect via parameter '{param}'",
                    description=f"Parameter '{param}' accepted '{payload}' and the server responded with Location: {loc}.",
                    confidence=85,
                    evidence=[
                        self.client.build_evidence(
                            resp.request, resp, payload=payload,
                            description=f"Redirect to {loc}",
                        )
                    ],
                    remediation=(
                        "Use an allow-list of permitted redirect destinations. "
                        "Never redirect based on user-supplied input without validation. "
                        "Prefix-match against a known-safe domain list."
                    ),
                    references=[
                        "https://cheatsheetseries.owasp.org/cheatsheets/Unvalidated_Redirects_and_Forwards_Cheat_Sheet.html",
                    ],
                )
        except Exception:
            pass
