"use client";
import { createContext, useCallback, useContext, useState, type ReactNode } from "react";
import { catalogOptions, toggleCompare, type CompareEntry, type Journal, type RecommendationResponse } from "../lib/discovery";
type State = {
    results: RecommendationResponse | null;
    setResults: (v: RecommendationResponse) => void;
    selected: CompareEntry[];
    toggle: (entry: CompareEntry) => void;
    remove: (id: string) => void;
    clear: () => void;
    selectionError: string | null;
    observed: Journal[];
    observe: (journals: Journal[]) => void;
    options: ReturnType<typeof catalogOptions>;
};
const Context = createContext<State | null>(null);
export function DiscoveryProvider({ children }: {
    children: ReactNode;
}) {
    const [results, setResults] = useState<RecommendationResponse | null>(null);
    const [selected, setSelected] = useState<CompareEntry[]>([]);
    const [selectionError, setSelectionError] = useState<string | null>(null);
    const [observed, setObserved] = useState<Journal[]>([]);
    function toggle(entry: CompareEntry) { const next = toggleCompare(selected, entry); setSelected(next.entries); setSelectionError(next.error); }
    function remove(id: string) { setSelected(selected.filter(v => v.journal.id !== id)); setSelectionError(null); }
    const observe = useCallback((journals: Journal[]) => { setObserved(previous => [...new Map([...previous, ...journals].map(j => [j.id, j])).values()].slice(-500)); }, []);
    return <Context.Provider value={{ results, setResults, selected, toggle, remove, clear: () => { setSelected([]); setSelectionError(null); }, selectionError, observed, observe, options: catalogOptions(observed) }}>{children}</Context.Provider>;
}
export function useDiscovery() { const state = useContext(Context); if (!state)
    throw new Error("Missing discovery context"); return state; }
