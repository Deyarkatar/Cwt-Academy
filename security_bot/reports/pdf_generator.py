"""
PDF report generator using Jinja2 + WeasyPrint (or html-to-pdf fallback).
Requires: pip install weasyprint
"""
from __future__ import annotations

from pathlib import Path

from core.result import ScanSummary, Severity


PDF_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Helvetica, Arial, sans-serif; margin: 40px; color: #1a202c; }
h1 { color: #c53030; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
h2 { color: #2d3748; margin-top: 30px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #cbd5e0; padding: 8px 12px; text-align: left; font-size: 12px; }
th { background: #edf2f7; color: #4a5568; }
.critical { background: #fed7d7; }
.high { background: #feebc8; }
.medium { background: #fefcbf; }
.low { background: #bee3f8; }
.info { background: #e2e8f0; }
.meta { color: #718096; font-size: 11px; }
</style>
</head>
<body>
<h1>Security Assessment Report</h1>
<p class="meta">Scan ID: {{ scan_id }} | Target: {{ target }} | Duration: {{ duration }}s</p>

<h2>Severity Summary</h2>
<table>
<tr><th>Severity</th><th>Count</th></tr>
<tr class="critical"><td>CRITICAL</td><td>{{ critical }}</td></tr>
<tr class="high"><td>HIGH</td><td>{{ high }}</td></tr>
<tr class="medium"><td>MEDIUM</td><td>{{ medium }}</td></tr>
<tr class="low"><td>LOW</td><td>{{ low }}</td></tr>
<tr class="info"><td>INFO</td><td>{{ info }}</td></tr>
</table>

<h2>Findings</h2>
<table>
<tr>
  <th>ID</th><th>Severity</th><th>Module</th><th>Vector</th>
  <th>Title</th><th>Confidence</th><th>Target</th>
</tr>
{{ rows }}
</table>

<h2>Detailed Evidence</h2>
{{ evidence }}
</body>
</html>
"""


class PDFReportGenerator:
    def __init__(self, summary: ScanSummary) -> None:
        self.summary = summary

    def render_html(self) -> str:
        from jinja2 import Template
        t = Template(PDF_TEMPLATE)
        rows = ""
        evidence_html = ""
        for finding in self.summary.findings:
            rows += (
                f'<tr class="{finding.severity.value}">'
                f'<td>{finding.id}</td>'
                f'<td>{finding.severity.value.upper()}</td>'
                f'<td>{finding.module}</td>'
                f'<td>{finding.vector.value}</td>'
                f'<td>{finding.title}</td>'
                f'<td>{finding.confidence}%</td>'
                f'<td>{finding.target}</td></tr>'
            )
            for ev in finding.evidence:
                evidence_html += f"<h3>{finding.title}</h3><pre>{ev.description}</pre>"
                if ev.request:
                    evidence_html += f"<p><strong>Request:</strong></p><pre>{ev.request[:2000]}</pre>"
                if ev.response_snippet:
                    evidence_html += f"<p><strong>Response:</strong></p><pre>{ev.response_snippet[:2000]}</pre>"

        return t.render(
            scan_id=self.summary.scan_id,
            target=self.summary.target,
            duration=round(self.summary.duration_seconds or 0, 2),
            critical=self.summary.critical_count(),
            high=self.summary.high_count(),
            medium=self.summary.medium_count(),
            low=self.summary.low_count(),
            info=sum(1 for f in self.summary.findings if f.severity == Severity.INFO),
            rows=rows,
            evidence=evidence_html or "<p>No evidence recorded.</p>",
        )

    def save_pdf(self, path: Path | str) -> Path:
        try:
            from weasyprint import HTML
            html_str = self.render_html()
            p = Path(path)
            HTML(string=html_str).write_pdf(str(p))
            return p
        except ImportError:
            raise RuntimeError(
                "PDF generation requires 'weasyprint'. Install it: pip install weasyprint"
            )
