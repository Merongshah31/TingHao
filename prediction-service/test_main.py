import unittest

from main import StockPredictionRequest, predict_stock_action


class StockPredictionRulesTest(unittest.TestCase):
    def test_add_stock_fallback_is_positive_when_pending_quantity_covers_target(self) -> None:
        result = predict_stock_action(
            StockPredictionRequest(
                ingredient="Sugar",
                current_quantity=3,
                minimum_stock=20,
                pending_po_quantity=100,
                stock_out_last_14_days=28,
                supplier_lead_time_days=2,
            )
        )

        self.assertEqual("add_stock_now", result.recommended_action)
        self.assertEqual(37.0, result.suggested_quantity)

    def test_expired_stock_is_not_recommended_for_purchase(self) -> None:
        result = predict_stock_action(
            StockPredictionRequest(
                ingredient="Butter",
                current_quantity=12,
                minimum_stock=25,
                expiry_days_remaining=-1,
            )
        )

        self.assertEqual("do_not_buy", result.recommended_action)
        self.assertEqual(0.0, result.suggested_quantity)
        self.assertIn("expired_stock_do_not_buy", result.reason_codes)

    def test_usable_below_minimum_stock_has_priority_over_expiry_advice(self) -> None:
        result = predict_stock_action(
            StockPredictionRequest(
                ingredient="Milk",
                current_quantity=12,
                minimum_stock=20,
                expiry_days_remaining=5,
            )
        )

        self.assertEqual("add_stock_now", result.recommended_action)
        self.assertGreater(result.suggested_quantity, 0)


if __name__ == "__main__":
    unittest.main()
