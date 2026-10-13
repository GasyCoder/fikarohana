import { test } from "node:test";
import assert from "node:assert/strict";
import { join } from "node:path";
import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createRequire } from "node:module";
const testRequire = createRequire(import.meta.url);
const load = name => testRequire(join(process.env.FIKAROHANA_WEB_TEST_BUILD, name));
const model = load("lib/discovery.js"), { api, ApiError } = load("lib/api.js");
const ui = load("components/ui.js");
const { JournalDetailView } = load("components/journal-detail.js");
const { ComparisonTable } = load("components/comparison-table.js");
const { RecommendationResults } = load("components/recommendation-results.js");
const html = (component, props = {}) => renderToStaticMarkup(React.createElement(component, props));
const journal = id => ({ id, revision_id: "revision", title: "Synthetic Ecology Journal", publisher: null, identifiers: [], subjects: [], languages: [], article_types: [], indexations: [], fees: [], apc: { status: "unknown", amount: null, currency: null } });
const recommendation = { ...journal("j1"), relevance_score: 73.4, score_components: { taxonomy: null, lexical: 73.4 }, reasons: [{ type: "keyword_match", label: "ecology" }] };
const response = data => ({ data, meta: { returned: data.length, requested_limit: 5, candidates_after_filters: data.length, scored_candidates_loaded: data.length, catalog_revision_id: null, algorithm_version: "synthetic", retrieval_mode: "deterministic_metadata", score_semantics: "metadata_relevance_only" } });
test("research title and input boundaries are validated without inventing metadata", () => {
  assert.match(model.researchRequest(model.emptyResearch).errors.title, /Enter a title/);
  assert.ok(model.researchRequest({ ...model.emptyResearch, title: "x".repeat(501) }).errors.title);
  assert.ok(model.researchRequest({ ...model.emptyResearch, title: "Study", abstract: "x".repeat(10001) }).errors.abstract);
  assert.ok(model.researchRequest({ ...model.emptyResearch, title: "Study", keywords: "x".repeat(101) }).errors.keywords);
  assert.ok(model.researchRequest({ ...model.emptyResearch, title: "Study", keywords: Array.from({ length: 21 }, (_, i) => `key${i}`).join(",") }).errors.keywords);
  assert.ok(model.researchRequest({ ...model.emptyResearch, title: "Study", indexations: Array(11).fill("code") }).errors.indexations);
});
test("request mapping uses API names, optional omission, five results and canonical IDs", () => {
  const mapped = model.researchRequest({ ...model.emptyResearch, title: " Study ", abstract: " Abstract ", keywords: "ecology, ecology, field", subject: "canonical-id", language: "en", articleType: "research_article", noApc: true, indexations: ["doaj"] });
  assert.deepEqual(mapped.payload, { title: "Study", abstract: "Abstract", keywords: ["ecology", "field"], subject_ids: ["canonical-id"], language: "en", article_type: "research_article", no_apc_only: true, indexation_services: ["doaj"], limit: 5 });
  assert.deepEqual(model.researchRequest({ ...model.emptyResearch, title: "Study" }).payload, { title: "Study", limit: 5 });
});
test("browse query preserves filters, explicit no APC, pagination and URL encoding", () => {
  const filters = { ...model.emptyBrowse, q: "ecology & climate", subject: "id", language: "fr", articleType: "review", indexation: "doaj", publisher: "p", noApc: true };
  const query = new URLSearchParams(model.browseQuery(filters, 2));
  assert.equal(query.get("q"), "ecology & climate"); assert.equal(query.get("page"), "2"); assert.equal(query.get("per_page"), "20"); assert.equal(query.get("no_apc"), "true");
  assert.deepEqual(model.browseFromQuery(query), filters);
  assert.equal(new URLSearchParams(model.browseQuery(model.emptyBrowse)).has("no_apc"), false);
});
test("options use only observed canonical names and explicit supported memberships", () => {
  const j = journal("a"); j.languages = [{ code: "en", name: "English", supported: true }, { code: "fr", name: "French", supported: false }];
  assert.deepEqual(model.catalogOptions([j, j]).languages, [{ code: "en", name: "English", supported: true }]);
  assert.deepEqual(model.catalogOptions([]).subjects, []);
});
test("numeric zero, unknown, contextual fees and explicit no APC remain distinct", () => {
  assert.equal(model.apcLabel(journal("a").apc), "Unknown");
  assert.equal(model.apcLabel({ status: "known", amount: "0.0000", currency: "USD" }), "USD 0");
  assert.equal(model.apcLabel({ status: "no_apc", amount: null, currency: null }), "No APC reported");
  assert.equal(model.apcLabel({ status: "context_dependent", amount: null, currency: null }), "Depends on context");
  assert.equal(model.supportedNames([{ name: "French", code: "fr", supported: false }]), "Not available");
});
test("comparison adds, removes, preserves scores and enforces five-journal limit", () => {
  let entries = [];
  for (let i = 0; i < 5; i++) entries = model.toggleCompare(entries, { journal: journal(`j${i}`) }).entries;
  assert.equal(entries.length, 5);
  const blocked = model.toggleCompare(entries, { journal: journal("sixth") }); assert.equal(blocked.entries.length, 5); assert.match(blocked.error, /five journals/);
  entries = model.toggleCompare(entries, { journal: journal("j0") }).entries; assert.equal(entries.length, 4);
  const newRecommendation = { ...recommendation, id: "new" }; entries = model.toggleCompare(entries, { journal: newRecommendation, recommendation: newRecommendation }).entries; assert.equal(entries[4].recommendation.relevance_score, 73.4);
});
test("journal result renders score as relevance, reasons, unknowns and comparison action", () => {
  const output = html(ui.JournalCard, { journal: recommendation, recommendation, rank: 1, selected: false, onToggle() {} });
  assert.match(output, /Recommendation #1/); assert.match(output, /73.4/); assert.match(output, /Relevance score/); assert.match(output, /Keyword overlap/); assert.match(output, /ecology/); assert.match(output, /Unknown/); assert.match(output, /Compare journal/);
  assert.doesNotMatch(output, /acceptance_probability|Acceptance score|progressbar|SourceRecord/);
  assert.match(html(ui.RelevanceNote), /does not predict manuscript acceptance/);
});
test("recommendation empty, partial and navigation-reset states contain no fake alternatives", () => {
  assert.match(html(RecommendationResults, { results: null, selected: [], toggle() {} }), /Start with your research/);
  const empty = html(RecommendationResults, { results: response([]), selected: [], toggle() {} }); assert.match(empty, /No matching journals/); assert.match(empty, /Edit your research/); assert.doesNotMatch(empty, /Synthetic Ecology Journal/);
  const partial = html(RecommendationResults, { results: response([recommendation]), selected: [], toggle() {} }); assert.match(partial, /Fewer than 5/); assert.match(partial, /not been relaxed/);
});
test("loading and retry errors are accessible and avoid raw backend messages", () => {
  assert.match(html(ui.Loading), /role="status"/);
  const output = html(ui.ErrorState, { error: new ApiError(503, "server_error", {}, "support-ref"), retry() {} });
  assert.match(output, /role="alert"/); assert.match(output, /Try again/); assert.match(output, /support-ref/);
  assert.match(html(ui.ErrorState, { error: new ApiError(404, "not_found") }), /not available/);
});
test("detail exposes useful provenance links but no assertions or snapshot internals", () => {
  const output = html(JournalDetailView, { journal: { ...journal("a"), provenance: [{ assertion_id: "private-assertion-id", field: "title", source_url: "https://journal.example.org/guide", retrieved_at: "2026-10-13T00:00:00Z", verified_at: null }] } });
  assert.match(output, /Overview/); assert.match(output, /Publication fees/); assert.match(output, /Identifiers/); assert.match(output, /journal.example.org/); assert.match(output, /Not available/);
  assert.doesNotMatch(output, /private-assertion-id|revision|raw_content|SourceRecord/);
  assert.equal(model.safeSourceUrl("javascript:alert(1)"), undefined); assert.equal(model.safeSourceUrl("https://user:password@example.org"), undefined);
});
test("comparison table shows unknown metadata, score availability and semantic headers", () => {
  const output = html(ComparisonTable, { entries: [{ journal: journal("a") }, { journal: recommendation, recommendation }], remove() {} });
  assert.match(output, /scope="row"/); assert.match(output, /scope="col"/); assert.match(output, /Not available/); assert.match(output, /Unknown/); assert.match(output, /73.4/); assert.match(output, /Remove/);
});
test("API client maps requests to Laravel only and omits credentials and browser cache", async t => {
  const original = global.fetch; t.after(() => { global.fetch = original; });
  const calls = []; global.fetch = async (url, options) => { calls.push({ url, options }); return new Response(JSON.stringify(response([])), { status: 200 }); };
  await api.recommend({ title: "Private study", limit: 5 }); await api.journals("page=2"); await api.journal("journal-id");
  assert.equal(calls[0].url, "/api/v1/journal-recommendations"); assert.equal(calls[0].options.method, "POST"); assert.equal(JSON.parse(calls[0].options.body).title, "Private study");
  assert.equal(calls[0].options.credentials, "omit"); assert.equal(calls[0].options.cache, "no-store"); assert.equal(calls[1].url, "/api/v1/journals?page=2"); assert.equal(calls[2].url, "/api/v1/journals/journal-id");
});
test("backend field errors, rate limits and unavailable/non-JSON responses are handled safely", async t => {
  const original = global.fetch; t.after(() => { global.fetch = original; });
  global.fetch = async () => new Response(JSON.stringify({ error: { code: "validation_error", message: "Raw SQL should not appear", fields: { title: ["Required"] }, request_id: "r" } }), { status: 422 });
  await assert.rejects(api.recommend({ title: "", limit: 5 }), e => e instanceof ApiError && e.fields.title[0] === "Required" && !e.message.includes("SQL"));
  global.fetch = async () => new Response("not json", { status: 502 }); await assert.rejects(api.journal("a"), ApiError);
  global.fetch = async () => new Response("bad success", { status: 200 }); await assert.rejects(api.journal("a"), e => e.status === 502);
  global.fetch = async () => { throw new Error("network detail"); }; await assert.rejects(api.journals(""), e => e.status === 503 && !e.message.includes("network detail"));
});
