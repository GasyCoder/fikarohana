"use client";
import { Fragment, createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { projectsApi, type User } from "../lib/projects";
import { ApiError } from "../lib/api";
import { useDiscovery } from "./discovery-context";
import { Loading } from "./ui";
type Auth = { user: User | null; loading: boolean; error: string | null; setUser: (user: User | null) => void; logout: () => Promise<void> };
const Context = createContext<Auth | null>(null);
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null), [loading, setLoading] = useState(true), [error, setError] = useState<string | null>(null);
  const { clearResearch } = useDiscovery();
  useEffect(() => { let live = true; projectsApi.me().then(r => { if (live) setUser(r.data); }).catch(e => { if (live && (!(e instanceof ApiError) || e.status !== 401)) setError("Could not check your session. Try signing in again."); }).finally(() => { if (live) setLoading(false); }); return () => { live = false; }; }, []);
  useEffect(() => { const expired = () => setUser(null); window.addEventListener("fikarohana-session-expired", expired); return () => window.removeEventListener("fikarohana-session-expired", expired); }, []);
  async function logout() { try { await projectsApi.logout(); } catch (e) { if (!(e instanceof ApiError) || e.status !== 401) throw e; } setUser(null); clearResearch(); }
  return <Context.Provider value={{ user, loading, error, setUser, logout }}>{children}</Context.Provider>;
}
export function useAuth() { const auth = useContext(Context); if (!auth) throw new Error("Missing authentication context"); return auth; }
export function AuthRequired({ children }: { children: ReactNode }) {
  const { user, loading, error } = useAuth();
  if (loading) return <Loading label="Checking your session…"/>;
  return user ? <Fragment key={user.id}>{children}</Fragment> : <><AuthRequiredMessage/>{error && <p role="alert">{error}</p>}<SignIn/></>;
}
function SignIn() {
  const { setUser } = useAuth();
  const [register, setRegister] = useState(false), [pending, setPending] = useState(false), [error, setError] = useState<string | null>(null);
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); setPending(true); setError(null);
    const email = String(data.get("email")), password = String(data.get("password"));
    try {
      if (register) await projectsApi.register(String(data.get("name")), email, password, String(data.get("confirmation")));
      const result = await projectsApi.login(email, password); form.reset(); setUser(result.data);
    } catch (e) { setError(e instanceof ApiError && e.status === 422 ? Object.values(e.fields).flat().join(" ") : e instanceof ApiError && e.status === 419 ? "Session expired. Please try again." : "Sign-in failed. Check your details and the connection."); }
    finally { setPending(false); }
  }
  return <form onSubmit={submit} className="research-form auth-form"><h2>{register ? "Create an account" : "Sign in"}</h2><fieldset disabled={pending}>{register && <div className="field"><label htmlFor="account-name">Name</label><input id="account-name" name="name" required maxLength={255} autoComplete="name"/></div>}<div className="field"><label htmlFor="account-email">Email</label><input id="account-email" name="email" type="email" required autoComplete="username"/></div><div className="field"><label htmlFor="account-password">Password</label><input id="account-password" name="password" type="password" required minLength={register ? 12 : undefined} autoComplete={register ? "new-password" : "current-password"}/></div>{register && <div className="field"><label htmlFor="account-confirmation">Confirm password</label><input id="account-confirmation" name="confirmation" type="password" required autoComplete="new-password"/></div>}{error && <p role="alert">{error}</p>}<button className="button" disabled={pending}>{pending ? "Please wait…" : register ? "Create account and sign in" : "Sign in"}</button><button type="button" className="text-link" onClick={() => setRegister(!register)}>{register ? "Use an existing account" : "Create an account"}</button></fieldset><p className="small">Your research stays in this tab while you sign in. Reloading clears unsaved research.</p></form>;
}

export function AuthRequiredMessage() { return <p className="notice">Sign in to save private research projects. Anonymous journal discovery remains available.</p>; }
