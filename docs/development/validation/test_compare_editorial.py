"""Synthetic tests only; no publisher HTML, PostgreSQL or network required."""
import copy
import unittest
from unittest.mock import patch

from compare_editorial import evaluate


def dataset(field="language", value=None, scope=None):
    value = value or {"code": "en", "supported": True}
    scope = scope or {"article_code": None, "section": "manuscript"}
    return {"schema_version": 1, "annotation_policy": {
        "page_coverage_attested": True, "completed_at": "synthetic", "annotator_reference": "synthetic-human"},
        "pages": [{"id": "p", "journal_id": "j", "acquisition_status": "ACQUIRED", "source_record_id": "s"}],
        "journals": [{"id": "j"}], "annotations": [{"id": "a", "page_id": "p", "snapshot_id": "s",
        "fact_type": field, "disposition": "positive", "confidence": "explicit", "value": value, "scope": scope}],
        "extractions": [{"page_id": "p", "snapshot_id": "s", "status": "completed", "proposals": [
            {"field": field, "value": copy.deepcopy(value), "locator": "/synthetic/p"}]}]}


class ComparisonTest(unittest.TestCase):
    def test_exact_tp(self):
        self.assertEqual(evaluate(dataset())["overall"]["TP"], 1)

    def test_wrong_value_counts_fp_and_fn(self):
        d = dataset(); d["extractions"][0]["proposals"][0]["value"]["code"] = "fr"
        r = evaluate(d)["overall"]
        self.assertEqual((r["TP"], r["FP"], r["FN"]), (0, 1, 1))

    def test_missing_fact_is_fn(self):
        d = dataset(); d["extractions"][0]["proposals"] = []
        r = evaluate(d)["overall"]
        self.assertEqual((r["FN"], r["precision"], r["recall"], r["f1"]), (1, None, 0, 0))

    def test_ca_for_completed_empty_page(self):
        d = dataset(); d["annotations"][0]["disposition"] = "ambiguous"
        d["extractions"][0]["proposals"] = []
        self.assertEqual(evaluate(d)["overall"]["CA"], 1)

    def test_failed_page_retains_fn_without_claiming_ca(self):
        d = dataset(); a = copy.deepcopy(d["annotations"][0]); a.update(id="b", disposition="ambiguous")
        d["annotations"].append(a); d["extractions"][0].update(status="extraction_failed", proposals=[])
        r = evaluate(d)["overall"]
        self.assertEqual((r["FN"], r["CA"], r["ambiguous_not_evaluated"], r["extraction_failures"]), (1, 0, 1, 1))

    def test_oos_excluded_but_unsafe_generalization_is_fp(self):
        d = dataset("article_type", {"code": "research_article", "supported": True}, {"section": "journal"})
        d["annotations"][0]["disposition"] = "out_of_scope"
        d["annotations"][0]["scope"]["scientific_domain_restriction"] = "Only listed subject areas"
        r = evaluate(d)["overall"]
        self.assertEqual((r["OOS"], r["FN"], r["FP"]), (1, 0, 1))

    def test_normalized_fee_and_currency_difference(self):
        d = dataset("fee", {"fee_type": "apc", "availability": "known", "amount": "1200", "currency": "USD"}, {"section": "unspecified"})
        d["extractions"][0]["proposals"][0]["value"]["amount"] = "1,200.0000"
        self.assertEqual(evaluate(d)["overall"]["TP"], 1)
        d["extractions"][0]["proposals"][0]["value"]["currency"] = "EUR"
        self.assertEqual(evaluate(d)["overall"]["FP"], 1)

    def test_no_apc_is_not_zero_or_unknown(self):
        d = dataset("fee", {"fee_type": "apc", "availability": "unknown", "amount": None, "currency": None,
                           "basis": "official_reported_no_apc"}, {"section": "unspecified"})
        d["extractions"][0]["proposals"][0]["value"]["basis"] = "doaj_reported_no_apc"
        self.assertEqual(evaluate(d)["overall"]["TP"], 1)
        d["extractions"][0]["proposals"][0]["value"].update(availability="known", amount="0", currency="USD")
        self.assertEqual(evaluate(d)["overall"]["FP"], 1)
        d["extractions"][0]["proposals"][0]["value"].update(availability="unknown", amount=None, currency=None, basis="not_stated")
        self.assertEqual(evaluate(d)["overall"]["FP"], 1)

    def test_scoped_word_requirement(self):
        d = dataset("requirement", {"requirement_type": "abstract_words", "operator": "max", "value_number": 250,
                                   "article_code": "research_article", "scope": "abstract"},
                    {"article_code": "research_article", "section": "abstract"})
        self.assertEqual(evaluate(d)["overall"]["TP"], 1)
        d["extractions"][0]["proposals"][0]["value"]["article_code"] = None
        r = evaluate(d)["overall"]
        self.assertEqual((r["TP"], r["FP"], r["FN"]), (0, 1, 1))

    def test_link_role_and_exact_target(self):
        for field, value in [("submission_url", {"text_value": "https://example.org/submit"}),
                             ("template", {"format": "word", "official_url": "https://example.org/template.docx"})]:
            with self.subTest(field=field):
                d = dataset(field, value, {"section": "unspecified"})
                self.assertEqual(evaluate(d)["overall"]["TP"], 1)
                key = "text_value" if field == "submission_url" else "official_url"
                d["extractions"][0]["proposals"][0]["value"][key] += "#different"
                self.assertEqual(evaluate(d)["overall"]["FP"], 1)

    def test_malformed_benchmark_rejected(self):
        for mutation in [lambda d: d.update(schema_version=2),
                         lambda d: d["annotation_policy"].update(page_coverage_attested=False),
                         lambda d: d.update(extractions=[]),
                         lambda d: d["annotations"][0].update(disposition="guessed"),
                         lambda d: d["annotations"][0].update(snapshot_id="wrong")]:
            d = dataset(); mutation(d)
            with self.assertRaises(ValueError): evaluate(d)

    def test_frozen_gold_digest_rejects_changed_representation(self):
        import hashlib
        import json
        d = dataset()
        d["gold_sha256"] = hashlib.sha256(json.dumps({"annotations": d["annotations"],
            "annotation_policy": d["annotation_policy"]}, sort_keys=True,
            separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        self.assertEqual(evaluate(d)["overall"]["TP"], 1)
        d["annotations"][0]["scope"]["section"] = "abstract"
        with self.assertRaises(ValueError): evaluate(d)

    def test_duplicate_annotations_facts_and_runs_rejected(self):
        d = dataset(); d["annotations"].append(copy.deepcopy(d["annotations"][0]))
        with self.assertRaises(ValueError): evaluate(d)
        d["annotations"][1]["id"] = "another-id"
        with self.assertRaises(ValueError): evaluate(d)
        d = dataset(); d["extractions"].append(copy.deepcopy(d["extractions"][0]))
        with self.assertRaises(ValueError): evaluate(d)

    def test_deterministic_pure_no_io_or_input_mutation(self):
        d = dataset(); before = copy.deepcopy(d)
        with patch("builtins.open", side_effect=AssertionError("Unexpected IO")):
            a = evaluate(d); b = evaluate(d)
        self.assertEqual(a, b)
        self.assertEqual(d, before)

    def test_ab_and_policy_holds_are_not_fn(self):
        d = dataset(); d["pages"].extend([
            {"id": "blocked", "journal_id": "j", "acquisition_status": "ACQUISITION_BLOCKED"},
            {"id": "hold", "journal_id": "j", "acquisition_status": "NOT_ATTEMPTED_POLICY_HOLD"}])
        r = evaluate(d)["overall"]
        self.assertEqual((r["AB"], r["policy_holds"], r["FN"]), (1, 1, 0))


if __name__ == "__main__":
    unittest.main()
