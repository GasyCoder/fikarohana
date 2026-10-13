"use client";
import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { api } from "../../lib/api";
import { browseFromQuery, browseQuery, type BrowseFilters, type JournalList } from "../../lib/discovery";
import { useDiscovery } from "../../components/discovery-context";
import { CatalogueSelect, useCatalogueOptions } from "../../components/catalogue-options";
import { EmptyState, ErrorState, JournalCard, Loading } from "../../components/ui";
function BrowseContent({ search }: {
    search: string;
}) {
    const params = new URLSearchParams(search);
    const page = Math.max(1, Math.min(10000, Number(params.get("page")) || 1));
    const applied = browseFromQuery(params);
    const [filters, setFilters] = useState<BrowseFilters>(applied);
    const router = useRouter();
    const { options, observe, selected, toggle } = useDiscovery();
    const metadata = useCatalogueOptions();
    const [version, setVersion] = useState(0);
    const [state, setState] = useState<{
        key: string;
        data?: JournalList;
        error?: unknown;
    }>({ key: "" });
    const query = browseQuery(applied, page);
    const key = query + ":" + version;
    useEffect(() => { const controller = new AbortController(); api.journals(query, controller.signal).then(data => { if (!controller.signal.aborted) {
        setState({ key, data });
        observe(data.data);
    } }).catch(error => { if (!controller.signal.aborted)
        setState({ key, error }); }); return () => controller.abort(); }, [query, key, observe]);
    const pending = state.key !== key;
    const data = pending ? undefined : state.data;
    function update<K extends keyof BrowseFilters>(k: K, v: BrowseFilters[K]) { setFilters(prev => ({ ...prev, [k]: v })); }
    function navigate(page: number, f = applied) { router.push(`/journals?${browseQuery(f, page)}`, { scroll: false }); }
    return <><div className="page-heading"><p className="eyebrow">Published journal catalogue</p><h1>Explore your options.</h1><p>Search journal metadata, refine your requirements, and inspect the evidence.</p></div><div className="browse-layout"><details className="filter-panel" open><summary>Search &amp; filters</summary><form onSubmit={e => { e.preventDefault(); navigate(1, filters); }}><div className="field"><label htmlFor="journal-search">Search journals</label><input id="journal-search" maxLength={500} value={filters.q} onChange={e => update("q", e.target.value)} placeholder="Title, subject or publisher"/></div><CatalogueSelect id="browse-subject" label="Subject" value={filters.subject} onChange={v => update("subject", v)} options={options.subjects.map(v => ({ value: v.id, label: v.name }))} loading={metadata.loading}/><CatalogueSelect id="browse-language" label="Language" value={filters.language} onChange={v => update("language", v)} options={options.languages.map(v => ({ value: v.code, label: v.name }))} loading={metadata.loading}/><CatalogueSelect id="browse-type" label="Article type" value={filters.articleType} onChange={v => update("articleType", v)} options={options.articleTypes.map(v => ({ value: v.code, label: v.name }))} loading={metadata.loading}/><CatalogueSelect id="browse-index" label="Indexation" value={filters.indexation} onChange={v => update("indexation", v)} options={options.indexations.map(v => ({ value: v.code, label: v.name }))} loading={metadata.loading}/><CatalogueSelect id="browse-publisher" label="Publisher" value={filters.publisher} onChange={v => update("publisher", v)} options={options.publishers.map(v => ({ value: v.id, label: v.name }))} loading={metadata.loading}/><label className="check"><input type="checkbox" checked={filters.noApc} onChange={e => update("noApc", e.target.checked)}/>Explicit no APC only</label><p className="field-hint">Unknown fees and a zero amount alone do not qualify. Other charges may apply.</p>{Boolean(metadata.error) && <p className="option-warning" role="status">Some filter options are unavailable. <button type="button" onClick={metadata.retry} className="text-link">Retry options</button></p>}<div className="filter-actions"><button className="button" type="submit">Apply filters</button><button className="text-link" type="button" onClick={() => router.push("/journals")}>Clear filters</button></div></form></details><section aria-label="Journal results" aria-busy={pending}>{pending ? <Loading /> : state.error ? <ErrorState error={state.error} retry={() => setVersion(v => v + 1)}/> : data && <><div className="results-meta"><strong>{data.meta.total} journals</strong><span>Page {data.meta.current_page} of {data.meta.last_page}</span></div>{data.data.length ? <div className="results-list">{data.data.map(j => <JournalCard key={j.id} journal={j} selected={selected.some(v => v.journal.id === j.id)} disabled={selected.length >= 5} onToggle={() => toggle({ journal: j })}/>)}</div> : <EmptyState />}<nav className="pagination" aria-label="Journal pagination"><button type="button" className="button secondary" disabled={page <= 1} onClick={() => navigate(page - 1)}>← Previous</button><span>Page {page}</span><button type="button" className="button secondary" disabled={page >= data.meta.last_page} onClick={() => navigate(page + 1)}>Next →</button></nav></>}</section></div></>;
}
function BrowseRoute() { const params = useSearchParams(); return <BrowseContent key={params.toString()} search={params.toString()}/>; }
export default function BrowsePage() { return <Suspense fallback={<Loading />}><BrowseRoute /></Suspense>; }
