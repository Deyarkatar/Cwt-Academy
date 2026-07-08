"""
Insecure Direct Object Reference (IDOR) scanner.
Tests sequential ID access across authenticated endpoints.
"""
from __future__ import annotations

from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class IDORScanner(BaseModule):
    name = "idor"
    description = "Insecure Direct Object Reference detection"
    supported_vectors = ["idor"]

    PROBE_ENDPOINTS = [
        "/api/users/{id}", "/api/orders/{id}", "/api/invoices/{id}",
        "/api/documents/{id}", "/api/tickets/{id}", "/api/payments/{id}",
        "/api/admin/users/{id}", "/api/v1/resources/{id}",
    ]

    ID_RANGE = range(1, 10)

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", self.PROBE_ENDPOINTS)
        id_range = kwargs.get("id_range", self.ID_RANGE)

        for template in endpoints:
            for i in id_range:
                url = target.rstrip("/") + template.format(id=i)
                await self._probe(url, i)
        return self.findings

    async def _probe(self, url: str, tested_id: int) -> None:
        try:
            resp = await self.client.get(url)
            # If we get 200 with what looks like structured data, flag it
            if resp.status_code == 200 and len(resp.text) > 50:
                body = resp.text.lower()
                if any(x in body for x in ["email", "password", "address", "phone", "ssn", "credit_card", "balance"]):
                    self.add_finding(
                        vector="idor",
                        severity="high",
                        title=f"Potential IDOR: sequential access to resource {tested_id}",
                        description=f"GET {url} returned 200 with what appears to be sensitive data for resource ID {tested_id}.",
                        confidence=55,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Accessible resource ID {tested_id}",
                            )
                        ],
                        remediation=(
                            "Implement authorization checks for every resource access. "
                            "Use non-sequential UUIDs instead of integer IDs. "
                            "Apply row-level security or policy-based access control."
                        ),
                    )
        except Exception:
            pass
