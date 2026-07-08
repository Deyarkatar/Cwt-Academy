"""
Orchestrator: coordinates discovery, module execution, and reporting.
"""
from __future__ import annotations

import asyncio
import logging
from datetime import datetime
from typing import Any

from rich.console import Console
from rich.logging import RichHandler
from rich.progress import Progress, SpinnerColumn, TextColumn

from .config import ScannerConfig
from .http_client import SafeClient
from .plugin_manager import PluginManager
from .result import Finding, ScanSummary, Severity

console = Console()


class ScanEngine:
    """
    The central brain. Validates scope, runs modules, aggregates
    findings, and generates the final report.
    """

    def __init__(self, config: ScannerConfig):
        self.config = config
        self.plugins = PluginManager(package_prefix="modules")
        self.summary = ScanSummary(target="")
        self.logger = self._setup_logging()

    def _setup_logging(self) -> logging.Logger:
        logging.basicConfig(
            level=getattr(logging, self.config.log_level.upper(), logging.INFO),
            format="%(message)s",
            datefmt="[%X]",
            handlers=[RichHandler(console=console, markup=True)],
        )
        return logging.getLogger("security_bot.engine")

    async def scan(self, target: str, modules: list[str] | None = None) -> ScanSummary:
        if not self.config.safety.is_authorized(target):
            raise ValueError(f"Target {target} is not in the authorized scope.")

        self.summary = ScanSummary(target=target)
        self.summary.modules_run = modules or self.config.modules

        self.logger.info("[bold green]Starting scan[/] against %s", target)
        self.logger.info("Loaded modules: %s", ", ".join(self.summary.modules_run))

        async with SafeClient(self.config) as client:
            with Progress(
                SpinnerColumn(),
                TextColumn("[progress.description]{task.description}"),
                console=console,
            ) as progress:
                for mod_name in self.summary.modules_run:
                    await self._run_module(mod_name, target, client, progress)

        self.summary.finished_at = datetime.utcnow()
        self._print_summary()
        return self.summary

    async def _run_module(
        self,
        name: str,
        target: str,
        client: SafeClient,
        progress: Progress,
    ) -> None:
        mod_cls = self.plugins.get(name)
        if mod_cls is None:
            self.logger.warning("Module '%s' not found; skipping.", name)
            self.summary.errors.append(f"Module '{name}' not found")
            return

        task = progress.add_task(f"Running {name}...", total=None)
        instance = mod_cls(self.config, client)
        try:
            findings = await instance.run(target)
            self.summary.findings.extend(findings)
            self.logger.info(
                "Module [bold]%s[/] finished — %d finding(s)",
                name,
                len(findings),
            )
        except Exception as exc:
            self.logger.error("Module %s crashed: %s", name, exc)
            self.summary.errors.append(f"{name}: {exc}")
        finally:
            progress.remove_task(task)

    def _print_summary(self) -> None:
        console.rule("[bold cyan]Scan Summary[/]")
        console.print(f"Scan ID  : {self.summary.scan_id}")
        console.print(f"Target   : {self.summary.target}")
        console.print(f"Duration : {self.summary.duration_seconds:.2f}s")
        console.print()
        sev_colors = {
            Severity.CRITICAL: "red",
            Severity.HIGH: "bright_red",
            Severity.MEDIUM: "yellow",
            Severity.LOW: "blue",
            Severity.INFO: "dim",
        }
        for sev in Severity:
            count = sum(1 for f in self.summary.findings if f.severity == sev)
            if count:
                console.print(f"[{sev_colors[sev]}]{sev.value.upper():8}: {count}[/]")
        console.rule()
