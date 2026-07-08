"""
API versioning weakness scanner.
Detects:
- Deprecated/vulnerable versions still active
- Missing sunset / deprecation headers
- Version enumeration
"""
from __future__ import annotations

from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class APIVersioning(BaseModule):
    name = "apiversion"
    description = "API versioning and deprecation security checks"
    supported_vectors = ["misconfiguration"]

    VERSION_PATHS = [
        "/api/v1/", "/api/v2/", "/api/v3/",
        "/api/1/", "/api/2/",
        "/v1/", "/v2/", "/v3/",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        for path in self.VERSION_PATHS:
            url = target.rstrip("/") + path
            await self._probe_version(url)
        return self.findings

    async def _probe_version(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            if resp.status_code == 200:
                sunset = resp.headers.get("sunset")
                dep = resp.headers.get("deprecation")
                if not sunset and not dep:
                    self.add_finding(
                        vector="misconfiguration",
                        severity="info",
                        title=f"API version active without deprecation headers: {url}",
                        description=f"{url} returned 200 but has no Sunset or Deprecation headers. Cannot determine if this version is maintained.",
                        confidence=40,
                        evidence=[self.client.build_evidence(resp.request, resp)],
                        remediation="Add Deprecation and Sunset headers to API responses. Document version lifecycle.",
                    )
        except Exception:
            pass
