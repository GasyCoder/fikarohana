"use client";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { AuthRequired } from "../../../components/auth-context";
import { ProjectWorkspace } from "../../../components/project-workspace";
import { ProjectEditor } from "../../../components/project-editor";
import { Loading, ErrorState } from "../../../components/ui";
import { emptyResearch } from "../../../lib/discovery";
import { projectsApi, type Project } from "../../../lib/projects";
export default function WorkspacePage() { return <AuthRequired><Suspense fallback={<Loading/>}><WorkspaceRoute/></Suspense></AuthRequired>; }
function WorkspaceRoute() { const { id } = useParams<{ id: string }>(); return <Workspace key={id} id={id}/>; }
function Workspace({ id }: { id: string }) {
  const query = useSearchParams(); const [project, setProject] = useState<Project | null>(null), [error, setError] = useState<unknown>(null), [version, setVersion] = useState(0), [editing, setEditing] = useState(false);
  useEffect(() => { let live = true; projectsApi.show(id).then(v => { if (live) { setProject(v.data); setError(null); } }).catch(e => { if (live) setError(e); }); return () => { live = false; }; }, [id, version]);
  return <><Link href="/projects" className="back-link">← Projects</Link>{query.get("selection") === "unavailable" && <p className="notice">Your project was saved, but the journal could not be selected. Choose a currently published journal.</p>}{error ? <ErrorState error={error} retry={() => setVersion(v => v + 1)}/> : project ? <><ProjectWorkspace project={project}/><section className="detail-section"><button className="button secondary" onClick={() => setEditing(!editing)}>{editing ? "Cancel editing" : "Edit research"}</button>{editing && <ProjectEditor initial={{ ...emptyResearch, title: project.title, abstract: project.abstract ?? "", keywords: project.keywords.join(", "), subject: project.subject_ids[0] ?? "", language: project.language ?? "", articleType: project.article_type ?? "", noApc: project.no_apc_only, indexations: project.indexation_services }} label="Save changes" onSave={async input => { const result = await projectsApi.update(id, { ...input, subject_ids: project.subject_ids }); setProject(result.data); setEditing(false); }}/>}</section></> : <Loading/>}</>;
}
