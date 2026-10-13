"use client";
import { useEffect, useState } from "react";
import { api } from "../lib/api";
import { useDiscovery } from "./discovery-context";
export function useCatalogueOptions() {
    const { observe } = useDiscovery();
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<unknown>(null);
    const [version, setVersion] = useState(0);
    useEffect(() => { const controller = new AbortController(); api.journals("per_page=100&page=1", controller.signal).then(v => { observe(v.data); setLoading(false); }).catch(e => { if (!controller.signal.aborted) {
        setError(e);
        setLoading(false);
    } }); return () => controller.abort(); }, [observe, version]);
    return { loading, error, retry: () => { setError(null); setLoading(true); setVersion(v => v + 1); } };
}
export function CatalogueSelect({ id, label, value, onChange, options, loading = false }: {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: {
        value: string;
        label: string;
    }[];
    loading?: boolean;
}) {
    return <div className="field"><label htmlFor={id}>{label}</label><select id={id} value={value} onChange={e => onChange(e.target.value)} disabled={loading || (!options.length && !value)}><option value="">{loading ? "Loading options…" : options.length ? "Any / no preference" : "No options available"}</option>{value && !options.some(o => o.value === value) && <option value={value}>Current selection — not in this catalogue sample</option>}{options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}</select></div>;
}
