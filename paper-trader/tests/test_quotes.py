"""Quote parsing stays offline. The desk asks the feed only when a test calls it."""

from __future__ import annotations

import sys
import unittest
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from paper_trader import quotes
from paper_trader.model import Bar


def _chart(stamps, opens, highs, lows, closes, price=None, when=None):
    meta = {
        "gmtoffset": 0,
        "exchangeName": "NMS",
        "regularMarketTime": when if when is not None else stamps[-1],
    }
    if price is not None:
        meta["regularMarketPrice"] = price
    return {
        "chart": {
            "result": [
                {
                    "meta": meta,
                    "timestamp": stamps,
                    "indicators": {
                        "quote": [
                            {
                                "open": opens,
                                "high": highs,
                                "low": lows,
                                "close": closes,
                                "volume": [100] * len(stamps),
                            }
                        ]
                    },
                }
            ]
        }
    }


class QuoteParseTests(unittest.TestCase):
    def setUp(self) -> None:
        quotes._CACHE.clear()
        self._request = quotes._request

    def tearDown(self) -> None:
        quotes._request = self._request
        quotes._CACHE.clear()

    def test_daily_parse_skips_gaps_and_uses_the_live_price(self) -> None:
        start = int(datetime(2024, 1, 2, tzinfo=timezone.utc).timestamp())
        stamps = [start + index * 86400 for index in range(5)]
        bars, meta = quotes.parse_chart(
            _chart(
                stamps,
                [10, 11, None, 13, 14],
                [12, 12, 12, 15, 16],
                [9, 10, 10, 12, 13],
                [11, 11.5, 11, 14, 15],
                price=15.5,
                when=stamps[-1] + 3600,
            ),
            intraday=False,
        )
        self.assertEqual([bar.date for bar in bars], ["2024-01-02", "2024-01-03", "2024-01-05", "2024-01-06"])
        self.assertEqual(bars[-1].close, 15.5)
        self.assertGreaterEqual(bars[-1].high, 15.5)
        self.assertLessEqual(bars[-1].low, bars[-1].open)
        self.assertEqual(meta["price"], 15.5)
        self.assertEqual(meta["as_of"], stamps[-1] + 3600)
        self.assertEqual(meta["exchange"], "NMS")

    def test_a_short_ohlc_array_does_not_crash(self) -> None:
        start = int(datetime(2024, 1, 2, tzinfo=timezone.utc).timestamp())
        stamps = [start, start + 86400, start + 2 * 86400]
        bars, _meta = quotes.parse_chart(_chart(stamps, [10], [11], [9], [10.5]), intraday=False)
        self.assertEqual(len(bars), 1)
        self.assertEqual(bars[0].close, 10.5)

    def test_non_positive_prices_are_left_out(self) -> None:
        start = int(datetime(2024, 1, 2, tzinfo=timezone.utc).timestamp())
        stamps = [start, start + 86400]
        bars, _meta = quotes.parse_chart(_chart(stamps, [10, 0], [11, 1], [9, 0], [10.5, 1]), intraday=False)
        self.assertEqual(len(bars), 1)

    def test_yahoo_symbols_follow_the_catalog(self) -> None:
        self.assertEqual(quotes.yahoo_symbol("AAPL"), "AAPL")
        self.assertEqual(quotes.yahoo_symbol("BTC-USD"), "BTC-USD")
        self.assertEqual(quotes.yahoo_symbol("ES"), "ES=F")
        with self.assertRaises(ValueError):
            quotes.yahoo_symbol("NOPE")

    def test_hours_and_quarters_keep_real_prices(self) -> None:
        bars = [
            Bar("2024-01-02", 10, 12, 9, 11, 1),
            Bar("2024-01-02", 11, 13, 10, 12, 1),
            Bar("2024-04-01", 12, 14, 11, 13, 1),
            Bar("2024-04-02", 13, 15, 12, 14, 1),
        ]
        grouped = quotes.aggregate_hours(bars, 4)
        self.assertEqual(len(grouped), 1)
        self.assertEqual(grouped[0].open, 10)
        self.assertEqual(grouped[0].close, 14)
        self.assertEqual(grouped[0].high, 15)
        self.assertEqual(grouped[0].low, 9)
        quarters = quotes.aggregate_quarters(bars[:2] + bars[2:])
        self.assertEqual([bar.date for bar in quarters], ["2024-Q1", "2024-Q2"])
        self.assertEqual(quarters[0].close, 12)
        self.assertEqual(quarters[1].open, 12)

    def test_load_bars_reads_the_chart_payload_without_a_network(self) -> None:
        start = int(datetime(2024, 1, 2, tzinfo=timezone.utc).timestamp())
        stamps = [start + index * 86400 for index in range(45)]
        opens = [100 + index for index in range(45)]
        highs = [item + 2 for item in opens]
        lows = [item - 2 for item in opens]
        closes = [item + 1 for item in opens]

        def fake(yahoo: str, interval: str, span: str, refresh: bool = False) -> dict:
            self.assertEqual(yahoo, "AAPL")
            self.assertEqual(interval, "1d")
            return _chart(stamps, opens, highs, lows, closes, price=closes[-1] + 3)

        quotes._request = fake
        bars = quotes.load_bars("AAPL", refresh=True)
        self.assertEqual(len(bars), 45)
        self.assertEqual(bars[-1].close, closes[-1] + 3)
        self.assertEqual(bars[0].date, "2024-01-02")

    def test_a_short_history_is_an_error(self) -> None:
        start = int(datetime(2024, 1, 2, tzinfo=timezone.utc).timestamp())
        stamps = [start + index * 86400 for index in range(10)]
        quotes._request = lambda yahoo, interval, span, refresh=False: _chart(
            stamps, [10] * 10, [12] * 10, [9] * 10, [11] * 10, price=11
        )
        with self.assertRaises(ValueError):
            quotes.load_bars("SPY")


if __name__ == "__main__":
    unittest.main()
