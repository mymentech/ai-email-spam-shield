"""
AI Email Spam Shield — FastAPI Microservice
Hugging Face model: AventIQ-AI/bert-spam-detection

Author: Mozammel Haque / MymenTech (https://www.mymentech.com)
License: GPL-2.0-or-later
"""

from contextlib import asynccontextmanager
from typing import Optional
import logging
import os

from fastapi import FastAPI, HTTPException, Header
from pydantic import BaseModel
from transformers import pipeline

logger = logging.getLogger("aiess")
logging.basicConfig(level=logging.INFO)

# Global classifier — loaded once at startup.
classifier = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load model on startup, release on shutdown."""
    global classifier
    model_name = os.getenv("MODEL_NAME", "AventIQ-AI/bert-spam-detection")
    logger.info(f"Loading model: {model_name}")
    classifier = pipeline("text-classification", model=model_name)
    logger.info("Model loaded and ready.")
    yield
    classifier = None
    logger.info("Model released.")


app = FastAPI(
    title="AI Email Spam Shield API",
    description="Spam detection microservice by MymenTech",
    version="1.0.0",
    lifespan=lifespan,
)

# Optional API key auth via environment variable.
API_KEY = os.getenv("AIESS_API_KEY", "")


def verify_api_key(authorization: Optional[str] = None) -> None:
    """Check bearer token if API_KEY is configured."""
    if not API_KEY:
        return  # No key configured — open access (local network only).
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing Authorization header.")
    token = authorization.removeprefix("Bearer ").strip()
    if token != API_KEY:
        raise HTTPException(status_code=403, detail="Invalid API key.")


class PredictRequest(BaseModel):
    text: str


class PredictResponse(BaseModel):
    spam_probability: float
    label: str


@app.get("/health")
async def health() -> dict:
    """Health check — returns model load status."""
    return {"status": "ok", "model_loaded": classifier is not None}


@app.post("/predict", response_model=PredictResponse)
async def predict(
    request: PredictRequest,
    authorization: Optional[str] = Header(default=None),
) -> PredictResponse:
    """
    Classify text as spam or ham.

    Request body:
        {"text": "Email subject and body combined"}

    Response:
        {"spam_probability": 0.92, "label": "spam"}
    """
    verify_api_key(authorization)

    if classifier is None:
        raise HTTPException(status_code=503, detail="Model not loaded.")

    # Truncate to 2000 chars (well within BERT's 512-token limit).
    text = request.text[:2000]

    result = classifier(text)[0]

    # The model returns label "SPAM" or "HAM" with a confidence score.
    label         = result["label"].lower()  # "spam" or "ham"
    raw_score     = float(result["score"])   # confidence for the predicted label

    # Normalize to spam_probability regardless of predicted label.
    spam_probability = raw_score if label == "spam" else 1.0 - raw_score

    return PredictResponse(
        spam_probability=round(spam_probability, 4),
        label="spam" if spam_probability >= 0.5 else "ham",
    )
