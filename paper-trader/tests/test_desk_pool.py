"""Desk Pool Form Prints / Civic Trades — fail-closed gates and public disclosure shaping."""

from __future__ import annotations

import unittest
from unittest import mock

from paper_trader.desk_pool import (
    _normalize_civic_row,
    civic_trades,
    desk_pool_open,
    form_prints,
    fund_seat,
    pool_gates,
    seat_flow_scan,
)


class DeskPoolTests(unittest.TestCase):
    def test_gates_fail_closed(self) -> None:
        gates = pool_gates()
        self.assertFalse(gates["darkPoolBook"]["ready"])
        self.assertEqual(gates["darkPoolBook"]["status"], "No feed")
        self.assertFalse(gates["icebergBook"]["ready"])
        self.assertFalse(gates["institutionalBlotter"]["ready"])
        self.assertTrue(gates["paperOnly"])
        self.assertTrue(gates["liveLocked"])

    def test_form_prints_rejects_index(self) -> None:
        out = form_prints("^GSPC")
        self.assertFalse(out["ready"])
        self.assertEqual(out["rows"], [])

    def test_normalize_civic_row(self) -> None:
        row = _normalize_civic_row(
            {
                "ticker": "nvda",
                "type": "Purchase",
                "amountLabel": "$15,001 - $50,000",
                "memberName": "Example Member",
                "transactionDate": "2026-01-02",
                "disclosureDate": "2026-02-01",
                "sourceLink": "https://example.test/ptr.pdf",
            },
            "test",
        )
        self.assertEqual(row["symbol"], "NVDA")
        self.assertEqual(row["side"], "Buy")
        self.assertEqual(row["amount"], "$15,001 - $50,000")

    def test_form_prints_shapes_rows(self) -> None:
        payload = {
            "data": [
                {
                    "issuerTicker": "AAPL",
                    "ownerName": "Example Officer",
                    "ownerTitle": "CEO",
                    "transactionCode": "P",
                    "shares": 1000,
                    "pricePerShare": 10.0,
                    "totalValue": 10000.0,
                    "filedAt": "2026-11-01T12:00:00.000Z",
                    "transactionDate": "2026-10-30",
                    "rawFilingUrl": "https://example.test/4",
                    "is10b5_1": False,
                }
            ],
            "meta": {"attribution": {"text": "Credit line"}},
        }
        with mock.patch("paper_trader.desk_pool._fetch_json", return_value=payload):
            with mock.patch("paper_trader.desk_pool._get", return_value=None):
                with mock.patch("paper_trader.desk_pool._put", side_effect=lambda key, value, ttl: value):
                    out = form_prints("AAPL", 5)
        self.assertTrue(out["ready"])
        self.assertEqual(out["rows"][0]["side"], "Buy")
        self.assertEqual(out["rows"][0]["shares"], 1000)
        self.assertIn("Credit line", out["attribution"])

    def test_civic_trades_filters_ticker(self) -> None:
        house = [
            {
                "ticker": "MSFT",
                "txn_type": "buy",
                "politician": "Member A",
                "amount_low": 1001,
                "amount_high": 15000,
                "txn_date": "2026-01-01",
                "disclosure_date": "2026-02-01",
                "filing_status": "new",
            },
            {
                "ticker": "AAPL",
                "txn_type": "sell",
                "politician": "Member B",
                "amount_low": 1001,
                "amount_high": 15000,
                "txn_date": "2026-01-02",
                "disclosure_date": "2026-02-02",
                "filing_status": "new",
            },
        ]
        with mock.patch("paper_trader.desk_pool._house_year_rows", return_value=[
            _normalize_civic_row(house[0], "optimistfi-house"),
            _normalize_civic_row(house[1], "optimistfi-house"),
        ]):
            with mock.patch("paper_trader.desk_pool._member_rows", return_value=[]):
                out = civic_trades("AAPL", 10)
        self.assertTrue(out["ready"])
        self.assertEqual(len(out["rows"]), 1)
        self.assertEqual(out["rows"][0]["symbol"], "AAPL")
        self.assertEqual(out["rows"][0]["side"], "Sell")

    def test_desk_pool_open_includes_gates(self) -> None:
        with mock.patch("paper_trader.desk_pool.form_prints", return_value={"ready": False, "rows": [], "note": "x"}):
            with mock.patch("paper_trader.desk_pool.civic_trades", return_value={"ready": False, "rows": [], "note": "y"}):
                out = desk_pool_open("NVDA")
        self.assertEqual(out["symbol"], "NVDA")
        self.assertEqual(out["gates"]["darkPoolBook"]["status"], "No feed")
        self.assertTrue(out["fundSeat"]["ready"])
        self.assertGreaterEqual(len(out["fundSeat"]["rows"]), 3)
        self.assertEqual(out["version"], "1.10.43")

    def test_fund_seat_desk_names_only(self) -> None:
        out = fund_seat(12)
        self.assertTrue(out["ready"])
        seats = {row["seat"] for row in out["rows"]}
        self.assertTrue(seats.issubset({"Value Seat", "Macro Seat", "Activist Seat"}))
        for row in out["rows"]:
            self.assertTrue(row["symbol"])
            self.assertNotIn("disclosed", row["seat"].lower())
            self.assertNotIn("buffett", row["role"].lower())

    def test_seat_flow_scan_desk_names(self) -> None:
        with mock.patch(
            "paper_trader.desk_pool.civic_trades",
            return_value={
                "ready": True,
                "rows": [
                    {
                        "symbol": "NVDA",
                        "member": "Example Member",
                        "side": "Buy",
                        "amount": "$50,001 – $100,000",
                        "traded": "2026-01-02",
                        "disclosed": "2026-01-10",
                    }
                ],
                "attribution": "test",
            },
        ):
            with mock.patch(
                "paper_trader.desk_pool.form_prints",
                return_value={"ready": False, "rows": [], "note": "x"},
            ):
                out = seat_flow_scan(10)
        self.assertTrue(out["ready"])
        self.assertEqual(out["rows"][0]["seat"], "Civic Seat")
        self.assertEqual(out["rows"][0]["symbol"], "NVDA")
        self.assertNotIn("pelosi tracker", out["note"].lower())
        self.assertNotIn("disclosedhq", out["note"].lower())


if __name__ == "__main__":
    unittest.main()
