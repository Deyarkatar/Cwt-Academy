"""
Tests for the ScanEngine orchestrator.
"""
from __future__ import annotations

import pytest

from core.engine import ScanEngine
from core.config import ScannerConfig


class TestScanEngine:
    def test_engine_initialization(self, test_config: ScannerConfig) -> None:
        engine = ScanEngine(test_config)
        assert engine.config == test_config
        assert engine.summary.target == ""

    def test_module_discovery(self, test_config: ScannerConfig) -> None:
        engine = ScanEngine(test_config)
        modules = engine.plugins.list_modules()
        # At minimum, modules we created should be discoverable if importable
        assert isinstance(modules, list)
