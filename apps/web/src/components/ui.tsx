import Link from "next/link";
import { ApiError } from "../lib/api";
import { apcLabel, relevanceMessage, supportedNames, type Journal, type Recommendation } from "../lib/discovery";
export function Loading({ label = "Loading the published catalogue…" }: {
    label?: string;
}) { return <div className="state-panel" role="status"><span className="spinner" aria-hidden="true"/><p>{label}</p></div>; }
export function ErrorState({ error, retry }: {
    error: unknown;
    retry?: () => void;
}) { return <div className="error-panel" role="alert"><h2>Something needs attention</h2><p>{error instanceof ApiError ? error.message : "We could not load this information. Please try again."}</p>{error instanceof ApiError && error.requestId && <p className="small">Support reference: {error.requestId}</p>}{retry && <button type="button" className="button secondary" onClick={retry}>Try again</button>}</div>; }
export function EmptyState({ recommendations = false }: {
    recommendations?: boolean;
}) { return <div className="state-panel"><span className="eyebrow">No matching journals</span><h2>A narrower catalogue, a fresh starting point.</h2><p>{recommendations ? "No published journals satisfy these requirements. Try changing your filters or describing your research differently." : "No published journals match this search. Adjust your filters or try a broader search."}</p>{recommendations && <Link href="/research" className="button secondary">Edit your research</Link>}</div>; }
export function RelevanceNote() { return <p className="relevance-note">{relevanceMessage}</p>; }
export function JournalFacts({ journal }: {
    journal: Journal;
}) { return <dl className="facts"><div><dt>Subjects</dt><dd>{journal.subjects.map(s => s.name).join(", ") || "Not available"}</dd></div><div><dt>Language</dt><dd>{supportedNames(journal.languages)}</dd></div><div><dt>Article types</dt><dd>{supportedNames(journal.article_types)}</dd></div><div><dt>APC</dt><dd>{apcLabel(journal.apc)}</dd></div><div><dt>Indexation</dt><dd>{supportedNames(journal.indexations)}</dd></div></dl>; }
export function JournalCard({ journal, recommendation, rank, selected, disabled, onToggle }: {
    journal: Journal;
    recommendation?: Recommendation;
    rank?: number;
    selected: boolean;
    disabled?: boolean;
    onToggle: () => void;
}) {
    return <article className="journal-card"><div className="journal-heading"><div>{rank && <span className="eyebrow">Recommendation #{rank}</span>}<h2><Link href={`/journals/${journal.id}`}>{journal.title}</Link></h2><p className="publisher">{journal.publisher?.name ?? "Publisher not available"}</p></div>{recommendation && <div className="score"><strong>{recommendation.relevance_score.toLocaleString("en", { maximumFractionDigits: 2 })}</strong><span>Relevance score</span></div>}</div><JournalFacts journal={journal}/>{recommendation && <div className="reasons"><h3>Why this journal?</h3>{recommendation.reasons.length ? <ul>{recommendation.reasons.slice(0, 5).map((r, i) => <li key={i}>{r.type === "keyword_match" ? "Keyword overlap" : r.type === "subject_match" ? "Subject match" : "Related subject"}: <strong>{r.label}</strong></li>)}</ul> : <p>No direct metadata match was found; this journal satisfies your filters.</p>}</div>}<div className="card-actions"><Link href={`/journals/${journal.id}`} className="text-link">Inspect journal <span aria-hidden="true">↗</span></Link><button type="button" aria-pressed={selected} disabled={disabled && !selected} className="compare-button" onClick={onToggle}>{selected ? "✓ Selected for comparison" : "+ Compare journal"}</button></div></article>;
}
