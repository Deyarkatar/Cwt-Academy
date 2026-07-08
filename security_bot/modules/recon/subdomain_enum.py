"""
Subdomain enumeration via:
- DNS brute-force (async wordlist)
- Certificate Transparency log scraping (crt.sh)
- Subdomain permutation / alteration
"""
from __future__ import annotations

import asyncio
from urllib.parse import urljoin

from core.base_module import BaseModule
from core.result import Severity


class SubdomainEnum(BaseModule):
    name = "subdomain"
    description = "Subdomain discovery via brute-force, CT logs, and permutation"
    supported_vectors = ["info_disclosure"]

    DEFAULT_WORDLIST = [
        "www", "mail", "ftp", "localhost", "webmail", "smtp", "pop", "ns1", "webdisk",
        "ns2", "cpanel", "whm", "autodiscover", "autoconfig", "ns", "mx1", "mx2",
        "mx", "blog", "dev", "staging", "test", "admin", "api", "app", "beta",
        "cdn", "cms", "corp", "data", "demo", "docs", "edge", "git", "gitlab",
        "go", "grafana", "img", "jenkins", "k8s", "kube", "kubernetes", "lab",
        "logs", "monitor", "m", "mobile", "new", "news", "node", "old", "ops",
        "panel", "partner", "payment", "prod", "production", "prometheus", "proxy",
        "qa", "remote", "repo", "sandbox", "search", "secure", "shop", "stage",
        "static", "stats", "status", "store", "support", "swarm", "tools", "upload",
        "vault", "video", "vpn", "vps", "web", "wiki", "www1", "www2", "zabbix",
    ]

    PERMUTATION_SUFFIXES = [
        "-dev", "-staging", "-test", "-prod", "-api", "-admin", "-internal",
        "-old", "-new", "-beta", "-backup", "-tmp", "-temp", "-debug", "-private",
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        domain = self._extract_domain(target)
        if not domain:
            self.logger.warning("Could not extract domain from %s", target)
            return self.findings

        wordlist = kwargs.get("wordlist", self.DEFAULT_WORDLIST)
        await asyncio.gather(
            self._brute_force(domain, wordlist),
            self._ct_logs(domain),
            self._permutations(domain),
        )
        return self.findings

    def _extract_domain(self, url: str) -> str | None:
        from urllib.parse import urlparse
        parsed = urlparse(url)
        host = parsed.hostname
        if not host:
            return None
        # Strip www. and extract apex if needed, but keep full for subdomain scan
        return host

    async def _brute_force(self, domain: str, wordlist: list[str]) -> None:
        sem = asyncio.Semaphore(50)

        async def _check(sub: str) -> None:
            async with sem:
                url = f"http://{sub}.{domain}"
                try:
                    resp = await self.client.get(url, timeout=5)
                    if resp.status_code < 500:
                        self.add_finding(
                            vector="info_disclosure",
                            severity="info",
                            title=f"Live subdomain discovered: {sub}.{domain}",
                            description=f"Subdomain {sub}.{domain} responded with HTTP {resp.status_code}.",
                            confidence=90,
                            evidence=[
                                self.client.build_evidence(
                                    resp.request, resp,
                                    description=f"Subdomain {sub}.{domain} is live",
                                )
                            ],
                        )
                except Exception:
                    pass

        tasks = [_check(w) for w in wordlist]
        await asyncio.gather(*tasks, return_exceptions=True)

    async def _ct_logs(self, domain: str) -> None:
        """Query crt.sh for certificate transparency entries."""
        import json

        ct_url = f"https://crt.sh/?q=%.{domain}&output=json"
        try:
            resp = await self.client.get(ct_url, timeout=15)
            if resp.status_code != 200:
                return
            data = resp.json()
            seen: set[str] = set()
            for entry in data:
                name = entry.get("name_value", "")
                for sub in name.split("\n"):
                    sub = sub.strip().lower()
                    if sub.endswith(domain) and sub != domain and sub not in seen:
                        seen.add(sub)
                        self.add_finding(
                            vector="info_disclosure",
                            severity="info",
                            title=f"CT log subdomain: {sub}",
                            description=f"Certificate Transparency log revealed subdomain {sub}.",
                            confidence=95,
                            evidence=[],
                        )
        except Exception:
            pass

    async def _permutations(self, domain: str) -> None:
        """Try common suffix permutations on discovered subdomains."""
        # Lightweight: just test suffix mutations on apex
        for suffix in self.PERMUTATION_SUFFIXES:
            sub = f"www{suffix}"
            url = f"http://{sub}.{domain}"
            try:
                resp = await self.client.get(url, timeout=5)
                if resp.status_code < 500:
                    self.add_finding(
                        vector="info_disclosure",
                        severity="info",
                        title=f"Permutation hit: {sub}.{domain}",
                        description=f"Suffix permutation '{suffix}' yielded a live host.",
                        confidence=50,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="Permutation subdomain responded",
                            )
                        ],
                    )
            except Exception:
                pass
