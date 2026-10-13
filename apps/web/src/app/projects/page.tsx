"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { AuthRequired, useAuth } from "../../components/auth-context";
import { ProjectListView, DeleteConfirmation } from "../../components/project-workspace";
import { projectsApi, type Project, type ProjectList } from "../../lib/projects";
import { ErrorState, Loading } from "../../components/ui";
export default function ProjectsPage() { return <><div className="page-heading"><p className="eyebrow">Your research</p><h1>Private projects</h1></div><AuthRequired><Projects/></AuthRequired></>; }
function Projects() {
  const { logout } = useAuth(); const [data, setData] = useState<ProjectList | null>(null), [error, setError] = useState<unknown>(null), [version, setVersion] = useState(0), [page, setPage] = useState(1), [deleting, setDeleting] = useState<Project | null>(null), [pending, setPending] = useState(false);
  useEffect(() => { let live = true; projectsApi.list(page).then(v => { if (live) { setData(v); setError(null); } }).catch(e => { if (live) setError(e); }); return () => { live = false; }; }, [page, version]);
  async function remove() { if (!deleting) return; setPending(true); try { await projectsApi.remove(deleting.id); setDeleting(null); setVersion(v => v + 1); } catch (e) { setError(e); } finally { setPending(false); } }
  return <><div className="detail-actions"><Link className="button" href="/projects/new">Create project</Link><button className="text-link" onClick={() => logout().catch(setError)}>Sign out</button></div>{deleting && <DeleteConfirmation title={deleting.title} pending={pending} onConfirm={remove} onCancel={() => setDeleting(null)}/>} {error ? <ErrorState error={error} retry={() => setVersion(v => v + 1)}/> : data ? <><ProjectListView projects={data.data} onDelete={setDeleting}/><div className="detail-actions"><button className="button secondary" disabled={page <= 1} onClick={() => setPage(v => v - 1)}>Previous</button><span>Page {data.meta.current_page} of {data.meta.last_page}</span><button className="button secondary" disabled={page >= data.meta.last_page} onClick={() => setPage(v => v + 1)}>Next</button></div></> : <Loading/>}</>;
}
