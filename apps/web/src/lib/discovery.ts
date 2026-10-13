import type { components } from "../../../../packages/contracts/generated/api";
export type Journal = components["schemas"]["DiscoveryJournal"];
export type JournalDetail = components["schemas"]["DiscoveryJournalDetail"];
export type Recommendation = components["schemas"]["RecommendedJournal"];
export type RecommendationRequest = components["schemas"]["JournalRecommendationRequest"];
export type RecommendationResponse = components["schemas"]["JournalRecommendationResponse"];
export type JournalList = components["schemas"]["DiscoveryList"];
export type ApiErrorBody = components["schemas"]["ErrorResponse"];
export type CompareEntry = {
    journal: Journal;
    recommendation?: Recommendation;
};
export const relevanceMessage = "The relevance score measures metadata compatibility with your research. It does not predict manuscript acceptance.";
export type ResearchForm = {
    title: string;
    abstract: string;
    keywords: string;
    subject: string;
    language: string;
    articleType: string;
    noApc: boolean;
    indexations: string[];
};
export const emptyResearch: ResearchForm = { title: "", abstract: "", keywords: "", subject: "", language: "", articleType: "", noApc: false, indexations: [] };
export function researchRequest(form: ResearchForm): {
    payload?: RecommendationRequest;
    errors: Record<string, string>;
} {
    const errors: Record<string, string> = {};
    const title = form.title.trim();
    if (!title)
        errors.title = "Enter a title for your research.";
    if ([...title].length > 500)
        errors.title = "Use no more than 500 characters.";
    if ([...form.abstract].length > 10000)
        errors.abstract = "Use no more than 10,000 characters.";
    const keywords = [...new Set(form.keywords.split(",").map(v => v.trim()).filter(Boolean))];
    if (keywords.length > 20 || keywords.some(v => [...v].length > 100))
        errors.keywords = "Use up to 20 keywords, each no longer than 100 characters.";
    if (form.indexations.length > 10)
        errors.indexations = "Select up to 10 indexation services.";
    if (Object.keys(errors).length)
        return { errors };
    return { errors, payload: { title, limit: 5, ...(form.abstract.trim() ? { abstract: form.abstract.trim() } : {}), ...(keywords.length ? { keywords } : {}), ...(form.subject ? { subject_ids: [form.subject] } : {}), ...(form.language ? { language: form.language } : {}), ...(form.articleType ? { article_type: form.articleType } : {}), ...(form.noApc ? { no_apc_only: true } : {}), ...(form.indexations.length ? { indexation_services: form.indexations } : {}) } };
}
export type BrowseFilters = {
    q: string;
    subject: string;
    language: string;
    articleType: string;
    indexation: string;
    publisher: string;
    noApc: boolean;
};
export const emptyBrowse: BrowseFilters = { q: "", subject: "", language: "", articleType: "", indexation: "", publisher: "", noApc: false };
export function browseQuery(filters: BrowseFilters, page = 1): string {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    for (const [key, value] of Object.entries({ q: filters.q.trim(), subject: filters.subject, language: filters.language, article_type: filters.articleType, indexation: filters.indexation, publisher: filters.publisher }))
        if (value)
            params.set(key, value);
    if (filters.noApc)
        params.set("no_apc", "true");
    return params.toString();
}
export function browseFromQuery(params: URLSearchParams): BrowseFilters {
    return { q: params.get("q") ?? "", subject: params.get("subject") ?? "", language: params.get("language") ?? "", articleType: params.get("article_type") ?? "", indexation: params.get("indexation") ?? "", publisher: params.get("publisher") ?? "", noApc: params.get("no_apc") === "true" };
}
export function apcLabel(apc: Journal["apc"]): string {
    if (apc.status === "no_apc")
        return "No APC reported";
    if (apc.status === "context_dependent")
        return "Depends on context";
    if (apc.status === "known" && apc.amount !== null && apc.currency)
        return `${apc.currency} ${Number(apc.amount).toLocaleString("en", { maximumFractionDigits: 4 })}`;
    return "Unknown";
}
export function supportedNames(values: Journal["languages"]): string {
    return values.filter(v => v.supported).map(v => v.name).join(", ") || "Not available";
}
export function toggleCompare(entries: CompareEntry[], entry: CompareEntry): {
    entries: CompareEntry[];
    error: string | null;
} {
    if (entries.some(v => v.journal.id === entry.journal.id))
        return { entries: entries.filter(v => v.journal.id !== entry.journal.id), error: null };
    if (entries.length >= 5)
        return { entries, error: "You can compare up to five journals. Remove one to add another." };
    return { entries: [...entries, entry], error: null };
}
export function safeSourceUrl(value: string | null): string | undefined {
    if (!value)
        return;
    try {
        const url = new URL(value);
        if (["https:", "http:"].includes(url.protocol) && !url.username && !url.password)
            return url.href;
    }
    catch { }
}
export function catalogOptions(journals: Journal[]) {
    const unique = <T,>(values: T[], key: (value: T) => string, label: (value: T) => string): T[] => [...new Map(values.map(v => [key(v), v])).values()].sort((a, b) => label(a).localeCompare(label(b), "en"));
    const subjects = unique(journals.flatMap(j => j.subjects), v => v.id, v => v.name);
    const vocabulary = (key: "languages" | "article_types" | "indexations") => unique(journals.flatMap(j => j[key]).filter(v => v.supported), v => v.code, v => v.name);
    const publishers = unique(journals.flatMap(j => j.publisher ? [j.publisher] : []), v => v.id, v => v.name);
    return { subjects, languages: vocabulary("languages"), articleTypes: vocabulary("article_types"), indexations: vocabulary("indexations"), publishers };
}
