# Security Assessment Bot — Production Container
# Multi-stage build for minimal attack surface
FROM python:3.12-slim-bookworm AS builder

WORKDIR /build
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    libffi-dev \
    libpango-1.0-0 \
    libpangoft2-1.0-0 \
    libcairo2 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir --user -r requirements.txt

# --- Runtime stage ---
FROM python:3.12-slim-bookworm AS runtime

LABEL maintainer="security-team@example.com"
LABEL description="Security Assessment & Chaos Engineering Bot"

# Create non-root user
RUN groupadd -r scanner && useradd -r -g scanner -d /app -s /sbin/nologin scanner

# Install runtime deps for weasyprint (PDF generation)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpango-1.0-0 \
    libpangoft2-1.0-0 \
    libcairo2 \
    libgdk-pixbuf2.0-0 \
    libffi-dev \
    shared-mime-info \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy Python packages from builder
COPY --from=builder /root/.local /home/scanner/.local
ENV PATH=/home/scanner/.local/bin:$PATH

# Copy application code
COPY --chown=scanner:scanner core/ ./core/
COPY --chown=scanner:scanner modules/ ./modules/
COPY --chown=scanner:scanner reports/ ./reports/
COPY --chown=scanner:scanner utils/ ./utils/
COPY --chown=scanner:scanner payloads/ ./payloads/
COPY --chown=scanner:scanner main.py .

# Ensure Python can find modules
ENV PYTHONPATH=/app

USER scanner

ENTRYPOINT ["python", "main.py"]
CMD ["--help"]
