"""
REST API mass-assignment vulnerability scanner.
Tests:
- Extra parameter persistence (e.g., adding 'role=admin' to user update)
- Ignored parameter reflection (framework silently dropping vs persisting)
"""
from __future__ import annotations

import json
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class RESTMassAssignment(BaseModule):
    name = "massassign"
    description = "REST API mass-assignment / extra parameter persistence"
    supported_vectors = ["auth_bypass"]

    PROBES = [
        {"role": "admin"},
        {"is_admin": True},
        {"admin": True},
        {"status": "active"},
        {"is_active": True},
        {"is_staff": True},
        {"is_superuser": True},
        {"permissions": ["all"]},
        {"balance": 999999},
        {"credit": 999999},
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/api/users/me", "/api/profile", "/api/account"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            await self._probe_extra_params(url)
        return self.findings

    async def _probe_extra_params(self, url: str) -> None:
        for probe in self.PROBES:
            try:
                resp = await self.client.patch(url, json=probe)
                if resp.status_code in (200, 201, 204):
                    body = resp.text.lower()
                    # If the response echoes back the injected privilege field
                    for key in probe:
                        if key.lower() in body:
                            self.add_finding(
                                vector="auth_bypass",
                                severity="high",
                                title=f"Mass-assignment: '{key}' parameter persisted",
                                description=f"PATCH {url} with '{key}' was accepted and the field was reflected in the response.",
                                confidence=70,
                                evidence=[
                                    self.client.build_evidence(
                                        resp.request, resp,
                                        description=f"Injected {key} persisted",
                                    )
                                ],
                                remediation="Use explicit allow-lists / DTOs for write operations. Never bind request bodies directly to ORM models.",
                            )
                            return
            except Exception:
                pass
