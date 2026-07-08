"""
Technology fingerprinting:
- HTTP header analysis (Server, X-Powered-By, X-Generator, etc.)
- Favicon MD5 hash lookup
- HTML body regex signatures (Wappalyzer-style)
- robots.txt / sitemap.xml parsing
- Error page stack-trace technology leaks
"""
from __future__ import annotations

import hashlib
import re
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class TechFingerprint(BaseModule):
    name = "fingerprint"
    description = "Technology stack fingerprinting and version detection"
    supported_vectors = ["info_disclosure"]

    HEADER_SIGNATURES: dict[str, tuple[str, str, str]] = {
        "x-powered-by": (r"PHP[ /]?([\d.]+)?", "PHP", "high"),
        "server": (r"Apache[/ ]?([\d.]+)?", "Apache", "medium"),
        "server": (r"nginx[/ ]?([\d.]+)?", "Nginx", "medium"),
        "server": (r"Microsoft-IIS[/ ]?([\d.]+)?", "IIS", "medium"),
        "x-generator": (r".*", "CMS Generator", "medium"),
        "x-aspnet-version": (r".*", "ASP.NET", "high"),
        "x-drupal-cache": (r".*", "Drupal", "medium"),
        "x-version": (r".*", "Generic Version", "low"),
    }

    BODY_SIGNATURES: list[tuple[str, str, str, str]] = [
        (r"wp-content/", "WordPress", "medium", "WordPress detected via asset path"),
        (r"<meta name=\"generator\" content=\"WordPress ([\d.]+)\"", "WordPress", "high", "WordPress version in meta generator"),
        (r"<script[^>]*src=\"[^\"]*jquery[/-]?([\d.]+)?", "jQuery", "low", "jQuery library loaded"),
        (r"<script[^>]*src=\"[^\"]*react[/-]?([\d.]+)?", "React", "low", "React library loaded"),
        (r"<script[^>]*src=\"[^\"]*vue[/-]?([\d.]+)?", "Vue.js", "low", "Vue.js library loaded"),
        (r"<script[^>]*src=\"[^\"]*angular[/-]?([\d.]+)?", "Angular", "low", "Angular library loaded"),
        (r"django", "Django", "medium", "Django framework artifacts"),
        (r"laravel", "Laravel", "medium", "Laravel framework artifacts"),
        (r"ruby on rails", "Ruby on Rails", "medium", "Rails framework artifacts"),
        (r"express", "Express.js", "medium", "Express.js artifacts"),
        (r"spring", "Spring", "medium", "Spring framework artifacts"),
        (r"fastapi", "FastAPI", "medium", "FastAPI framework artifacts"),
        (r"<meta name=\"csrf-token\"", "CSRF Protection", "info", "CSRF token meta tag present"),
    ]

    KNOWN_FAVICON_HASHES: dict[str, str] = {
        "f3418a443e7d841097c714d69ec1a7f1": "WordPress",
        "c29be95b39f3885a71ecc80f3e17a3c8": "Drupal",
        "89b932b4ff3915682337901298c7e0a5": "Joomla",
        "b5d3f4f4f4f4f4f4f4f4f4f4f4f4f4f4": "Generic",
    }

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        await self._analyze_headers(target)
        await self._analyze_body(target)
        await self._check_favicon(target)
        await self._check_robots_sitemap(target)
        await self._check_error_leakage(target)
        return self.findings

    async def _analyze_headers(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            for header, (pattern, tech, severity) in self.HEADER_SIGNATURES.items():
                value = resp.headers.get(header)
                if value:
                    match = re.search(pattern, value, re.I)
                    version = match.group(1) if match and match.lastindex else "unknown"
                    self.add_finding(
                        vector="info_disclosure",
                        severity="low" if severity == "medium" else severity,
                        title=f"Technology leaked in header: {tech}",
                        description=f"Header '{header}: {value}' reveals {tech} (version: {version}).",
                        confidence=85,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Header leak: {header}",
                            )
                        ],
                        remediation="Remove or obfuscate technology-specific headers. Use a reverse proxy to strip internal headers.",
                    )
        except Exception:
            pass

    async def _analyze_body(self, url: str) -> None:
        try:
            resp = await self.client.get(url)
            text = resp.text
            for pattern, tech, severity, desc in self.BODY_SIGNATURES:
                if re.search(pattern, text, re.I):
                    self.add_finding(
                        vector="info_disclosure",
                        severity=severity,
                        title=f"Technology detected: {tech}",
                        description=desc,
                        confidence=80,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Body signature matched: {tech}",
                            )
                        ],
                    )
        except Exception:
            pass

    async def _check_favicon(self, url: str) -> None:
        from urllib.parse import urljoin
        favicon_url = urljoin(url, "/favicon.ico")
        try:
            resp = await self.client.get(favicon_url)
            if resp.status_code == 200:
                md5 = hashlib.md5(resp.content).hexdigest()
                tech = self.KNOWN_FAVICON_HASHES.get(md5)
                if tech:
                    self.add_finding(
                        vector="info_disclosure",
                        severity="low",
                        title=f"Favicon fingerprint: {tech}",
                        description=f"Favicon MD5 {md5} matches known {tech} installation.",
                        confidence=70,
                        evidence=[],
                    )
        except Exception:
            pass

    async def _check_robots_sitemap(self, url: str) -> None:
        from urllib.parse import urljoin
        for path in ["/robots.txt", "/sitemap.xml"]:
            try:
                resp = await self.client.get(urljoin(url, path))
                if resp.status_code == 200:
                    body = resp.text[:2048]
                    self.add_finding(
                        vector="info_disclosure",
                        severity="info",
                        title=f"{path} publicly accessible",
                        description=f"{path} returned content that may reveal hidden paths or site structure.",
                        confidence=90,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"{path} content",
                            )
                        ],
                        remediation="Review robots.txt / sitemap.xml for sensitive paths. Do not rely on robots.txt for security.",
                    )
            except Exception:
                pass

    async def _check_error_leakage(self, url: str) -> None:
        """Trigger a 404 and inspect for stack traces / framework leakage."""
        import uuid
        probe_url = f"{url.rstrip('/')}/{uuid.uuid4()}"
        try:
            resp = await self.client.get(probe_url)
            text_lower = resp.text.lower()
            leaks = {
                "laravel": "laravel",
                "django": "django",
                "traceback": "python traceback",
                "stack trace": "generic stack trace",
                "exception": "exception details",
                "sqlstate": "database error",
            }
            for keyword, description in leaks.items():
                if keyword in text_lower:
                    self.add_finding(
                        vector="info_disclosure",
                        severity="medium",
                        title=f"Error page leaks: {description}",
                        description=f"404 page at {probe_url} contains '{keyword}' — application stack/technology visible.",
                        confidence=75,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description="Error page leakage",
                            )
                        ],
                        remediation="Configure custom error pages. Disable debug mode in production. Strip stack traces from HTTP responses.",
                    )
                    break
        except Exception:
            pass
