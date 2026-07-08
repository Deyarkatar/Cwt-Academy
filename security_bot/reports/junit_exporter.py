"""
JUnit XML exporter for CI integration (Jenkins, GitLab, GitHub Actions).
Each finding becomes a failed test case.
"""
from __future__ import annotations

from pathlib import Path
from xml.etree.ElementTree import Element, SubElement, tostring

from core.result import Finding, ScanSummary, Severity


class JUnitExporter:
    def __init__(self, summary: ScanSummary) -> None:
        self.summary = summary

    def export(self) -> str:
        testsuite = Element("testsuite")
        testsuite.set("name", "SecurityAssessmentBot")
        testsuite.set("tests", str(len(self.summary.findings)))
        testsuite.set("failures", str(len(self.summary.findings)))
        testsuite.set("errors", "0")
        testsuite.set("time", str(round(self.summary.duration_seconds or 0, 2)))

        for finding in self.summary.findings:
            testcase = SubElement(testsuite, "testcase")
            testcase.set("name", f"[{finding.severity.value.upper()}] {finding.title}")
            testcase.set("classname", f"{finding.module}.{finding.vector.value}")
            testcase.set("time", "0")

            failure = SubElement(testcase, "failure")
            failure.set("type", finding.vector.value)
            failure.text = (
                f"Severity: {finding.severity.value.upper()}\n"
                f"Confidence: {finding.confidence}%\n"
                f"Target: {finding.target}\n"
                f"Description: {finding.description}\n"
                f"Remediation: {finding.remediation or 'N/A'}\n"
            )

        # Add a passing dummy test if no findings (so CI doesn't fail on clean scans)
        if not self.summary.findings:
            testcase = SubElement(testsuite, "testcase")
            testcase.set("name", "No findings detected")
            testcase.set("classname", "scan.clean")
            testcase.set("time", "0")

        return '<?xml version="1.0" encoding="UTF-8"?>\n' + tostring(testsuite, encoding="unicode")

    def save(self, path: Path | str) -> Path:
        p = Path(path)
        p.write_text(self.export())
        return p
