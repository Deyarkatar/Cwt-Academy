"""
Delta / diff reporter: compares two scan summaries and reports
new, fixed, and unchanged findings.
"""
from __future__ import annotations

from typing import Any

from core.result import Finding, ScanSummary


class DiffReporter:
    def __init__(self, baseline: ScanSummary, current: ScanSummary) -> None:
        self.baseline = baseline
        self.current = current

    def compare(self) -> dict[str, Any]:
        baseline_keys = {self._key(f) for f in self.baseline.findings}
        current_keys = {self._key(f) for f in self.current.findings}

        new_keys = current_keys - baseline_keys
        fixed_keys = baseline_keys - current_keys
        unchanged_keys = baseline_keys & current_keys

        new_findings = [f for f in self.current.findings if self._key(f) in new_keys]
        fixed_findings = [f for f in self.baseline.findings if self._key(f) in fixed_keys]
        unchanged_findings = [f for f in self.current.findings if self._key(f) in unchanged_keys]

        return {
            "new": [f.to_dict() for f in new_findings],
            "fixed": [f.to_dict() for f in fixed_findings],
            "unchanged": [f.to_dict() for f in unchanged_findings],
            "summary": {
                "baseline_total": len(self.baseline.findings),
                "current_total": len(self.current.findings),
                "new_count": len(new_findings),
                "fixed_count": len(fixed_findings),
                "unchanged_count": len(unchanged_findings),
            },
        }

    @staticmethod
    def _key(finding: Finding) -> str:
        # Composite key for comparison
        return f"{finding.module}:{finding.vector.value}:{finding.title}:{finding.target}"
