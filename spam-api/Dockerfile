FROM python:3.11-slim

WORKDIR /app

# Install dependencies first (Docker cache layer).
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download model during build to avoid cold-start delay at runtime.
RUN python -c "from transformers import pipeline; pipeline('text-classification', model='AventIQ-AI/bert-spam-detection')"

COPY main.py .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:8000/health')"

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
