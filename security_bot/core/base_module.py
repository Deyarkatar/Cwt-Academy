"""
Abstract base class for all scanning / attack modules.
"""
from __future__ import annotations

import logging
from abc import ABC, abstractmethod
from typing import Any

from .config import ScannerConfig
from .http_client import SafeClient
from .result import Finding, ScanSummary

logger = logging.getLogger(__name__)


class BaseModule(ABC):
    """
    Every recon, exploit, or fuzzing module inherits from this.
    The engine injects config and client; modules report findings.
    """

    name: str = "base"
    description: str = ""
    supported_vectors: list[str] = []

    def __init__(self, config: ScannerConfig, client: SafeClient) -> None:
        self.config = config
        self.client = client
        self.findings: list[Finding] = []
        self.logger = logging.getLogger(f"security_bot.module.{self.name}")

    @abstractmethod
    async def run(self, target: str, **kwargs: Any) -> list[Finding]:
        """
        Execute the module against a target URL.
        Must return a list of Findings (empty if none).
        """
        ...

    def add_finding(
        self,
        vector: str,
        severity: str,
        title: str,
        description: str,
        confidence: int,
        evidence: list[Any] | None = None,
        remediation: str | None = None,
        references: list[str] | None = None,
    ) -> Finding:
        from .result import AttackVector, Severity

        finding = Finding(
            module=self.name,
            vector=AttackVector(vector),
            severity=Severity(severity),
            title=title,
            description=description,
            target=self._current_target or "unknown",
            confidence=confidence,
            evidence=evidence or [],
            remediation=remediation,
            references=references or [],
        )
        self.findings.append(finding)
        self.logger.warning(
            "[%s] %s | %s | %s",
            severity.upper(),
            self.name,
            title,
            finding.target,
        )
        return finding

    @property
    def _current_target(self) -> str | None:
        return getattr(self, "__target", None)

    @_current_target.setter
    def _current_target(self, value: str) -> None:
        self.__target = value
