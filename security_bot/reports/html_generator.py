"""
Generates a standalone HTML report with severity filtering,
evidence expansion, and CVSS-style scoring hints.
"""
from __future__ import annotations

from pathlib import Path

from core.result import ScanSummary, Severity


TEMPLATE = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Security Assessment Report — {{ scan_id }}</title>
<style>
body { font-family: system-ui, sans-serif; margin: 2rem; background:#0b0f19; color:#e2e8f0; }
h1,h2 { color:#f8fafc; }
.summary { display:grid; grid-template-columns: repeat(5,1fr); gap:1rem; margin:1.5rem 0; }
.card { padding:1rem; border-radius:8px; text-align:center; font-weight:bold; }
.critical { background:#7f1d1d; }
.high     { background:#991b1b; }
.medium   { background:#b45309; }
.low      { background:#1e40af; }
.info     { background:#334155; }
table { width:100%; border-collapse:collapse; margin-top:1rem; }
th,td { padding:.6rem; border-bottom:1px solid #334155; text-align:left; }
th { color:#94a3b8; }
tr:hover { background:#1e293b; }
.evidence { background:#0f172a; padding:1rem; border-radius:6px; margin:.5rem 0; }
pre { white-space:pre-wrap; word-break:break-word; margin:.3rem 0; }
.filter-btn { cursor:pointer; padding:.4rem .8rem; border:none; border-radius:4px; margin-right:.4rem; }
</style>
</head>
<body>
<h1>Security Assessment Report</h1>
<p>Target: <strong>{{ target }}</strong> | Scan ID: {{ scan_id }} | Duration: {{ duration }}s</p>
<div class="summary">
  <div class="card critical">CRITICAL<br>{{ critical }}</div>
  <div class="card high">HIGH<br>{{ high }}</div>
  <div class="card medium">MEDIUM<br>{{ medium }}</div>
  <div class="card low">LOW<br>{{ low }}</div>
  <div class="card info">INFO<br>{{ info }}</div>
</div>
<h2>Findings</h2>
<table>
<thead>
<tr><th>Severity</th><th>Module</th><th>Vector</th><th>Title</th><th>Confidence</th><th>Target</th></tr>
</thead>
<tbody>
{{ rows }}
</tbody>
</table>
<h2>Evidence Details</h2>
{{ evidence_sections }}
</body>
</html>
"""

ROW_TEMPLATE = (
    "<tr class='sev-{{ sev }}'>"
    "<td><span class='card {{ sev }}' style='display:inline-block;padding:.2rem .5rem;font-size:.8rem;'>{{ severity }}</span></td>"
    "<td>{{ module }}</td><td>{{ vector }}</td><td>{{ title }}</td>"
    "<td>{{ confidence }}%</td><td>{{ target }}</td></tr>"
)

EVIDENCE_TEMPLATE = (
    "<div class='evidence' id='ev-{{ id }}'>"
    "<h3>{{ title }}</h3><p>{{ description }}</p>"
    "<pre><strong>Request:</strong>\n{{ request }}</pre>"
    "<pre><strong>Response:</strong>\n{{ response }}</pre>"
    "{% if remediation %}<pre><strong>Remediation:</strong>\n{{ remediation }}</pre>{% endif %}"
    "</div>"
)


class HTMLReportGenerator:
    def __init__(self, summary: ScanSummary) -> None:
        self.summary = summary

    def render(self) -> str:
        from jinja2 import Template

        t = Template(TEMPLATE)
        rows = ""
        evidence_sections = ""
        for finding in self.summary.findings:
            rows += Template(ROW_TEMPLATE).render(
                sev=finding.severity.value,
                severity=finding.severity.value.upper(),
                module=finding.module,
                vector=finding.vector.value,
                title=finding.title,
                confidence=finding.confidence,
                target=finding.target,
            )
            for ev in finding.evidence:
                evidence_sections += Template(EVIDENCE_TEMPLATE).render(
                    id=finding.id,
                    title=finding.title,
                    description=ev.description,
                    request=ev.request or "N/A",
                    response=ev.response_snippet or "N/A",
                    remediation=finding.remediation or "",
                )

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
            evidence_sections=evidence_sections or "<p>No evidence recorded.</p>",
        )

    def save(self, path: Path | str) -> Path:
        p = Path(path)
        p.write_text(self.render())
        return p
