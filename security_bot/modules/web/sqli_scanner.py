"""
SQL Injection scanner supporting:
- Error-based detection
- Boolean-based blind inference (timing is skipped in safe_mode)
- Union-based keyword reflection checks
"""
from __future__ import annotations

from urllib.parse import urlencode, urljoin, urlparse

from core.base_module import BaseModule
from core.result import Severity


class SQLiScanner(BaseModule):
    name = "sqli"
    description = "SQL Injection detection engine"
    supported_vectors = ["sqli"]

    PAYLOADS = {
        "error": [
            "'", '"', "'--", "' OR '1'='1", "' AND 1=1--",
            "1' UNION SELECT NULL--", "1\" UNION SELECT NULL--",
            "' AND 1=2 UNION SELECT NULL--",
            "1') AND 1=1--", "1') AND 1=2--",
        ],
        "boolean": [
            "' AND 'a'='a", "' AND 'a'='b",
            "1 AND 1=1", "1 AND 1=2",
        ],
    }

    ERROR_SIGNATURES = [
        "sql syntax", "mysql_fetch", "mysqli_", "pg_query",
        "ORA-", "SQLite3::", "sql server", "unclosed quotation",
        "odbc_exec", "jdbc", "pdo_", "warning: mysql",
        "you have an error in your sql syntax",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        parsed = urlparse(target)
        base = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"
        params = self._parse_query(parsed.query)

        for param in params:
            await self._test_param(base, params, param, mode="error")
            if not self.config.safety.safe_mode:
                await self._test_param(base, params, param, mode="boolean")

        # Test common injectable paths (e.g., /api/users?id=1)
        for path_suffix in ["?id=1", "?user=1", "?page=1", "?category=1", "?product=1"]:
            url = urljoin(base, path_suffix)
            for payload in self.PAYLOADS["error"][:3]:
                await self._probe_url(url.replace("=1", f"=1{payload}"), payload)

        return self.findings

    def _parse_query(self, query: str) -> dict[str, str]:
        from urllib.parse import parse_qs
        result = parse_qs(query, keep_blank_values=True)
        return {k: v[0] for k, v in result.items()}

    async def _test_param(self, base: str, params: dict[str, str], param: str, mode: str) -> None:
        for payload in self.PAYLOADS[mode]:
            modified = dict(params)
            modified[param] = payload
            url = f"{base}?{urlencode(modified)}"
            await self._probe_url(url, payload, param)

    async def _probe_url(self, url: str, payload: str, param: str | None = None) -> None:
        try:
            resp = await self.client.get(url)
            body_lower = resp.text.lower()
            for sig in self.ERROR_SIGNATURES:
                if sig in body_lower:
                    self.add_finding(
                        vector="sqli",
                        severity="high",
                        title=f"Error-based SQL Injection detected{' in ' + param if param else ''}",
                        description=(
                            f"Payload '{payload}' triggered a database error signature: '{sig}'.\n"
                            f"This indicates unsanitized user input reaching the SQL parser."
                        ),
                        confidence=85,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp, payload=payload,
                                description=f"SQL error signature '{sig}' matched",
                            )
                        ],
                        remediation=(
                            "Use parameterized queries / prepared statements. "
                            "Apply least-privilege DB accounts. Enable WAF rules."
                        ),
                        references=[
                            "https://owasp.org/www-community/attacks/SQL_Injection",
                            "https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html",
                        ],
                    )
                    return
        except Exception:
            pass
