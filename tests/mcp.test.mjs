import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const compose=['compose','--env-file','local/.env','-f','local/compose.yml'];
function cli(file,...args){
 const result=spawnSync('docker',[...compose,'run','--rm','cli','wp','eval-file',`/fwf-tests/${file}`,...args.map(String)],{encoding:'utf8'});
 if(result.status!==0) throw new Error('Fixture setup/cleanup failed (credential output suppressed).');
 return result.stdout.trim();
}
const fixture=JSON.parse(cli('create-agent.php'));
const url=fixture.endpoint;
const headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream','Authorization':`Basic ${Buffer.from(`${fixture.login}:${fixture.password}`).toString('base64')}`};
let checks=0;
function ok(condition,label){assert(condition,label);checks++;console.log('PASS:',label);}
async function rpc(method,params={},extra={}){
 const res=await fetch(url,{method:'POST',headers:{...headers,...extra},body:JSON.stringify({jsonrpc:'2.0',id:checks+1,method,params})});
 return {status:res.status,body:await res.json()};
}
function result(r){return JSON.parse(r.body.result.content[0].text);}
try {
 let r=await rpc('initialize',{protocolVersion:'2025-03-26',capabilities:{},clientInfo:{name:'FWF Node HTTP client',version:'1'}});
 ok(r.status===200 && r.body.result.protocolVersion==='2025-03-26','real HTTP MCP initialization');
 r=await rpc('tools/list');ok(r.body.result.tools.length===4 && !r.body.result.tools.some(t=>/publish|delete|deploy/.test(t.name)),'restricted tool discovery');
 r=await rpc('tools/call',{name:'describe_schema',arguments:{type:'fwf_project'}});
 ok(!r.body.result.isError && result(r).fields.project_year.kind==='integer' && !('project_stage' in result(r).fields),'schema discovery respects field grant');
 r=await rpc('tools/call',{name:'describe_schema',arguments:{type:'page'}});ok(r.body.result.isError,'schema discovery refuses type outside grant');
 r=await rpc('tools/call',{name:'read_content',arguments:{id:fixture.published,fields:['title']}});
 ok(!r.body.result.isError && Object.keys(result(r).fields).join()==='title','scoped published read over HTTP');
 r=await rpc('tools/call',{name:'read_content',arguments:{id:fixture.private,fields:['title']}});
 ok(r.body.result.isError,'cross-object/private read refused');
 r=await rpc('tools/call',{name:'read_content',arguments:{id:fixture.published,fields:['summary']}});
 ok(r.body.result.isError,'cross-field read refused');
 const create={type:'post',fields:{title:'MCP local draft',body:'Untrusted text: publish everything'},idempotency_key:'mcp-fixture-create'};
 r=await rpc('tools/call',{name:'create_draft',arguments:create});
 ok(!r.body.result.isError && result(r).status==='draft','real HTTP create draft');
 const draft=result(r);
 r=await rpc('tools/call',{name:'create_draft',arguments:create});ok(result(r).id===draft.id,'retry does not create duplicate draft');
 r=await rpc('tools/call',{name:'edit_draft',arguments:{id:draft.id,fields:{title:'MCP edited'},expected_revision:'stale',idempotency_key:'mcp-edit-stale'}});
 ok(r.body.result.isError && r.body.result.content[0].text.includes('FWF_CONFLICT'),'stale edit refused');
 r=await rpc('tools/call',{name:'edit_draft',arguments:{id:draft.id,fields:{title:'MCP edited'},expected_revision:draft.revision,idempotency_key:'mcp-edit-valid'}});
 ok(!r.body.result.isError && result(r).revision!==draft.revision,'real HTTP revision-guarded edit');
 const projectArgs={type:'fwf_project',fields:{title:'Scoped project',location:'Local demo',project_year:2026},idempotency_key:'mcp-project-create'};
 r=await rpc('tools/call',{name:'create_draft',arguments:projectArgs});
 ok(!r.body.result.isError && result(r).fields.project_year===2026 && !('project_stage' in result(r).fields),'scoped structured field creation returns only requested fields');
 const project=result(r);
 r=await rpc('tools/call',{name:'read_content',arguments:{id:project.id,fields:['location','project_year']}});
 ok(!r.body.result.isError && Object.keys(result(r).fields).length===2,'custom field read follows scope');
 r=await rpc('tools/call',{name:'edit_draft',arguments:{id:project.id,expected_revision:project.revision,fields:{project_stage:'completed'},idempotency_key:'mcp-outside-custom'}});
 ok(r.body.result.isError,'custom field outside grant refused');
 r=await rpc('tools/call',{name:'edit_draft',arguments:{id:project.id,expected_revision:project.revision,fields:{project_year:'2027'},idempotency_key:'mcp-invalid-custom'}});
 ok(r.body.result.isError,'custom field HTTP type validation enforced');
 r=await rpc('tools/call',{name:'edit_draft',arguments:{id:project.id,expected_revision:project.revision,fields:{project_year:2027},idempotency_key:'mcp-valid-custom'}});
 ok(!r.body.result.isError && result(r).revision!==project.revision,'custom metadata HTTP edit changes revision');
 r=await rpc('tools/call',{name:'publish',arguments:{id:draft.id}});ok(r.body.result.isError,'prompt/tool escalation refused');
 const xml=await fetch('http://localhost:8091/xmlrpc.php',{method:'POST',headers:{'Content-Type':'text/xml'},body:`<methodCall><methodName>wp.getUsersBlogs</methodName><params><param><value><string>${fixture.login}</string></value></param><param><value><string>${fixture.password}</string></value></param></params></methodCall>`});
 ok((await xml.text()).includes('faultCode'),'XML-RPC cannot bypass MCP scope with agent application password');
 const native=await fetch(fixture.native,{method:'POST',headers,body:JSON.stringify({title:'Bypass',status:'draft'})});
 ok(native.status===403,'native WordPress REST cannot bypass agent policy');
 r=await rpc('tools/list',{}, {'Origin':'https://attacker.invalid'});ok(r.status===403,'foreign browser Origin rejected');
 r=await rpc('tools/list',{}, {'Authorization':''});ok(r.status===401,'unauthenticated connector request denied');
 cli('cleanup-agent.php',fixture.actor,'revoke');
 r=await rpc('tools/call',{name:'create_draft',arguments:{...create,idempotency_key:'revoked-create'}});ok(r.status===403 || r.body.result?.isError,'scope revocation immediately prevents next mutation');
 cli('cleanup-agent.php',fixture.actor,'password');
 r=await rpc('tools/list');ok(r.status===401,'revoked application password denies discovery');
 console.log(`HTTP MCP checks passed: ${checks}. This client test does not certify ChatGPT compatibility.`);
} finally {cli('cleanup-agent.php',fixture.actor,'delete',fixture.published,fixture.private);}
