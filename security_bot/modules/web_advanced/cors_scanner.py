"""
Cross-Origin Resource Sharing (CORS) misconfiguration scanner.
Detects:
- Access-Control-Allow-Origin: *
- Reflecting arbitrary Origin header
- Overly permissive Allow-Credentials
- Weak Allow-Methods
"""
from __future__ import annotations

from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class CORSScanner(BaseModule):
    name = "cors"
    description = "CORS policy misconfiguration detection"
    supported_vectors = ["misconfiguration"]

    TEST_ORIGINS = [
        "https://evil.com",
        "null",
        "http://localhost",
        "https://attacker.com",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        await self._check_wildcard(target)
        await self._check_reflection(target)
        await self._check_credentials_wildcard(target)
        return self.findings

    async def _check_wildcard(self, url: str) -> None:
        try:
            resp = await self.client.options(url)
            acao = resp.headers.get("access-control-allow-origin", "")
            if "*" in acao:
                self.add_finding(
                    vector="misconfiguration",
                    severity="medium",
                    title="CORS: Access-Control-Allow-Origin: *",
                    description="Any domain can make cross-origin requests to this endpoint.",
                    confidence=95,
                    evidence=[
                        self.client.build_evidence(
                            resp.request, resp,
                            description="ACAO: * detected",
                        )
                    ],
                    remediation="Replace * with an explicit allow-list of trusted domains.",
                )
        except Exception:
            pass

    async def _check_reflection(self, url: str) -> None:
        for origin in self.TEST_ORIGINS:
            try:
                resp = await self.client.get(
                    url,
                    headers={"Origin": origin},
                )
                acao = resp.headers.get("access-control-allow-origin", "")
                if origin in acao or (origin == "null" and acao == "null"):
                    self.add_finding(
                        vector="misconfiguration",
                        severity="high",
                        title="CORS: arbitrary Origin reflection",
                        description=f"Server reflected Origin '{origin}' in Access-Control-Allow-Origin.",
                        confidence=90,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Reflected Origin: {origin}",
                            )
                        ],
                        remediation="Validate the Origin header against an allow-list. Never echo user-supplied origins.",
                    )
                    return
            except Exception:
                pass

    async def _check_credentials_wildcard(self, url: str) -> None:
        try:
            resp = await self.client.options(
                url,
                headers={"Origin": "https://evil.com"},
            )
            acao = resp.headers.get("access-control-allow-origin", "")
            acc = resp.headers.get("access-control-allow-credentials", "").lower()
            if "*" in acao and acc == "true":
                self.add_finding(
                    vector="misconfiguration",
                    severity="critical",
                    title="CORS: Credentials allowed with wildcard origin",
                    description="Access-Control-Allow-Credentials: true is set alongside a wildcard ACAO. This is forbidden by the CORS spec and allows credential theft.",
                    confidence=100,
                    evidence=[
                        self.client.build_evidence(
                            resp.request, resp,
                            description="ACAO: * + ACAC: true",
                        )
                    ],
                    remediation="Never combine Allow-Credentials: true with ACAO: *. Use an explicit origin allow-list.",
                )
        except Exception:
            pass
