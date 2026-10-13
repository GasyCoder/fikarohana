"use client";
import Link from "next/link";
import { useDiscovery } from "../../components/discovery-context";
import { ComparisonTable } from "../../components/comparison-table";
import { RelevanceNote } from "../../components/ui";
export default function ComparePage() {
    const { selected, remove, clear } = useDiscovery();
    return <><div className="page-heading heading-actions"><div><p className="eyebrow">Make an informed choice</p><h1>Compare your journals.</h1><p>Up to five journals, with the information that matters.</p></div>{selected.length > 0 && <button className="text-link" type="button" onClick={clear}>Clear selection</button>}</div>{selected.length === 0 ? <div className="state-panel"><h2>Your comparison starts here.</h2><p>Select journals from browse or recommendation results. Nothing is saved after you close or reload this tab.</p><Link href="/journals" className="button">Browse journals →</Link></div> : <>{selected.length === 1 && <p className="notice" role="status">One journal selected. Add another to compare side by side.</p>}<ComparisonTable entries={selected} remove={remove}/><RelevanceNote /><p className="small">Relevance scores are shown only for journals from your recommendation results. Unknown means information is unavailable, not that a requirement is unsupported.</p><Link href="/journals" className="text-link">Add another journal →</Link></>}</>;
}
