# Ting Hao Stock Prediction Service

Lightweight FastAPI service for Ting Hao Smart Stock Planner predictions.

This service is intentionally rule-based for the first MVP. Laravel remains responsible for UI, database records, inventory workflows, purchase orders, approval, and audit logs. This service only predicts the stock action: when to buy, buy soon, buy less, avoid buying, monitor, or use stock before expiry.

Qwen Cloud is not called here. In a later phase, Qwen can explain the prediction result in simple business language, but raw forecasting calculations should stay deterministic and low-cost.

## Install

```bash
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
```

On macOS/Linux:

```bash
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

## Run Locally

```bash
uvicorn main:app --reload --port 8001
```

Health check:

```bash
curl http://127.0.0.1:8001/health
```

## Predict Stock Action

```bash
curl -X POST http://127.0.0.1:8001/predict-stock-action \
  -H "Content-Type: application/json" \
  -d '{
    "ingredient": "Sugar",
    "current_quantity": 3,
    "unit": "kg",
    "minimum_stock": 20,
    "stock_out_last_7_days": 28,
    "stock_out_last_14_days": 50,
    "stock_out_last_30_days": 90,
    "expiry_days_remaining": 30,
    "pending_po_quantity": 0,
    "supplier_lead_time_days": 2,
    "weekend_near": true,
    "festival_near": false
  }'
```

Sample response:

```json
{
  "ingredient": "Sugar",
  "recommended_action": "add_stock_now",
  "estimated_days_until_stockout": 1,
  "suggested_quantity": 53.96,
  "risk_level": "high",
  "confidence": 0.95,
  "reason_codes": [
    "weekend_demand_risk",
    "stockout_soon",
    "below_minimum_stock"
  ],
  "calculation_summary": {
    "average_daily_usage": 3.57,
    "average_daily_usage_7d": 4.0,
    "average_daily_usage_14d": 3.57,
    "average_daily_usage_30d": 3.0,
    "current_quantity": 3.0,
    "minimum_stock": 20.0,
    "pending_po_quantity": 0.0
  }
}
```

## Prediction Logic

The service calculates:

- `average_daily_usage_7d = stock_out_last_7_days / 7`
- `average_daily_usage_14d = stock_out_last_14_days / 14`
- `average_daily_usage_30d = stock_out_last_30_days / 30`
- preferred average daily usage: 14 days first, then 7 days, then 30 days
- adjusted demand when weekend or festival risk is near
- estimated days until stockout
- suggested quantity
- risk level
- confidence
- reason codes

Recommended actions:

- `add_stock_now`
- `add_stock_soon`
- `monitor`
- `buy_less`
- `do_not_buy`
- `use_before_expiry`

The rule engine handles missing values safely. If usage data is missing or zero, it returns `monitor` with `insufficient_usage_data`, unless stock is already below the minimum.

Stock action safety rules also ensure that:

- expired stock is flagged as a do-not-buy condition for Laravel to present as `Review Expired Stock`
- usable stock below its minimum level takes priority over lower-value expiry advice
- add-stock actions always return a positive fallback quantity using `max(minimum_stock * 2 - current_quantity, minimum_stock)` when the calculated quantity is zero
- non-purchase actions keep a zero API quantity, which Laravel presents as business advice rather than a meaningless suggested purchase amount

Run the rule tests locally with:

```bash
python -m unittest test_main.py
```

## Why No Training Data Is Required

This MVP does not train a machine learning model. The goal is to provide transparent, low-cost stock planning decisions from existing inventory numbers. Rule-based logic is easier to audit, easier to demo locally, and safer before Ting Hao has enough historical sales, stock movement, festival, and supplier lead-time data for real ML training.

## Future Phase

Later, Laravel can send the prediction output to Qwen only for business-friendly explanation text. Qwen should not replace the deterministic forecasting calculation.


cd "C:\laravel\TingHao - Copy\prediction-service"
uvicorn main:app --reload --port 8001
