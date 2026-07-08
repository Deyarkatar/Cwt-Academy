"""
Directory indexing / directory listing detection.
Looks for Apache/Nginx/IIS directory listing patterns.
"""
from __future__ import annotations

import re
from urllib.parse import urljoin
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class IndexingDetector(BaseModule):
    name = "indexing"
    description = "Directory listing / indexing exposure detection"
    supported_vectors = ["info_disclosure"]

    PROBE_PATHS = [
        "/", "/images/", "/uploads/", "/files/", "/assets/",
        "/backup/", "/backups/", "/data/", "/docs/", "/documents/",
        "/media/", "/static/", "/tmp/", "/temp/", "/logs/",
        "/css/", "/js/", "/fonts/", "/img/", "/downloads/",
    ]

    INDEXING_SIGNATURES = [
        r"<title>Index of /",
        r"<h1>Index of /",
        r"Directory Listing for",
        r"Parent Directory</a>",
        r"<pre>Name\s+Last modified\s+Size\s+Description</pre>",
        r"\[To Parent Directory\]",
        r"nginx/\d+\.\d+.*404 Not Found",  # Sometimes nginx lists dirs
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        paths = kwargs.get("paths", self.PROBE_PATHS)
        for path in paths:
            url = urljoin(target.rstrip("/") + "/", path.lstrip("/"))
            await self._check_url(url)
        return self.findings

    async def _check_url(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            if resp.status_code != 200:
                return
            text = resp.text
            for pattern in self.INDEXING_SIGNATURES:
                if re.search(pattern, text, re.I):
                    files = self._extract_files(text)
                    self.add_finding(
                        vector="info_disclosure",
                        severity="medium",
                        title=f"Directory listing enabled: {url}",
                        description=(
                            f"{url} returned a directory listing page.\n"
                            f"Files/directories visible: {', '.join(files[:10])}"
                        ),
                        confidence=90,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="Directory listing HTML matched signature",
                            )
                        ],
                        remediation=(
                            "Disable directory listing in web server config.\n"
                            "Apache: Options -Indexes\n"
                            "Nginx: autoindex off;\n"
                            "IIS: <directoryBrowse enabled=\"false\"/>"
                        ),
                    )
                    return
        except Exception:
            pass

    def _extract_files(self, html: str) -> list[str]:
        """Quick regex to pull hrefs from listing pages."""
        matches = re.findall(r'href="([^"]+)"', html)
        return [m for m in matches if m not in ("../", "./", "/")]
