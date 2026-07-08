"""
Pytest fixtures for the security bot test suite.
"""
from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import pytest

from core.config import ScannerConfig
from core.http_client import SafeClient
from core.result import Finding, Severity, AttackVector, ScanSummary


@pytest.fixture
def test_config() -> ScannerConfig:
    return ScannerConfig(
        safety=ScannerConfig.model_fields["safety"].annotation(
            allowed_hosts=["test-target.local"],
            allowed_ips=["127.0.0.0/8"],
            require_explicit_authorization=False,
            safe_mode=True,
            skip_destructive=True,
            max_requests_per_second=100.0,
        ),
        modules=["fuzz", "sqli", "xss"],
    )


@pytest.fixture
def mock_finding() -> Finding:
    return Finding(
        module="sqli",
        vector=AttackVector.SQL_INJECTION,
        severity=Severity.HIGH,
        title="SQL Injection in login form",
        description="Unsanitized input reaches SQL parser.",
        target="http://test-target.local/login",
        confidence=90,
    )


@pytest.fixture
def mock_summary(mock_finding: Finding) -> ScanSummary:
    summary = ScanSummary(target="http://test-target.local")
    summary.findings = [mock_finding]
    summary.modules_run = ["sqli"]
    return summary
