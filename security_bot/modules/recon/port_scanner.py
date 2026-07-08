"""
Async port scanner using TCP connect (full SYN requires raw sockets / root).
Optimized with asyncio and semaphore-based concurrency.
"""
from __future__ import annotations

import asyncio
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class PortScanner(BaseModule):
    name = "portscan"
    description = "TCP port scanning with banner grabbing"
    supported_vectors = ["info_disclosure"]

    TOP_PORTS = [
        21, 22, 23, 25, 53, 80, 110, 111, 135, 139, 143, 443,
        445, 993, 995, 1723, 3306, 3389, 5432, 5900, 5901, 6379,
        8080, 8443, 9200, 9300, 27017, 27018,
    ]

    BANNER_PORTS = {
        21: "FTP",
        22: "SSH",
        25: "SMTP",
        80: "HTTP",
        110: "POP3",
        143: "IMAP",
        443: "HTTPS",
        3306: "MySQL",
        3389: "RDP",
        5432: "PostgreSQL",
        5900: "VNC",
        6379: "Redis",
        8080: "HTTP-Alt",
        9200: "Elasticsearch",
        27017: "MongoDB",
    }

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        domain = self._extract_host(target)
        if not domain:
            self.logger.warning("No host extracted from %s", target)
            return self.findings

        ports = kwargs.get("ports", self.TOP_PORTS)
        concurrency = kwargs.get("concurrency", 100)
        timeout = kwargs.get("timeout", 3.0)

        sem = asyncio.Semaphore(concurrency)
        results: list[tuple[int, bool, str]] = []

        async def _probe(port: int) -> None:
            async with sem:
                try:
                    reader, writer = await asyncio.wait_for(
                        asyncio.open_connection(domain, port),
                        timeout=timeout,
                    )
                    banner = await self._grab_banner(reader)
                    writer.close()
                    await writer.wait_closed()
                    results.append((port, True, banner))
                except Exception:
                    results.append((port, False, ""))

        await asyncio.gather(*(_probe(p) for p in ports), return_exceptions=True)

        for port, open_, banner in results:
            if open_:
                svc = self.BANNER_PORTS.get(port, "unknown")
                self.add_finding(
                    vector="info_disclosure",
                    severity="medium",
                    title=f"Open port: {port}/{svc}",
                    description=f"TCP port {port} is open on {domain}. Banner: {banner[:256] or 'N/A'}",
                    confidence=95,
                    evidence=[],
                    remediation="Close unnecessary ports. Use a firewall / security group. Expose only required services.",
                )

        return self.findings

    def _extract_host(self, url: str) -> str | None:
        from urllib.parse import urlparse
        parsed = urlparse(url)
        return parsed.hostname

    async def _grab_banner(self, reader: asyncio.StreamReader) -> str:
        try:
            data = await asyncio.wait_for(reader.read(1024), timeout=2.0)
            return data.decode("utf-8", "replace").strip()
        except Exception:
            return ""
