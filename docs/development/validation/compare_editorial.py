"""Development-only, offline comparison of human gold and captured dry-run outputs."""
import argparse
import hashlib
import json
import re
from decimal import Decimal


FIELDS = {"fee", "language", "article_type", "requirement", "template", "submission_url"}


def amount(value):
    if not isinstance(value, str):
        raise ValueError("Money must be an exact decimal string")
    if re.fullmatch(r"[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,4})?", value):
        value = value.replace(",", "")
    if not re.fullmatch(r"[0-9]{1,10}(?:\.[0-9]{1,4})?", value):
        raise ValueError("Invalid monetary format")
    return format(Decimal(value), ".4f")


def identity(field, value, scope):
    """Match semantic values/scopes, excluding descriptive labels and provenance."""
    if field not in FIELDS or not isinstance(value, dict) or not isinstance(scope, dict):
        raise ValueError("Invalid fact")
    article = scope.get("article_code", value.get("article_code"))
    section = scope.get("section", value.get("scope", "unspecified"))
    restriction = scope.get("scientific_domain_restriction")
    if field == "fee":
        absent = (value.get("availability") == "unknown" and value.get("fee_type") == "apc"
                  and value.get("amount") is None and value.get("currency") is None
                  and value.get("basis") in {"official_reported_no_apc", "doaj_reported_no_apc"})
        fact = ["explicit_no_apc", "apc"] if absent else [
            value["fee_type"], value.get("availability", "known"),
            None if value.get("amount") is None else amount(value["amount"]),
            None if value.get("currency") is None else value["currency"].upper()]
    elif field in {"language", "article_type"}:
        if not isinstance(value.get("supported"), bool):
            raise ValueError("Support must be boolean")
        fact = [value["code"], value["supported"]]
        section = scope.get("section", "manuscript" if field == "language" else "journal")
    elif field == "requirement":
        fact = [value["requirement_type"], value["operator"], value.get("value_number"), value.get("value_text"), value.get("value_items")]
        section = scope.get("section", value.get("scope", {
            "abstract_words": "abstract", "word_count": "manuscript", "reference_style": "references"
        }.get(value["requirement_type"], "unspecified")))
    elif field == "template":
        fact = [value["format"], value["official_url"]]
    else:
        fact = [value["text_value"]]
    if field in {"template", "submission_url"}:
        url = fact[-1]
        if not isinstance(url, str) or not re.match(r"^https?://[^\s]+$", url):
            raise ValueError("Invalid benchmark link")
    return json.dumps([field, fact, article, section, restriction], sort_keys=True, separators=(",", ":"))


def rates(tp, fp, fn):
    return {"precision": tp / (tp + fp) if tp + fp else None,
            "recall": tp / (tp + fn) if tp + fn else None,
            "f1": 2 * tp / (2 * tp + fp + fn) if 2 * tp + fp + fn else None,
            "fp_rate": fp / (tp + fp) if tp + fp else None}


def evaluate(data):
    """Pure function: no network, DB, file writes, or mutation of supplied data."""
    if not isinstance(data, dict) or data.get("schema_version") != 1:
        raise ValueError("Unsupported benchmark schema")
    if not all(isinstance(data.get(k), list) for k in ["pages", "journals", "annotations", "extractions"]):
        raise ValueError("Invalid benchmark collections")
    if data.get("gold_sha256"):
        digest = hashlib.sha256(json.dumps({"annotations": data["annotations"],
            "annotation_policy": data["annotation_policy"]}, sort_keys=True,
            separators=(",", ":"), ensure_ascii=False).encode()).hexdigest()
        if digest != data["gold_sha256"]:
            raise ValueError("Frozen human gold changed")
    policy = data["annotation_policy"]
    if not policy.get("page_coverage_attested") or not policy.get("completed_at") or not policy.get("annotator_reference"):
        raise ValueError("Human gold must be frozen with complete coverage")
    pages = {p["id"]: p for p in data["pages"]}
    if len(pages) != len(data["pages"]):
        raise ValueError("Duplicate page")
    runs = {r["page_id"]: r for r in data["extractions"]}
    if len(runs) != len(data["extractions"]):
        raise ValueError("Duplicate run")
    if set(runs) != {p["id"] for p in pages.values() if p["acquisition_status"] == "ACQUIRED"}:
        raise ValueError("Every acquired page requires a captured run")
    for page_id, run in runs.items():
        if page_id not in pages or pages[page_id]["acquisition_status"] != "ACQUIRED":
            raise ValueError("Run requires acquired page")
        if run.get("status") not in {"completed", "extraction_failed"}:
            raise ValueError("Invalid run status")
        if run["snapshot_id"] != pages[page_id]["source_record_id"]:
            raise ValueError("Snapshot mismatch")
        if run["status"] == "extraction_failed" and run["proposals"]:
            raise ValueError("Failed extraction cannot return successful proposals")
    seen = set()
    positives = {}
    annotations = data["annotations"]
    for a in annotations:
        if a["id"] in seen or a["page_id"] not in runs:
            raise ValueError("Duplicate annotation or missing run")
        seen.add(a["id"])
        if a["disposition"] not in {"positive", "ambiguous", "out_of_scope"} or a["fact_type"] not in FIELDS:
            raise ValueError("Invalid annotation disposition/type")
        if a["snapshot_id"] != runs[a["page_id"]]["snapshot_id"]:
            raise ValueError("Annotation snapshot mismatch")
        if a["disposition"] == "positive":
            if a["confidence"] != "explicit" or a["scope"].get("scientific_domain_restriction"):
                raise ValueError("Unsafe V1 positive scope")
            key = (a["page_id"], identity(a["fact_type"], a["value"], a["scope"]))
            if key in positives:
                raise ValueError("Duplicate positive fact")
            positives[key] = a
    predicted = {}
    for page_id, run in runs.items():
        for proposal in run["proposals"]:
            key = (page_id, identity(proposal["field"], proposal["value"], {}))
            predicted.setdefault(key, proposal)  # Corroborating equivalent proposals count once.
    matched = set(positives) & set(predicted)
    def category(field, value):
        return "requirement." + value["requirement_type"] if field == "requirement" else field
    details = []
    for key in positives:
        details.append({"classification": "TP" if key in matched else "FN", "page_id": key[0],
                        "fact_type": category(positives[key]["fact_type"], positives[key]["value"]), "annotation_id": positives[key]["id"]})
    for key in set(predicted) - set(positives):
        details.append({"classification": "FP", "page_id": key[0], "fact_type": category(predicted[key]["field"], predicted[key]["value"]),
                        "locator": predicted[key].get("locator")})
    ca = 0
    not_evaluated = 0
    for a in annotations:
        if a["disposition"] == "ambiguous":
            run = runs[a["page_id"]]
            # Conservative: no CA claim for failed pages or mixed-output pages needing human attribution.
            if run["status"] == "completed" and not run["proposals"]:
                ca += 1
            else:
                not_evaluated += 1
    def aggregate(rows):
        counts = {name: sum(x["classification"] == name for x in rows) for name in ["TP", "FP", "FN"]}
        return counts | rates(counts["TP"], counts["FP"], counts["FN"])
    summary = aggregate(details)
    summary.update(CA=ca, ambiguous_not_evaluated=not_evaluated,
                   OOS=sum(a["disposition"] == "out_of_scope" for a in annotations),
                   AB=sum(p["acquisition_status"] == "ACQUISITION_BLOCKED" for p in pages.values()),
                   policy_holds=sum(p["acquisition_status"] == "NOT_ATTEMPTED_POLICY_HOLD" for p in pages.values()),
                   extraction_failures=sum(r["status"] == "extraction_failed" for r in runs.values()),
                   proposals=len(predicted), expected_positives=len(positives))
    fields = {f: aggregate([x for x in details if x["fact_type"] == f]) for f in sorted((FIELDS - {"requirement"}) | {"requirement.abstract_words", "requirement.word_count", "requirement.reference_style"} | {x["fact_type"] for x in details})}
    journals = {}
    for journal in data["journals"]:
        ids = {p["id"] for p in pages.values() if p["journal_id"] == journal["id"]}
        journals[journal["id"]] = aggregate([x for x in details if x["page_id"] in ids]) | {
            "acquired_pages": sum(pages[p]["acquisition_status"] == "ACQUIRED" for p in ids),
            "CA": sum(a["disposition"] == "ambiguous" and a["page_id"] in ids
                      and runs[a["page_id"]]["status"] == "completed" and not runs[a["page_id"]]["proposals"] for a in annotations),
            "OOS": sum(a["disposition"] == "out_of_scope" and a["page_id"] in ids for a in annotations)}
    return {"overall": summary, "by_fact_type": fields, "by_journal": journals,
            "details": sorted(details, key=lambda x: (x["page_id"], x["fact_type"], x.get("annotation_id", x.get("locator", ""))))}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("dataset")
    args = parser.parse_args()
    with open(args.dataset, encoding="utf-8") as source:
        result = evaluate(json.load(source))
    print(json.dumps(result, indent=2, sort_keys=True))
