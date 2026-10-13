import Link from "next/link";
import type { CompareEntry, RecommendationResponse } from "../lib/discovery";
import { EmptyState, JournalCard, RelevanceNote } from "./ui";
export function RecommendationResults({ results, selected, toggle }: {
    results: RecommendationResponse | null;
    selected: CompareEntry[];
    toggle: (entry: CompareEntry) => void;
}) {
    if (!results)
        return <div className="state-panel"><span className="eyebrow">A research-led shortlist</span><h1>Start with your research.</h1><p>Describe your work to see matching journals. Results are kept during navigation, but cleared when you reload or close this tab.</p><Link className="button" href="/research">Describe your research →</Link></div>;
    return <><div className="page-heading heading-actions"><div><p className="eyebrow">Your shortlist</p><h1>Journals worth exploring.</h1><p>{results.meta.returned} {results.meta.returned === 1 ? "journal" : "journals"} returned from {results.meta.candidates_after_filters} eligible journals.</p></div><Link href="/research" className="button secondary">New search</Link></div><RelevanceNote />{results.data.length === 0 ? <EmptyState recommendations/> : <>{results.data.length < results.meta.requested_limit && <p className="notice" role="status">Fewer than {results.meta.requested_limit} journals are available under these filters. Your requirements have not been relaxed.</p>}<div className="results-list">{results.data.map((j, i) => <JournalCard key={j.id} journal={j} recommendation={j} rank={i + 1} selected={selected.some(v => v.journal.id === j.id)} disabled={selected.length >= 5} onToggle={() => toggle({ journal: j, recommendation: j })}/>)}</div><p className="small">Inspect the journal&apos;s current details before making a publication decision. Unknown information is not a negative finding.</p></>}</>;
}
