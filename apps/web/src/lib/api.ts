import type { ApiErrorBody, JournalDetail, JournalList, RecommendationRequest, RecommendationResponse } from "./discovery";
export class ApiError extends Error {
    constructor(public status: number, public code: string, public fields: Record<string, string[]> = {}, public requestId?: string) {
        super(status === 404 ? "This journal is not available in the published catalogue." : status === 422 ? "Please check the highlighted fields." : status === 429 ? "Too many requests. Please wait a minute and try again." : "We could not reach the journal catalogue. Please try again.");
    }
}
async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20000);
    const abort = () => controller.abort();
    init.signal?.addEventListener("abort", abort, { once: true });
    if (init.signal?.aborted)
        controller.abort();
    try {
        const response = await fetch(`/api/v1${path}`, { ...init, signal: controller.signal, credentials: "omit", cache: "no-store", headers: { Accept: "application/json", ...(init.body ? { "Content-Type": "application/json" } : {}) } });
        const body = await response.json().catch(() => null);
        if (!response.ok) {
            const error = (body as ApiErrorBody | null)?.error;
            throw new ApiError(response.status, error?.code ?? "unavailable", error?.fields ?? {}, error?.request_id ?? response.headers.get("X-Request-ID") ?? undefined);
        }
        if (body === null)
            throw new ApiError(502, "invalid_response");
        return body as T;
    }
    catch (error) {
        if (error instanceof ApiError || init.signal?.aborted)
            throw error;
        throw new ApiError(503, "unavailable");
    }
    finally {
        clearTimeout(timer);
        init.signal?.removeEventListener("abort", abort);
    }
}
export const api = {
    journals: (query: string, signal?: AbortSignal) => request<JournalList>(`/journals?${query}`, { signal }),
    journal: (id: string, signal?: AbortSignal) => request<{
        data: JournalDetail;
    }>(`/journals/${encodeURIComponent(id)}`, { signal }),
    recommend: (payload: RecommendationRequest, signal?: AbortSignal) => request<RecommendationResponse>("/journal-recommendations", { method: "POST", body: JSON.stringify(payload), signal }),
};
