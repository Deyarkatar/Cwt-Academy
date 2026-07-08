"""
Auto-discovery and registration of scanner modules.
"""
from __future__ import annotations

import importlib
import inspect
import pkgutil
from pathlib import Path
from typing import Any, TypeVar

from .base_module import BaseModule

T = TypeVar("T", bound=BaseModule)


class PluginManager:
    """Discovers and instantiates modules from the modules/ package tree."""

    def __init__(self, package_prefix: str = "modules") -> None:
        self.prefix = package_prefix
        self._registry: dict[str, type[BaseModule]] = {}
        self._discover()

    def _discover(self) -> None:
        """Recursively walk packages and register classes that subclass BaseModule."""
        try:
            pkg = importlib.import_module(self.prefix)
        except ImportError:
            return

        self._scan_package(pkg, self.prefix)

    def _scan_package(self, pkg: Any, prefix: str) -> None:
        for _, modname, ispkg in pkgutil.iter_modules(pkg.__path__, prefix=f"{prefix}."):
            if ispkg:
                try:
                    subpkg = importlib.import_module(modname)
                except Exception:
                    continue
                self._scan_package(subpkg, modname)
            else:
                try:
                    mod = importlib.import_module(modname)
                except Exception:
                    continue
                for _name, obj in inspect.getmembers(mod, inspect.isclass):
                    if issubclass(obj, BaseModule) and obj is not BaseModule and hasattr(obj, "name"):
                        self._registry[obj.name] = obj

    def list_modules(self) -> list[str]:
        return sorted(self._registry.keys())

    def get(self, name: str) -> type[BaseModule] | None:
        return self._registry.get(name)

    def instantiate(
        self,
        name: str,
        config: Any,
        client: Any,
    ) -> BaseModule | None:
        cls = self.get(name)
        if cls is None:
            return None
        return cls(config, client)
