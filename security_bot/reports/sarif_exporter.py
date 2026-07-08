"""
SARIF (Static Analysis Results Interchange Format) v2.1.0 exporter.
Compatible with GitHub Advanced Security, Azure DevOps, and VS Code SARIF viewers.
"""
from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from core.result import Finding, ScanSummary, Severity


class SARIFExporter:
    TOOL_NAME = "SecurityAssessmentBot"
    TOOL_VERSION = "1.0.0"

    SEVERITY_MAP = {
        Severity.CRITICAL: "error",
        Severity.HIGH: "error",
        Severity.MEDIUM: "warning",
        Severity.LOW: "note",
        Severity.INFO: "note",
    }

    def __init__(self, summary: ScanSummary) -> None:
        self.summary = summary

    def export(self) -> dict[str, Any]:
        rules = []
        results = []
        rule_ids: set[str] = set()

        for finding in self.summary.findings:
            rule_id = f"{finding.module}/{finding.vector.value}"
            if rule_id not in rule_ids:
                rule_ids.add(rule_id)
                rules.append({
                    "id": rule_id,
                    "name": finding.title,
                    "shortDescription": {"text": finding.title},
                    "fullDescription": {"text": finding.description},
                    "defaultConfiguration": {
                        "level": self.SEVERITY_MAP.get(finding.severity, "warning"),
                    },
                    "helpUri": finding.references[0] if finding.references else "",
                })

            result = {
                "ruleId": rule_id,
                "message": {"text": finding.description},
                "level": self.SEVERITY_MAP.get(finding.severity, "warning"),
                "locations": [{
                    "physicalLocation": {
                        "artifactLocation": {"uri": finding.target},
                        "region": {
                            "startLine": 1,
                            "startColumn": 1,
                        },
                    },
                }],
                "properties": {
                    "confidence": finding.confidence,
                    "module": finding.module,
                    "vector": finding.vector.value,
                    "remediation": finding.remediation or "",
                },
            }
            results.append(result)

        return {
            "$schema": "https://raw.githubusercontent.com/oasis-tcs/sarif-spec/master/Schemata/sarif-schema-2.1.0.json",
            "version": "2.1.0",
            "runs": [{
                "tool": {
                    "driver": {
                        "name": self.TOOL_NAME,
                        "version": self.TOOL_VERSION,
                        "rules": rules,
                    },
                },
                "results": results,
                "invocations": [{
                    "executionSuccessful": True,
                    "startTimeUtc": self.summary.started_at.isoformat() if self.summary.started_at else "",
                    "endTimeUtc": self.summary.finished_at.isoformat() if self.summary.finished_at else "",
                }],
            }],
        }

    def save(self, path: Path | str) -> Path:
        p = Path(path)
        p.write_text(json.dumps(self.export(), indent=2))
        return p
