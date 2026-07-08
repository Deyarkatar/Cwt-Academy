"""
Data models for scan results and findings.
"""
from __future__ import annotations

from datetime import datetime
from enum import Enum
from typing import Any
from uuid import uuid4

from pydantic import BaseModel, Field


class Severity(str, Enum):
    CRITICAL = "critical"
    HIGH = "high"
    MEDIUM = "medium"
    LOW = "low"
    INFO = "info"


class AttackVector(str, Enum):
    SQL_INJECTION = "sqli"
    CROSS_SITE_SCRIPTING = "xss"
    COMMAND_INJECTION = "cmdi"
    PATH_TRAVERSAL = "path_traversal"
    AUTHENTICATION_BYPASS = "auth_bypass"
    CREDENTIAL_STUFFING = "credential_stuffing"
    RATE_LIMIT_ABUSE = "rate_limit_abuse"
    INSECURE_DIRECT_OBJECT_REFERENCE = "idor"
    SERVER_SIDE_REQUEST_FORGERY = "ssrf"
    XML_EXTERNAL_ENTITY = "xxe"
    OPEN_REDIRECT = "open_redirect"
    SECURITY_MISCONFIGURATION = "misconfiguration"
    INFORMATION_DISCLOSURE = "info_disclosure"
    FUZZING = "fuzzing"
    UNKNOWN = "unknown"


class Evidence(BaseModel):
    request: str | None = None
    response_snippet: str | None = None
    trigger_payload: str | None = None
    description: str
    screenshot_hint: str | None = None


class Finding(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4())[:8])
    module: str
    vector: AttackVector
    severity: Severity
    title: str
    description: str
    target: str
    confidence: int = Field(ge=0, le=100, default=50)
    evidence: list[Evidence] = Field(default_factory=list)
    remediation: str | None = None
    references: list[str] = Field(default_factory=list)
    timestamp: datetime = Field(default_factory=datetime.utcnow)
    meta: dict[str, Any] = Field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return self.model_dump(mode="json")


class ScanSummary(BaseModel):
    scan_id: str = Field(default_factory=lambda: str(uuid4())[:12])
    target: str
    started_at: datetime = Field(default_factory=datetime.utcnow)
    finished_at: datetime | None = None
    modules_run: list[str] = Field(default_factory=list)
    findings: list[Finding] = Field(default_factory=list)
    errors: list[str] = Field(default_factory=list)
    stats: dict[str, Any] = Field(default_factory=dict)

    @property
    def duration_seconds(self) -> float | None:
        if self.finished_at:
            return (self.finished_at - self.started_at).total_seconds()
        return None

    def critical_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == Severity.CRITICAL)

    def high_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == Severity.HIGH)

    def medium_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == Severity.MEDIUM)

    def low_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == Severity.LOW)
