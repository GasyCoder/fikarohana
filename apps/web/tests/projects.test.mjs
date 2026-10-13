import test from "node:test";
import assert from "node:assert/strict";
import { createRequire } from "node:module";
import { join } from "node:path";
const req = createRequire(join(process.env.FIKAROHANA_WEB_TEST_BUILD, "test.cjs"));
const React=req("react"),{renderToStaticMarkup}=req("react-dom/server");
const load=n=>req(join(process.env.FIKAROHANA_WEB_TEST_BUILD,n));
const {projectInput,projectsApi}=load("lib/projects.js"),{emptyResearch}=load("lib/discovery.js");
const {ProjectWorkspace,ProjectListView,DeleteConfirmation}=load("components/project-workspace.js"),{ProjectEditor}=load("components/project-editor.js"),{AuthRequiredMessage}=load("components/auth-context.js");
const html=(c,p={})=>renderToStaticMarkup(React.createElement(c,p));
const project={id:"p",title:"Private ecology study",abstract:"Unpublished summary",keywords:["ecology"],subject_ids:[],language:null,article_type:null,no_apc_only:false,indexation_services:[],selected_journal_id:null,journal_selected_at:null,created_at:"2026-10-13T00:00:00Z",updated_at:"2026-10-13T00:00:00Z",journal:null};
const journal={id:"j",revision_id:"r",title:"Published journal",publisher:null,identifiers:[],subjects:[],languages:[],article_types:[],indexations:[],fees:[],apc:{status:"unknown",amount:null,currency:null},requirements:[],templates:[],provenance:[]};
test("project mapping retains research intent and excludes scores and temporary ranking controls",()=>{
 const result=projectInput({...emptyResearch,title:" Study ",abstract:" Summary ",keywords:"ecology, ecology",subject:"canonical",language:"en",articleType:"research_article",noApc:true,indexations:["doaj"]});
 assert.deepEqual(result.payload,{title:"Study",abstract:"Summary",keywords:["ecology"],subject_ids:["canonical"],language:"en",article_type:"research_article",no_apc_only:true,indexation_services:["doaj"]});
 assert.ok(projectInput(emptyResearch).errors.title);
});
test("project list renders own supplied projects and selected journal or empty state",()=>{
 assert.match(html(ProjectListView,{projects:[],onDelete(){}}),/No projects yet/);
 const s=html(ProjectListView,{projects:[{...project,selected_journal_id:"j",journal}],onDelete(){}});
 assert.match(s,/Private ecology study/);assert.match(s,/Published journal/);assert.match(s,/Delete project/);
});
test("creation editor renders transient research for explicit private saving",()=>{
 const {DiscoveryProvider}=load("components/discovery-context.js");
 const s=renderToStaticMarkup(React.createElement(DiscoveryProvider,null,React.createElement(ProjectEditor,{initial:{...emptyResearch,title:"Saved from discovery",abstract:"Summary"},onSave:async()=>{}})));
 assert.match(s,/Saved from discovery/);assert.match(s,/Create private project/);assert.match(s,/Recommendation scores and result lists are not saved/);
});
test("workspace sections preserve unknown metadata without invented requirements",()=>{
 const s=html(ProjectWorkspace,{project});
 for(const text of ["Overview","Requirements","Fees","Templates","Sources","No journal selected","Unknown","No missing requirement is inferred"])assert.ok(s.includes(text),text);
 assert.match(s,/Unpublished summary/);
});
test("workspace displays canonical requirements, qualifiers and safe official links",()=>{
 const j={...journal,requirements:[{type:"abstract_words",availability:"known",operator:"max",value_number:250,value_text:null,value_items:null,notes:"Structured abstract only",article_type:"research_article",language:null}],templates:[{format:"word",label:"Official",official_url:"https://journal.example/template",article_type:null,language:null}],provenance:[{field:"requirement",source_url:"https://journal.example/authors",retrieved_at:null,verified_at:null}]};
 const s=html(ProjectWorkspace,{project:{...project,journal:j,selected_journal_id:"j"}});
 assert.match(s,/max 250/);assert.match(s,/Structured abstract only/);assert.match(s,/noopener noreferrer/);assert.match(s,/journal.example/);
 assert.doesNotMatch(s,/assertion_id|source_record_id/);
});
test("numeric zero remains known currency; unsafe template links are omitted",()=>{
 const j={...journal,apc:{status:"known",amount:"0.0000",currency:"USD"},templates:[{format:"word",label:"Bad",official_url:"javascript:alert(1)",article_type:null,language:null}]};
 const s=html(ProjectWorkspace,{project:{...project,journal:j}});
 assert.match(s,/USD 0/);assert.doesNotMatch(s,/No APC reported|javascript:/);
});
test("auth-required message preserves anonymous discovery",()=>{
 assert.match(html(AuthRequiredMessage),/Sign in/);assert.match(html(AuthRequiredMessage),/Anonymous journal discovery remains available/);
});
test("deletion requires separate confirmation and offers cancellation",()=>{
 let calls=0;const tree=DeleteConfirmation({title:"Study",pending:false,onConfirm(){calls++},onCancel(){}});
 const s=html(DeleteConfirmation,{title:"Study",pending:false,onConfirm(){calls++},onCancel(){}});
 assert.equal(calls,0);assert.match(s,/Confirm deletion/);assert.match(s,/Cancel/);
 const confirm=tree.props.children.find(v=>v?.type==="button");confirm.props.onClick();assert.equal(calls,1);
});
test("private API uses session cookies and CSRF, never bearer storage or public discovery credentials",async()=>{
 const previous=global.fetch;global.document={cookie:"XSRF-TOKEN=synthetic%20csrf"};const calls=[];
 global.fetch=async(url,init)=>{calls.push({url,init});return new Response(JSON.stringify({data:project}),{status:200,headers:{"Content-Type":"application/json"}})};
 try{
  await projectsApi.create({title:"Private study"});await projectsApi.select("p","canonical-journal");await projectsApi.update("p",{abstract:null});
  assert.equal(calls[0].url,"/api/v1/projects");assert.equal(calls[0].init.credentials,"include");assert.equal(calls[0].init.cache,"no-store");assert.equal(calls[0].init.headers["X-XSRF-TOKEN"],"synthetic csrf");
  assert.deepEqual(JSON.parse(calls[1].init.body),{journal_id:"canonical-journal"});assert.equal(calls[1].init.method,"PUT");assert.equal(calls[2].init.method,"PATCH");
  global.fetch=async()=>new Response(null,{status:204});assert.equal(await projectsApi.remove("p"),undefined);
 }finally{global.fetch=previous;delete global.document;}
});
