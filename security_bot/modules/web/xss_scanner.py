"""
Cross-Site Scripting (XSS) scanner.
Detects:
- Reflected XSS (payload echoed verbatim in response)
- DOM-based hints (common sinks)
- Context-aware injection via polyglot payloads
"""
from __future__ import annotations

import html
from urllib.parse import urlencode, urlparse

from core.base_module import BaseModule
from core.result import Severity


class XSSScanner(BaseModule):
    name = "xss"
    description = "Reflected & DOM-based XSS detection"
    supported_vectors = ["xss"]

    PAYLOADS = [
        # Reflected probes
        "<script>alert(1)</script>",
        "<img src=x onerror=alert(1)>",
        "<svg onload=alert(1)>",
        "javascript:alert(1)",
        # Polyglot / context-breakers
        "'><script>alert(1)</script>",
        '"><script>alert(1)</script>',
        "`><script>alert(1)</script>",
        "</title><script>alert(1)</script>",
        "<!--<script>alert(1)</script>-->",
        # HTML-encoded bypass attempts
        "&lt;script&gt;alert(1)&lt;/script&gt;",
    ]

    SINK_PATTERNS = [
        "document.write", "innerHTML", "outerHTML", "eval(",
        "setTimeout(", "setInterval(", "location.href", "window.location",
        "document.cookie", "onerror=", "onload=",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        parsed = urlparse(target)
        base = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        params = self._parse_query(parsed.query)

        # Parameter reflection tests
        for param in params:
            for payload in self.PAYLOADS:
                await self._test_reflection(base, params, param, payload)

        # DOM sink analysis on the base page
        await self._analyze_dom_sinks(target)
        return self.findings

    def _parse_query(self, query: str) -> dict[str, str]:
        from urllib.parse import parse_qs
        result = parse_qs(query, keep_blank_values=True)
        return {k: v[0] for k, v in result.items()}

    async def _test_reflection(self, base: str, params: dict[str, str], param: str, payload: str) -> None:
        modified = dict(params)
        modified[param] = payload
        url = f"{base}?{urlencode(modified)}"
        try:
            resp = await self.client.get(url)
            body = resp.text

            if payload in body:
                confidence = 75
                severity = "high"
                context = self._identify_context(body, payload)
                if context == "html_encoded":
                    confidence = 30
                    severity = "low"

                self.add_finding(
                    vector="xss",
                    severity=severity,
                    title=f"Reflected XSS in parameter '{param}'",
                    description=(
                        f"Payload '{payload[:60]}...' was reflected verbatim in the response body.\n"
                        f"Injection context: {context}."
                    ),
                    confidence=confidence,
                    evidence=[
                        self.client.build_evidence(
                            resp.request, resp, payload=payload,
                            description=f"Payload reflected in {context} context",
                        )
                    ],
                    remediation=(
                        "Context-aware output encoding (HTML, JS, URL, CSS). "
                        "Use a modern templating framework with auto-escaping. "
                        "Implement a strict Content Security Policy (CSP)."
                    ),
                    references=[
                        "https://owasp.org/www-community/attacks/xss/",
                        "https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html",
                    ],
                )
        except Exception:
            pass

    def _identify_context(self, body: str, payload: str) -> str:
        if html.escape(payload) in body:
            return "html_encoded"
        if f"'{payload}'" in body or f'"{payload}"' in body:
            return "js_string"
        if payload in body:
            return "raw_html"
        return "unknown"

    async def _analyze_dom_sinks(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            body = resp.text
            for sink in self.SINK_PATTERNS:
                if sink in body:
                    self.add_finding(
                        vector="xss",
                        severity="info",
                        title=f"Potential DOM XSS sink: {sink}",
                        description=f"The string '{sink}' was found in the page source, indicating a potential DOM-based XSS vector.",
                        confidence=25,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"DOM sink '{sink}' present in source",
                            )
                        ],
                    )
        except Exception:
            pass
