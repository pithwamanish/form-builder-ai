# Use Astral UV official image for ultra-fast Python package installation
FROM ghcr.io/astral-sh/uv:latest AS uv_bin

FROM python:3.11-slim

WORKDIR /app

# Copy uv binary from official image
COPY --from=uv_bin /uv /uv

# Copy dependency definition files
COPY pyproject.toml requirements.txt ./

# Install dependencies using uv
RUN /uv pip install --system -r requirements.txt

# Copy application source code
COPY . .

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
