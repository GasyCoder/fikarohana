"use client";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { api } from "../../../lib/api";
import type { JournalDetail } from "../../../lib/discovery";
import { useDiscovery } from "../../../components/discovery-context";
import { ErrorState, Loading } from "../../../components/ui";
import { JournalDetailView } from "../../../components/journal-detail";
function JournalContent() {
    const { id } = useParams<{
        id: string;
    }>();
    const [version, setVersion] = useState(0);
    const key = id + ":" + version;
    const [state, setState] = useState<{
        key: string;
        data?: JournalDetail;
        error?: unknown;
    }>({ key: "" });
    const { observe, selected, toggle, results } = useDiscovery();
    useEffect(() => { const controller = new AbortController(); api.journal(id, controller.signal).then(v => { if (!controller.signal.aborted) {
        setState({ key, data: v.data });
        observe([v.data]);
    } }).catch(error => { if (!controller.signal.aborted)
        setState({ key, error }); }); return () => controller.abort(); }, [id, key, observe]);
    return <><Link href="/journals" className="back-link">← Browse journals</Link>{state.key !== key ? <Loading /> : state.error ? <ErrorState error={state.error} retry={() => setVersion(v => v + 1)}/> : state.data && <><JournalDetailView journal={state.data}/><div className="detail-actions"><button type="button" className="button secondary" aria-pressed={selected.some(v => v.journal.id === id)} disabled={selected.length >= 5 && !selected.some(v => v.journal.id === id)} onClick={() => toggle({ journal: state.data!, recommendation: results?.data.find(v => v.id === id) })}>{selected.some(v => v.journal.id === id) ? "Remove from comparison" : "Compare this journal"}</button><Link href="/compare" className="text-link">View comparison →</Link></div></>}</>;
}

export default function JournalPage() { return <Suspense fallback={<Loading/>}><JournalContent/></Suspense>; }
