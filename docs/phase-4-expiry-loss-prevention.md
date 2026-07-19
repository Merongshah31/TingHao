# TingHao Agent Phase 4 Expiry Loss Prevention

Date: 2026-06-29

## Summary

Phase 4 adds measurable waste-prevention impact to TingHao Agent. The agent scans ingredients expiring within 7 days, excludes expired or zero-stock ingredients, calculates potential RM loss from real quantity and cost price, asks Qwen for a short bakery usage recommendation, and stores the result for admin review.

## Business Impact

The dashboard and Agent Console can now show:

```text
RM X at risk from Y ingredients expiring this week.
```

This helps judges see a concrete Track 4 Autopilot Agent outcome: the system identifies avoidable expiry loss and recommends practical actions before waste happens.

## Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/agent/expiry-loss` | `agent.expiry-loss` | Admin, Staff | View expiry loss dashboard and recommendations |
| POST | `/agent/expiry-loss/scan` | `agent.expiry-loss.scan` | Admin | Run expiry loss scan |
| GET | `/expiry-loss-recommendations/{expiryLossRecommendation}` | `expiry-loss-recommendations.show` | Admin, Staff | View recommendation detail |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/review` | `expiry-loss-recommendations.review` | Admin | Mark reviewed |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/dismiss` | `expiry-loss-recommendations.dismiss` | Admin | Dismiss recommendation |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/complete` | `expiry-loss-recommendations.complete` | Admin | Mark completed |

## Database

New table:

- `expiry_loss_recommendations`

Important fields:

- `agent_run_id`
- `ingredient_id`
- `quantity_at_risk`
- `unit`
- `cost_price`
- `potential_loss`
- `expiry_date`
- `days_until_expiry`
- `recommendation_title`
- `recommendation_body`
- `status`: `active`, `reviewed`, `dismissed`, or `completed`
- `reviewed_by`

## Agent Tools

Each scan creates an `agent_runs` record with `input_type=expiry_loss_scan` and logs these tool calls:

- `scan_expiring_ingredients`
- `calculate_expiry_loss`
- `generate_expiry_recommendation`
- `save_expiry_recommendation`

## Safety Rules

- Qwen is called only server-side through `QwenClient`.
- Qwen API keys are never exposed in Blade or JavaScript.
- Expired ingredients are excluded from recommendation generation.
- The agent does not invent sales numbers, POS demand, or unavailable recipe data.
- If cost price is missing, RM loss is left null and the recommendation explains that cost data is unavailable.
- Mock mode generates deterministic fallback recommendations when no Qwen key is configured.

## Demo Seed

Seeder data includes `Unsalted Butter` expiring in 5 days with 12 kg at RM18 cost price, giving a demo impact of RM216 at risk.
