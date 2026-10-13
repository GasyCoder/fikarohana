import type { components } from "../../../../packages/contracts/generated/api";
import { ApiError } from "./api";
import { researchRequest, type ResearchForm } from "./discovery";
export type Project = components["schemas"]["ResearchProject"];
export type ProjectInput = components["schemas"]["ProjectInput"];
export type ProjectList = components["schemas"]["ProjectList"];
export type WorkspaceJournal = components["schemas"]["WorkspaceJournal"];
export type User = components["schemas"]["User"];
function csrfToken(): string | undefined {
  if (typeof document === "undefined") return undefined;
  const value = document.cookie.split("; ").find(v => v.startsWith("XSRF-TOKEN="))?.slice(11);
  return value ? decodeURIComponent(value) : undefined;
}
async function csrf(): Promise<void> {
  const response = await fetch("/sanctum/csrf-cookie", { credentials: "include", cache: "no-store", signal: AbortSignal.timeout(20000), headers: { Accept: "application/json" } });
  if (!response.ok) throw new ApiError(response.status, "csrf_unavailable");
}
async function request<T>(path: string, method = "GET", payload?: unknown): Promise<T> {
  if (method !== "GET" && !csrfToken()) await csrf();
  const token = csrfToken();
  const headers: Record<string, string> = { Accept: "application/json", ...(payload ? { "Content-Type": "application/json" } : {}), ...(token ? { "X-XSRF-TOKEN": token } : {}) };
  let response: Response;
  try { response = await fetch(`/api/v1${path}`, { method, credentials: "include", cache: "no-store", headers, signal: AbortSignal.timeout(20000), ...(payload ? { body: JSON.stringify(payload) } : {}) }); }
  catch { throw new ApiError(503, "unavailable"); }
  if (response.status === 204) return undefined as T;
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    if (response.status === 401 && typeof window !== "undefined") window.dispatchEvent(new Event("fikarohana-session-expired"));
    if (response.status === 419) await csrf(); // Prepare a later explicit retry; never replay a write.
    const error = new ApiError(response.status, body?.error?.code ?? "unavailable", body?.error?.fields ?? {});
    if (response.status === 404) error.message = "This project is not available.";
    else if (response.status === 403) error.message = "You do not have access to this project.";
    else if (response.status === 401) error.message = "Please sign in to continue.";
    else if (response.status === 419) error.message = "Session expired. Please try again.";
    else if (response.status >= 500) error.message = "Could not reach your projects. Please try again.";
    throw error;
  }
  if (!body) throw new ApiError(502, "invalid_response");
  return body as T;
}
export function projectInput(form: ResearchForm): { payload?: ProjectInput; errors: Record<string, string> } {
  const mapped = researchRequest(form);
  if (!mapped.payload) return { errors: mapped.errors };
  const { title, abstract, keywords, subject_ids, language, article_type, no_apc_only, indexation_services } = mapped.payload;
  return { errors: {}, payload: { title, abstract: abstract ?? null, keywords: keywords ?? [], subject_ids: subject_ids ?? [], language: language ?? null, article_type: article_type ?? null, no_apc_only: no_apc_only ?? false, indexation_services: indexation_services ?? [] } };
}
export const projectsApi = {
  me: () => request<{ data: User }>("/me"),
  login: async (email: string, password: string) => { await csrf(); return request<{ data: User }>("/auth/login", "POST", { email, password }); },
  register: async (name: string, email: string, password: string, confirmation: string) => { await csrf(); return request<{ data: User }>("/auth/register", "POST", { name, email, password, password_confirmation: confirmation }); },
  logout: () => request("/auth/logout", "POST"),
  list: (page = 1) => request<ProjectList>(`/projects?page=${page}&per_page=20`),
  create: (payload: ProjectInput) => request<{ data: Project }>("/projects", "POST", payload),
  show: (id: string) => request<{ data: Project }>(`/projects/${encodeURIComponent(id)}`),
  update: (id: string, payload: components["schemas"]["ProjectUpdate"]) => request<{ data: Project }>(`/projects/${encodeURIComponent(id)}`, "PATCH", payload),
  remove: (id: string) => request<void>(`/projects/${encodeURIComponent(id)}`, "DELETE"),
  select: (id: string, journalId: string) => request<{ data: Project }>(`/projects/${encodeURIComponent(id)}/journal`, "PUT", { journal_id: journalId }),
};
