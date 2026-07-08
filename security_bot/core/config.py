"""
Configuration with mandatory safety guards.
All targets must be explicitly authorized.
"""
from __future__ import annotations

import ipaddress
from pathlib import Path
from typing import Any

import yaml
from pydantic import BaseModel, Field, field_validator, model_validator


class SafetyGuard(BaseModel):
    allowed_hosts: list[str] = Field(default_factory=list)
    allowed_ips: list[str] = Field(default_factory=list)
    block_public_cloud_ips: bool = True
    max_requests_per_second: float = 10.0
    max_concurrent_requests: int = 20
    connection_timeout: float = 10.0
    request_timeout: float = 30.0
    safe_mode: bool = True
    skip_destructive: bool = True
    require_explicit_authorization: bool = True

    @field_validator("allowed_hosts")
    @classmethod
    def _normalize_hosts(cls, v: list[str]) -> list[str]:
        return [h.lower().strip().lstrip("*.") for h in v if h.strip()]

    def is_authorized(self, url: str) -> bool:
        from urllib.parse import urlparse

        parsed = urlparse(url)
        host = (parsed.hostname or "").lower()

        if not host:
            return False

        for allowed in self.allowed_hosts:
            if host == allowed or host.endswith("." + allowed):
                return True

        try:
            addr = ipaddress.ip_address(host)
            for cidr in self.allowed_ips:
                if addr in ipaddress.ip_network(cidr, strict=False):
                    return True
        except ValueError:
            pass

        return False


class ScannerConfig(BaseModel):
    name: str = "Security Assessment Bot"
    version: str = "1.0.0"
    safety: SafetyGuard = Field(default_factory=SafetyGuard)
    proxy: str | None = None
    user_agent: str = (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36"
    )
    output_dir: Path = Field(default=Path("./reports"))
    log_level: str = "INFO"
    modules: list[str] = Field(default_factory=lambda: ["sqli", "xss", "fuzz", "auth"])
    custom_headers: dict[str, str] = Field(default_factory=dict)
    auth_cookie: str | None = None
    auth_bearer: str | None = None
    crawl_depth: int = 3
    max_urls: int = 500
    payload_file: Path | None = None

    @model_validator(mode="after")
    def _check_safety(self) -> ScannerConfig:
        if self.safety.require_explicit_authorization:
            if not self.safety.allowed_hosts and not self.safety.allowed_ips:
                raise ValueError(
                    "No authorized targets configured. "
                    "Set allowed_hosts or allowed_ips before running any scan."
                )
        return self

    @classmethod
    def from_yaml(cls, path: Path | str) -> ScannerConfig:
        with open(path, "r") as f:
            data = yaml.safe_load(f)
        return cls.model_validate(data)

    def to_yaml(self, path: Path | str) -> None:
        with open(path, "w") as f:
            yaml.safe_dump(self.model_dump(mode="json"), f, sort_keys=False)
