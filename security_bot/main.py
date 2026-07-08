#!/usr/bin/env python3
"""
Security Assessment & Chaos Engineering Bot
---------------------------------------------
Authorized penetration testing framework.
Use ONLY against targets you own or have explicit written permission to test.
"""
from __future__ import annotations

import asyncio
import sys
from pathlib import Path

import click

from core.config import ScannerConfig
from core.engine import ScanEngine


@click.group()
def cli() -> None:
    """Security Assessment & Chaos Engineering Bot."""
    pass


@cli.command()
@click.argument("target")
@click.option("--config", "-c", type=click.Path(exists=True), help="YAML config file")
@click.option("--module", "-m", multiple=True, help="Module(s) to run (repeatable)")
@click.option("--host", "-h", multiple=True, help="Authorize host (repeatable)")
@click.option("--output", "-o", type=click.Path(), help="Output directory for reports")
@click.option("--safe/--no-safe", default=True, help="Safe mode (skip destructive tests)")
@click.option("--rate", default=10.0, help="Max requests per second")
def scan(
    target: str,
    config: str | None,
    module: tuple[str, ...],
    host: tuple[str, ...],
    output: str | None,
    safe: bool,
    rate: float,
) -> None:
    """Run a security scan against TARGET."""
    if config:
        cfg = ScannerConfig.from_yaml(config)
    else:
        from core.config import SafetyGuard
        cfg = ScannerConfig(
            safety=SafetyGuard(
                allowed_hosts=list(host) if host else ["example.com"],
                require_explicit_authorization=bool(host),
            ),
        )

    if host:
        cfg.safety.allowed_hosts = list(host)
    if module:
        cfg.modules = list(module)
    if output:
        cfg.output_dir = Path(output)
    cfg.safety.safe_mode = safe
    cfg.safety.max_requests_per_second = rate

    engine = ScanEngine(cfg)
    summary = asyncio.run(engine.scan(target, modules=cfg.modules))

    # Save JSON report
    cfg.output_dir.mkdir(parents=True, exist_ok=True)
    report_path = cfg.output_dir / f"report_{summary.scan_id}.json"
    report_path.write_text(summary.model_dump_json(indent=2))
    click.echo(f"Report written to {report_path}")


@cli.command()
@click.argument("path", type=click.Path())
def init(path: str) -> None:
    """Generate a starter config file."""
    from core.config import SafetyGuard
    cfg = ScannerConfig(
        safety=SafetyGuard(allowed_hosts=["example.com"]),
    )
    cfg.to_yaml(path)
    click.echo(f"Starter config written to {path}")


if __name__ == "__main__":
    sys.path.insert(0, str(Path(__file__).parent))
    cli()
