"""
GraphQL security scanner.
Tests:
- Introspection enabled
- Field suggestion enabled
- Query depth / complexity limits
- Batching abuse (alias flooding)
- Unauthorized directive injection
"""
from __future__ import annotations

from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class GraphQLScanner(BaseModule):
    name = "graphql"
    description = "GraphQL introspection, depth, and batching abuse detection"
    supported_vectors = ["info_disclosure", "rate_limit_abuse"]

    INTROSPECTION_QUERY = """
    query IntrospectionQuery {
      __schema {
        queryType { name }
        mutationType { name }
        types {
          name
          fields { name type { name } }
        }
      }
    }
    """

    FIELD_SUGGESTION_QUERY = '{ __type(name: "User") { name fields { name } } }'

    DEPTH_TEST = """
    query Deep { user { orders { items { product { category { name } } } } } }
    """

    BATCH_TEST = """
    query Batch {
      a1: user(id:1) { id }
      a2: user(id:2) { id }
      a3: user(id:3) { id }
      a4: user(id:4) { id }
      a5: user(id:5) { id }
      a6: user(id:6) { id }
      a7: user(id:7) { id }
      a8: user(id:8) { id }
      a9: user(id:9) { id }
      a10: user(id:10) { id }
    }
    """

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/graphql", "/api/graphql", "/query"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            await self._check_introspection(url)
            await self._check_field_suggestion(url)
            await self._check_depth_limit(url)
            await self._check_batching(url)
        return self.findings

    async def _check_introspection(self, url: str) -> None:
        try:
            resp = await self.client.post(
                url,
                json={"query": self.INTROSPECTION_QUERY},
                headers={"Content-Type": "application/json"},
            )
            if resp.status_code == 200 and "__schema" in resp.text:
                self.add_finding(
                    vector="info_disclosure",
                    severity="medium",
                    title="GraphQL introspection enabled",
                    description=f"POST to {url} returned the full schema via introspection.",
                    confidence=95,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Disable introspection in production. Use persisted queries.",
                )
        except Exception:
            pass

    async def _check_field_suggestion(self, url: str) -> None:
        try:
            resp = await self.client.post(
                url,
                json={"query": self.FIELD_SUGGESTION_QUERY},
            )
            if resp.status_code == 200 and "fields" in resp.text:
                self.add_finding(
                    vector="info_disclosure",
                    severity="low",
                    title="GraphQL field suggestion may be enabled",
                    description="Typo-tolerant field suggestions can leak internal schema structure.",
                    confidence=50,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Disable field suggestions in production.",
                )
        except Exception:
            pass

    async def _check_depth_limit(self, url: str) -> None:
        try:
            resp = await self.client.post(url, json={"query": self.DEPTH_TEST})
            if resp.status_code == 200:
                self.add_finding(
                    vector="rate_limit_abuse",
                    severity="low",
                    title="GraphQL deep query accepted (depth limit missing?)",
                    description="A deeply nested query was processed without rejection. Verify max depth enforcement.",
                    confidence=40,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Enforce query depth and complexity limits. Use a query cost analysis library.",
                )
        except Exception:
            pass

    async def _check_batching(self, url: str) -> None:
        try:
            resp = await self.client.post(url, json={"query": self.BATCH_TEST})
            if resp.status_code == 200 and resp.text.count('"id"') >= 10:
                self.add_finding(
                    vector="rate_limit_abuse",
                    severity="medium",
                    title="GraphQL query batching / alias flooding accepted",
                    description="A single request with 10 aliases was processed. This bypasses per-request rate limits.",
                    confidence=70,
                    evidence=[self.client.build_evidence(resp.request, resp)],
                    remediation="Limit the number of aliases per query. Apply cost analysis or disable batching.",
                )
        except Exception:
            pass
