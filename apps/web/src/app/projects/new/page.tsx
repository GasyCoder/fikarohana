"use client";
import { Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Link from "next/link";
import { AuthRequired } from "../../../components/auth-context";
import { useDiscovery } from "../../../components/discovery-context";
import { ProjectEditor } from "../../../components/project-editor";
import { ErrorState, Loading } from "../../../components/ui";
import { projectsApi, type Project } from "../../../lib/projects";
export default function NewProjectPage() { return <AuthRequired><Suspense fallback={<Loading/>}><NewProject/></Suspense></AuthRequired>; }
function NewProject() {
  const router = useRouter(), query = useSearchParams(), journal = query.get("journal"); const { research, setResearch } = useDiscovery();
  const [projects, setProjects] = useState<Project[]>([]), [selected, setSelected] = useState(""), [error, setError] = useState<unknown>(null), [pending, setPending] = useState(false), [version, setVersion] = useState(0), [page, setPage] = useState(1), [lastPage, setLastPage] = useState(1);
  useEffect(() => { if (!journal) return; let live = true; projectsApi.list(page).then(v => { if (live) { setProjects(v.data); setLastPage(v.meta.last_page); setError(null); } }).catch(e => { if (live) setError(e); }); return () => { live = false; }; }, [journal, page, version]);
  async function select() { if (!journal || !selected) return; setPending(true); try { await projectsApi.select(selected, journal); router.push(`/projects/${selected}`); } catch (e) { setError(e); } finally { setPending(false); } }
  return <><Link className="back-link" href="/projects">← Projects</Link><div className="page-heading"><h1>{journal ? "Choose a project for this journal" : "Save your research"}</h1><p>Journal selection records your choice. It does not submit a manuscript.</p></div>{journal && <section className="detail-section"><h2>Use an existing project</h2><label htmlFor="target-project">Project</label><select id="target-project" value={selected} onChange={e => setSelected(e.target.value)}><option value="">Choose a project</option>{projects.map(p => <option key={p.id} value={p.id}>{p.title}</option>)}</select><button className="button" disabled={!selected || pending} onClick={select}>{pending ? "Selecting…" : "Select journal for project"}</button>{lastPage > 1 && <div className="detail-actions"><button disabled={page <= 1} onClick={() => { setSelected(""); setPage(p => p - 1); }}>Previous projects</button><button disabled={page >= lastPage} onClick={() => { setSelected(""); setPage(p => p + 1); }}>Next projects</button></div>}</section>}{error && <ErrorState error={error} retry={() => setVersion(v => v + 1)}/>}<h2>Create a new project</h2><ProjectEditor initial={research} onSave={async payload => { const result = await projectsApi.create(payload); setResearch({ ...research, title: payload.title, abstract: payload.abstract ?? "", keywords: payload.keywords?.join(", ") ?? "" }); if (journal) { try { await projectsApi.select(result.data.id, journal); } catch { router.push(`/projects/${result.data.id}?selection=unavailable`); return; } } router.push(`/projects/${result.data.id}`); }}/></>;
}
