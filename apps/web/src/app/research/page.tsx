"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError } from "../../lib/api";
import { emptyResearch, researchRequest, type ResearchForm } from "../../lib/discovery";
import { useDiscovery } from "../../components/discovery-context";
import { CatalogueSelect, useCatalogueOptions } from "../../components/catalogue-options";
import { ErrorState, RelevanceNote } from "../../components/ui";
export default function ResearchPage() {
    const [form, setForm] = useState<ResearchForm>({ ...emptyResearch, indexations: [] });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<unknown>(null);
    const { options, setResults, observe } = useDiscovery();
    const metadata = useCatalogueOptions();
    const router = useRouter();
    const abort = useRef<AbortController | null>(null);
    const formRef = useRef<HTMLFormElement>(null);
    useEffect(() => () => abort.current?.abort(), []);
    function update<K extends keyof ResearchForm>(key: K, value: ResearchForm[K]) { setForm(v => ({ ...v, [key]: value })); }
    async function submit(e: React.FormEvent) {
        e.preventDefault();
        if (pending)
            return;
        const mapped = researchRequest(form);
        setErrors(mapped.errors);
        setError(null);
        if (!mapped.payload)
            return;
        const controller = new AbortController();
        abort.current = controller;
        setPending(true);
        try {
            const result = await api.recommend(mapped.payload, controller.signal);
            if (controller.signal.aborted)
                return;
            setResults(result);
            observe(result.data);
            router.push("/recommendations");
        }
        catch (e) {
            if (!controller.signal.aborted) {
                setError(e);
                if (e instanceof ApiError) {
                    const fields: Record<string, string> = {};
                    for (const [key, messages] of Object.entries(e.fields))
                        fields[key.split(".")[0] === "indexation_services" ? "indexations" : key.split(".")[0] === "subject_ids" ? "subject" : key.split(".")[0] === "article_type" ? "articleType" : key.split(".")[0]] = messages.join(" ");
                    setErrors(fields);
                }
            }
        }
        finally {
            if (!controller.signal.aborted)
                setPending(false);
        }
    }
    return <><div className="page-heading"><p className="eyebrow">Journal recommendations</p><h1>Begin with your research.</h1><p>Add a title to get started. An abstract and keywords give the catalogue more context.</p></div><div className="research-layout"><form ref={formRef} onSubmit={submit} className="research-form" noValidate aria-busy={pending}><fieldset disabled={pending}><div className="field"><label htmlFor="title">Research title <span className="required">Required</span></label><input id="title" name="title" value={form.title} onChange={e => update("title", e.target.value)} maxLength={500} required aria-invalid={Boolean(errors.title)} aria-describedby={errors.title ? "title-error" : "title-help"} placeholder="What is your research about?"/><p id="title-help" className="field-hint">Use your working manuscript title or a concise research topic.</p>{errors.title && <p id="title-error" className="field-error" role="alert">{errors.title}</p>}</div><div className="field"><label htmlFor="abstract">Abstract <span className="optional">Optional</span></label><textarea id="abstract" rows={7} value={form.abstract} onChange={e => update("abstract", e.target.value)} maxLength={10000} aria-invalid={Boolean(errors.abstract)} aria-describedby={errors.abstract ? "abstract-help abstract-error" : "abstract-help"} placeholder="A short summary of your question, approach and findings…"/><p id="abstract-help" className="field-hint">Up to 10,000 characters. Your text is not saved.</p>{errors.abstract && <p id="abstract-error" role="alert" className="field-error">{errors.abstract}</p>}</div><div className="field"><label htmlFor="keywords">Keywords <span className="optional">Optional</span></label><input id="keywords" value={form.keywords} onChange={e => update("keywords", e.target.value)} aria-invalid={Boolean(errors.keywords)} aria-describedby={errors.keywords ? "keywords-help keywords-error" : "keywords-help"} placeholder="For example: ecology, conservation, pollination"/><p id="keywords-help" className="field-hint">Separate with commas. Up to 20 keywords.</p>{errors.keywords && <p id="keywords-error" role="alert" className="field-error">{errors.keywords}</p>}</div><details className="advanced" open={Object.keys(errors).some(k => !["title", "abstract", "keywords"].includes(k))}><summary>Preferences and filters <span className="optional">Optional</span></summary><p className="field-hint">Options come from available published journal information. An unavailable option does not mean incompatibility.</p>{Boolean(metadata.error) && <div className="option-warning" role="status">Filter options could not be loaded. You can still search with your title. <button type="button" className="text-link" onClick={metadata.retry}>Retry options</button></div>}<div className="form-grid"><CatalogueSelect id="subject" label="Subject / domain" value={form.subject} onChange={v => update("subject", v)} loading={metadata.loading} options={options.subjects.map(v => ({ value: v.id, label: v.name }))}/><CatalogueSelect id="language" label="Manuscript language" value={form.language} onChange={v => update("language", v)} loading={metadata.loading} options={options.languages.map(v => ({ value: v.code, label: v.name }))}/><CatalogueSelect id="articleType" label="Article type" value={form.articleType} onChange={v => update("articleType", v)} loading={metadata.loading} options={options.articleTypes.map(v => ({ value: v.code, label: v.name }))}/></div><fieldset className="indexation-options"><legend>Required indexation services</legend>{options.indexations.length ? options.indexations.map(v => <label className="check" key={v.code}><input type="checkbox" checked={form.indexations.includes(v.code)} onChange={e => update("indexations", e.target.checked ? [...form.indexations, v.code] : form.indexations.filter(x => x !== v.code))}/>{v.name}</label>) : <p className="field-hint">No services available in the loaded catalogue.</p>}</fieldset><label className="check"><input type="checkbox" checked={form.noApc} onChange={e => update("noApc", e.target.checked)}/>Only journals with explicit no-APC information</label><p className="field-hint">Unknown fees, waivers and a numerical zero do not satisfy this preference. Other charges may apply.</p>{Object.entries(errors).filter(([k]) => !["title", "abstract", "keywords"].includes(k)).map(([k, v]) => <p role="alert" className="field-error" key={k}>{v}</p>)}</details>{Boolean(error) && <ErrorState error={error} retry={() => formRef.current?.requestSubmit()}/>}<button className="button submit-button" disabled={pending} type="submit">{pending ? "Finding matching journals…" : "Find matching journals →"}</button>{pending && <p role="status" className="field-hint">Checking the published catalogue. Your filters stay unchanged.</p>}</fieldset></form><aside className="research-aside"><span className="eyebrow">Your work comes first</span><h2>A shortlist,<br />with reasons.</h2><p>We compare your research with journal titles and subject metadata. Your preferences narrow the eligible catalogue.</p><RelevanceNote /><div className="privacy-note"><strong>Private by design</strong><p>Your title, abstract and keywords are used for this request. No account, saved manuscript or browser storage.</p></div></aside></div></>;
}
