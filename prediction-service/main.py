from __future__ import annotations

import math
from typing import Any, Literal

from fastapi import FastAPI
from pydantic import BaseModel, Field


RecommendedAction = Literal[
    "add_stock_now",
    "add_stock_soon",
    "monitor",
    "buy_less",
    "do_not_buy",
    "use_before_expiry",
]

RiskLevel = Literal["low", "medium", "high"]


app = FastAPI(
    title="Ting Hao Stock Prediction Service",
    version="0.1.0",
    description="Rule-based stock action prediction for Ting Hao Smart Stock Planner.",
)


class StockPredictionRequest(BaseModel):
    ingredient: str | None = Field(default="Unknown ingredient")
    current_quantity: float | None = None
    unit: str | None = None
    minimum_stock: float | None = None
    stock_out_last_7_days: float | None = None
    stock_out_last_14_days: float | None = None
    stock_out_last_30_days: float | None = None
    expiry_days_remaining: int | None = None
    pending_po_quantity: float | None = None
    supplier_lead_time_days: int | None = None
    weekend_near: bool | None = False
    festival_near: bool | None = False


class CalculationSummary(BaseModel):
    average_daily_usage: float
    average_daily_usage_7d: float
    average_daily_usage_14d: float
    average_daily_usage_30d: float
    current_quantity: float
    minimum_stock: float
    pending_po_quantity: float


class StockPredictionResponse(BaseModel):
    ingredient: str
    recommended_action: RecommendedAction
    estimated_days_until_stockout: int | None
    suggested_quantity: float
    risk_level: RiskLevel
    confidence: float
    reason_codes: list[str]
    calculation_summary: CalculationSummary


@app.get("/health")
def health() -> dict[str, str]:
    return {
        "status": "ok",
        "service": "Ting Hao Stock Prediction Service",
    }


@app.post("/predict-stock-action", response_model=StockPredictionResponse)
def predict_stock_action(payload: StockPredictionRequest) -> StockPredictionResponse:
    current_quantity = clamp_non_negative(payload.current_quantity)
    minimum_stock = clamp_non_negative(payload.minimum_stock)
    minimum_stock_provided = payload.minimum_stock is not None and minimum_stock > 0
    pending_po_quantity = clamp_non_negative(payload.pending_po_quantity)
    supplier_lead_time_days = max(0, int(payload.supplier_lead_time_days or 0))

    avg_7d = average_daily_usage(payload.stock_out_last_7_days, 7)
    avg_14d = average_daily_usage(payload.stock_out_last_14_days, 14)
    avg_30d = average_daily_usage(payload.stock_out_last_30_days, 30)
    preferred_average = preferred_usage_average(avg_7d, avg_14d, avg_30d)
    adjusted_average = adjusted_usage_average(
        preferred_average,
        weekend_near=bool(payload.weekend_near),
        festival_near=bool(payload.festival_near),
    )

    reason_codes: list[str] = []
    if payload.weekend_near:
        reason_codes.append("weekend_demand_risk")
    if payload.festival_near:
        reason_codes.append("festival_demand_risk")

    estimated_days_until_stockout = estimate_days_until_stockout(
        current_quantity,
        adjusted_average,
    )

    recommended_action = choose_action(
        current_quantity=current_quantity,
        minimum_stock=minimum_stock,
        minimum_stock_provided=minimum_stock_provided,
        estimated_days_until_stockout=estimated_days_until_stockout,
        supplier_lead_time_days=supplier_lead_time_days,
        expiry_days_remaining=payload.expiry_days_remaining,
        has_usage_data=preferred_average > 0,
        reason_codes=reason_codes,
    )

    suggested_quantity = calculate_suggested_quantity(
        action=recommended_action,
        current_quantity=current_quantity,
        minimum_stock=minimum_stock,
        pending_po_quantity=pending_po_quantity,
        average_daily_usage=adjusted_average,
        supplier_lead_time_days=supplier_lead_time_days,
    )

    risk_level = determine_risk_level(
        action=recommended_action,
        estimated_days_until_stockout=estimated_days_until_stockout,
        supplier_lead_time_days=supplier_lead_time_days,
    )

    confidence = determine_confidence(
        action=recommended_action,
        has_usage_data=preferred_average > 0,
        has_14d_data=avg_14d > 0,
        has_7d_data=avg_7d > 0,
        has_30d_data=avg_30d > 0,
        reason_codes=reason_codes,
    )

    return StockPredictionResponse(
        ingredient=payload.ingredient or "Unknown ingredient",
        recommended_action=recommended_action,
        estimated_days_until_stockout=estimated_days_until_stockout,
        suggested_quantity=suggested_quantity,
        risk_level=risk_level,
        confidence=confidence,
        reason_codes=reason_codes,
        calculation_summary=CalculationSummary(
            average_daily_usage=round(preferred_average, 2),
            average_daily_usage_7d=round(avg_7d, 2),
            average_daily_usage_14d=round(avg_14d, 2),
            average_daily_usage_30d=round(avg_30d, 2),
            current_quantity=round(current_quantity, 2),
            minimum_stock=round(minimum_stock, 2),
            pending_po_quantity=round(pending_po_quantity, 2),
        ),
    )


def clamp_non_negative(value: Any) -> float:
    if value is None:
        return 0.0

    try:
        return max(0.0, float(value))
    except (TypeError, ValueError):
        return 0.0


def average_daily_usage(total_usage: float | None, days: int) -> float:
    usage = clamp_non_negative(total_usage)
    if days <= 0 or usage <= 0:
        return 0.0

    return usage / days


def preferred_usage_average(avg_7d: float, avg_14d: float, avg_30d: float) -> float:
    if avg_14d > 0:
        return avg_14d
    if avg_7d > 0:
        return avg_7d
    if avg_30d > 0:
        return avg_30d

    return 0.0


def adjusted_usage_average(
    preferred_average: float,
    *,
    weekend_near: bool,
    festival_near: bool,
) -> float:
    if preferred_average <= 0:
        return 0.0

    multiplier = 1.0
    if weekend_near:
        multiplier += 0.15
    if festival_near:
        multiplier += 0.25

    return preferred_average * multiplier


def estimate_days_until_stockout(
    current_quantity: float,
    average_daily_usage: float,
) -> int | None:
    if average_daily_usage <= 0:
        return None
    if current_quantity <= 0:
        return 0

    return max(1, math.ceil(current_quantity / average_daily_usage))


def choose_action(
    *,
    current_quantity: float,
    minimum_stock: float,
    minimum_stock_provided: bool,
    estimated_days_until_stockout: int | None,
    supplier_lead_time_days: int,
    expiry_days_remaining: int | None,
    has_usage_data: bool,
    reason_codes: list[str],
) -> RecommendedAction:
    high_stock = minimum_stock > 0 and current_quantity >= minimum_stock * 3
    very_high_stock = minimum_stock > 0 and current_quantity >= minimum_stock * 4
    expiry_known = expiry_days_remaining is not None
    expired = expiry_known and expiry_days_remaining < 0
    expiry_soon = expiry_known and 0 <= expiry_days_remaining <= 7
    stockout_soon = (
        estimated_days_until_stockout is not None
        and estimated_days_until_stockout <= supplier_lead_time_days + 2
    )

    if stockout_soon:
        reason_codes.append("stockout_soon")

    # Safety-first actions win before buying recommendations.
    if expired and current_quantity > 0:
        reason_codes.append("expired_stock_do_not_buy")
        return "do_not_buy"

    # Usable stock below its minimum should be replenished before lower-priority
    # expiry and overstock advice is considered.
    if minimum_stock_provided and current_quantity <= minimum_stock:
        reason_codes.append("below_minimum_stock")
        return "add_stock_now"

    if high_stock and expiry_soon:
        reason_codes.extend(["high_stock", "expiry_soon"])
        return "do_not_buy"

    if expiry_soon and current_quantity > 0:
        reason_codes.append("expiry_soon")
        return "use_before_expiry"

    if very_high_stock:
        reason_codes.append("very_high_stock")
        return "do_not_buy"

    if high_stock:
        reason_codes.append("high_stock")
        return "buy_less"

    if not has_usage_data:
        reason_codes.append("insufficient_usage_data")
        return "monitor"

    if stockout_soon:
        return "add_stock_soon"

    reason_codes.append("stock_level_acceptable")
    return "monitor"


def calculate_suggested_quantity(
    *,
    action: RecommendedAction,
    current_quantity: float,
    minimum_stock: float,
    pending_po_quantity: float,
    average_daily_usage: float,
    supplier_lead_time_days: int,
) -> float:
    if action in {"do_not_buy", "buy_less", "monitor", "use_before_expiry"}:
        return 0.0

    lead_time_buffer = average_daily_usage * max(supplier_lead_time_days + 7, 7)
    target_stock = max(minimum_stock * 2, minimum_stock + lead_time_buffer)
    needed_quantity = target_stock - current_quantity - pending_po_quantity

    suggested_quantity = max(0.0, needed_quantity)

    if action in {"add_stock_now", "add_stock_soon"} and suggested_quantity <= 0:
        suggested_quantity = max(
            (minimum_stock * 2) - current_quantity,
            minimum_stock,
            1.0,
        )

    return round(max(0.0, suggested_quantity), 2)


def determine_risk_level(
    *,
    action: RecommendedAction,
    estimated_days_until_stockout: int | None,
    supplier_lead_time_days: int,
) -> RiskLevel:
    if action in {"add_stock_now", "use_before_expiry", "do_not_buy"}:
        return "high"

    if action == "add_stock_soon":
        return "medium"

    if estimated_days_until_stockout is not None and estimated_days_until_stockout <= supplier_lead_time_days + 5:
        return "medium"

    return "low"


def determine_confidence(
    *,
    action: RecommendedAction,
    has_usage_data: bool,
    has_14d_data: bool,
    has_7d_data: bool,
    has_30d_data: bool,
    reason_codes: list[str],
) -> float:
    if not has_usage_data and action == "monitor":
        return 0.55

    confidence = 0.72
    if has_14d_data:
        confidence += 0.12
    if has_7d_data:
        confidence += 0.04
    if has_30d_data:
        confidence += 0.04
    if "below_minimum_stock" in reason_codes or "expiry_soon" in reason_codes:
        confidence += 0.04

    return round(min(confidence, 0.95), 2)
