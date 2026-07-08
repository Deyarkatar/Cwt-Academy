"""
Advanced web fuzzer: discovers hidden endpoints, parameters,
and analyzes response deltas for anomalies.
"""
from __future__ import annotations

import asyncio
from urllib.parse import urlencode, urljoin, urlparse

from core.base_module import BaseModule
from core.result import Finding, Severity


class WebFuzzer(BaseModule):
    name = "fuzz"
    description = "Directory / parameter / file-extension fuzzing"
    supported_vectors = ["fuzzing", "info_disclosure", "path_traversal"]

    DEFAULT_WORDLIST = [
        "admin", "api", "backup", "config", "debug", "env", ".env",
        "test", "staging", "dev", "internal", "private", "robots.txt",
        ".git", ".svn", ".htaccess", "phpinfo.php", "composer.json",
        "package.json", "Dockerfile", "docker-compose.yml", "swagger.json",
        "api/v1", "api/v2", "graphql", "wp-admin", "phpmyadmin",
        "server-status", "actuator", "health", "metrics", "trace",
    ]

    EXTENSIONS = ["", ".php", ".html", ".json", ".xml", ".bak", ".old", ".zip", ".tar.gz", ".sql"]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        words = kwargs.get("wordlist", self.DEFAULT_WORDLIST)
        await asyncio.gather(
            self._fuzz_directories(target, words),
            self._fuzz_parameters(target),
        )
        return self.findings

    async def _fuzz_directories(self, target: str, words: list[str]) -> None:
        for word in words:
            for ext in self.EXTENSIONS:
                url = urljoin(target.rstrip("/") + "/", f"{word}{ext}")
                try:
                    resp = await self.client.get(url)
                    if resp.status_code == 200:
                        body = resp.text[:512]
                        self.add_finding(
                            vector="info_disclosure",
                            severity="medium",
                            title=f"Exposed endpoint found: /{word}{ext}",
                            description=f"GET {url} returned 200 OK. Body snippet: {body[:200]}",
                            confidence=70,
                            evidence=[
                                self.client.build_evidence(
                                    resp.request, resp,
                                    description=f"Hidden endpoint /{word}{ext} is accessible",
                                )
                            ],
                            remediation="Restrict access or remove exposed administrative/debug endpoints.",
                        )
                    elif resp.status_code in (301, 302, 307, 308):
                        self.add_finding(
                            vector="info_disclosure",
                            severity="low",
                            title=f"Redirect hints at hidden path: /{word}{ext}",
                            description=f"Status {resp.status_code} at {url} — may indicate a redirect to a protected resource.",
                            confidence=40,
                            evidence=[
                                self.client.build_evidence(
                                    resp.request, resp,
                                    description="Redirect response",
                                )
                            ],
                        )
                except Exception:
                    pass

    async def _fuzz_parameters(self, target: str) -> None:
        probes = ["'", '"', "<script>", "${jndi:ldap}", "../../../etc/passwd", "|id"]
        parsed = urlparse(target)
        base = f"{parsed.scheme}://{parsed.netloc}{parsed.path}"

        for probe in probes:
            payload = {f"fuzz_{i}": probe for i in range(3)}
            url = f"{base}?{urlencode(payload)}"
            try:
                resp = await self.client.get(url)
                body = resp.text.lower()
                if any(x in body for x in ["sql", "mysql", "syntax", "error", "exception", "traceback"]):
                    self.add_finding(
                        vector="fuzzing",
                        severity="medium",
                        title="Error-based anomaly from fuzzed parameters",
                        description=f"Injecting '{probe}' triggered an application error page.",
                        confidence=60,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp, payload=probe,
                                description="Fuzz probe triggered error disclosure",
                            )
                        ],
                    )
            except Exception:
                pass
