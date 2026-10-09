import {spawnSync,spawn} from 'node:child_process';
import {createHash,randomBytes} from 'node:crypto';
import assert from 'node:assert/strict';
const http=(url,params={})=>fetch(url,{...params,headers:{...params.headers,Connection:'close'}});
const compose=['compose','--env-file','local/.env','-f','local/compose.yml'];
function cli(file,...args){const r=spawnSync('docker',[...compose,'run','--rm','cli','wp','eval-file',`/fwf-tests/${file}`,...args.map(String)],{encoding:'utf8'});if(r.status!==0){throw Error('OAuth fixture failed (credential output suppressed).');}return r.stdout.trim();}
const fixture=JSON.parse(cli('create-agent.php'));let configured=false;let checks=0;let webFixtures;
const ok=(value,label)=>{assert(value,label);checks++;console.log('PASS:',label);};
const hash=value=>createHash('sha256').update(value).digest('base64url');
const verifier=randomBytes(48).toString('base64url');
let meta;
function parameters(extra={}){return {client_id:meta.client,redirect_uri:meta.redirect,resource:meta.resource,response_type:'code',code_challenge:hash(verifier),code_challenge_method:'S256',state:'fixture_state',scope:'falcon:content',...extra};}
function code(extra={}){return JSON.parse(cli('oauth-fixture.php','code',fixture.actor,JSON.stringify(parameters(extra))));}
async function post(url,p,extra={}){const r=await http(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded',...extra},body:new URLSearchParams(p)});const text=await r.text();return {status:r.status,body:text?JSON.parse(text):null,headers:r.headers};}
function exchange(c,extra={}){return post(meta.token,{grant_type:'authorization_code',client_id:meta.client,redirect_uri:meta.redirect,resource:meta.resource,code:c,code_verifier:verifier,...extra});}
async function rpc(token,method='tools/list',params={}){const r=await http(fixture.endpoint,{method:'POST',headers:{'Content-Type':'application/json','Authorization':`Bearer ${token}`},body:JSON.stringify({jsonrpc:'2.0',id:checks+1,method,params})});return {status:r.status,body:await r.json()};}
try{
 meta=JSON.parse(cli('oauth-fixture.php','setup',fixture.actor));configured=true;
 let response=await http(meta.metadata);let data=await response.json();ok(response.status===200 && data.resource===fixture.endpoint && data.authorization_servers[0]===meta.issuer,'protected resource binds actual MCP URL');
 response=await http(new URL('/.well-known/oauth-authorization-server'+new URL(meta.issuer).pathname,meta.issuer));data=await response.json();ok(response.status===200 && data.issuer===meta.issuer,'standard authorization-server discovery path');
 response=await http(meta.server);data=await response.json();ok(data.issuer===meta.issuer && data.code_challenge_methods_supported.join()==='S256' && data.token_endpoint_auth_methods_supported.join()==='none','OAuth metadata public client/PKCE only');
 response=await http(fixture.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});ok(response.status===401 && response.headers.get('www-authenticate')?.includes(meta.metadata),'unauthenticated MCP advertises resource discovery');
 for(const [name,extra] of [['callback',{redirect_uri:'https://attacker.invalid/callback'}],['resource',{resource:'https://attacker.invalid/mcp'}],['PKCE method',{code_challenge_method:'plain'}],['scope',{scope:'publish'}],['client',{client_id:'unknown'}]]){ok(!!code(extra).error,`${name} rejected before consent/code`);}
 let c=code().code;let r=await exchange(c,{code_verifier:'x'.repeat(64)});ok(r.status===400,'wrong verifier refused');
 r=await exchange(c,{redirect_uri:'https://attacker.invalid/callback'});ok(r.status===400,'exchange redirect mismatch refused');
 r=await exchange(c,{resource:'https://attacker.invalid/mcp'});ok(r.status===400,'exchange resource mismatch refused');
 r=await exchange(c,{client_id:'unknown'});ok(r.status===400,'exchange client mismatch refused');
 const raced=await Promise.all([exchange(c),exchange(c)]);ok(raced.filter(result=>result.status===200).length===1,'concurrent code redemption issues only once');
 r=raced.find(result=>result.status===200);ok(r.status===200 && r.body.token_type==='Bearer' && r.body.refresh_token && r.headers.get('cache-control')==='no-store','PKCE exchange issues short-lived access and refresh tokens');let tokens=r.body;
 r=await exchange(c);ok(r.status===400,'authorization code cannot replay');
 r=await rpc(tokens.access_token);ok(r.status===200 && r.body.result.tools.length===4 && r.body.result.tools.every(t=>t.securitySchemes?.[0]?.type==='oauth2') && r.body.result.tools.find(t=>t.name==='read_content').annotations.readOnlyHint,'Bearer uses existing MCP and annotated restricted tools');
 r=await rpc(tokens.access_token,'tools/call',{name:'read_content',arguments:{id:fixture.published,fields:['title']}});ok(!r.body.result.isError,'OAuth scoped read succeeds');
 r=await rpc(tokens.access_token,'tools/call',{name:'read_content',arguments:{id:fixture.private,fields:['title']}});ok(r.body.result.isError,'OAuth cannot read foreign draft');
 r=await rpc(tokens.access_token,'tools/call',{name:'create_draft',arguments:{type:'post',fields:{title:'OAuth local draft'},idempotency_key:'oauth-local-create'}});ok(!r.body.result.isError && JSON.parse(r.body.result.content[0].text).status==='draft','OAuth creates draft through existing engine');const draft=JSON.parse(r.body.result.content[0].text);
 r=await rpc(tokens.access_token,'tools/call',{name:'read_content',arguments:{id:draft.id,fields:['title']}});ok(!r.body.result.isError,'created object expands same grant without invalidating token');
 r=await rpc(tokens.access_token,'tools/call',{name:'edit_draft',arguments:{id:draft.id,fields:{title:'Changed'},expected_revision:'stale',idempotency_key:'oauth-stale-edit'}});ok(r.body.result.isError && r.body.result.content[0].text.includes('FWF_CONFLICT'),'OAuth stale revision rejected');
 r=await rpc(tokens.access_token,'tools/call',{name:'edit_draft',arguments:{id:draft.id,fields:{title:'Changed'},expected_revision:draft.revision,idempotency_key:'oauth-valid-edit'}});ok(!r.body.result.isError,'OAuth revision-guarded draft edit');
 r=await rpc(tokens.access_token,'tools/call',{name:'publish',arguments:{id:draft.id}});ok(r.body.result.isError,'OAuth cannot publish');
 response=await http(fixture.native,{method:'POST',headers:{Authorization:`Bearer ${tokens.access_token}`,'Content-Type':'application/json'},body:'{"title":"Bypass","status":"draft"}'});ok(response.status===403,'Bearer cannot bypass native REST');
 const stored=cli('oauth-fixture.php','stored',fixture.actor);ok(!stored.includes(tokens.access_token) && !stored.includes(tokens.refresh_token) && !stored.includes(c) && JSON.parse(stored).every(row=>['off','no','auto-off'].includes(row.autoload)),'DB stores only hashed credentials in non-autoload options');
 const held=spawn('docker',[...compose,'run','--rm','cli','wp','eval-file','/fwf-tests/oauth-fixture.php','hold-family',String(fixture.actor),tokens.access_token],{stdio:['ignore','pipe','pipe']});
 const finished=new Promise((resolve,reject)=>{held.on('error',reject);held.on('exit',status=>status===0?resolve():reject(Error('Owned lock fixture failed')));});
 await new Promise((resolve,reject)=>{let text='';held.stdout.on('data',chunk=>{text+=chunk;if(text.includes('FAMILY_LOCK_READY'))resolve();});held.on('error',reject);held.on('exit',()=>{if(!text.includes('FAMILY_LOCK_READY'))reject(Error('Family lock not reached'));});});
 try{
  r=await post(meta.token,{grant_type:'refresh_token',client_id:meta.client,resource:meta.resource,refresh_token:tokens.refresh_token});ok(r.status===400 && r.body.error==='temporarily_unavailable','refresh respects active family lease');
  r=await post(meta.revoke,{client_id:meta.client,token:tokens.access_token});ok(r.status===503,'revocation cannot race active refresh family');
 }finally{await finished;}
 ok((await rpc(tokens.access_token)).status===200,'blocked operations preserve access after lease release');
 ok(JSON.parse(cli('oauth-fixture.php','grant-write-failure',fixture.actor)).error==='FWF_STORAGE','grant persistence failure reported');
 ok((await rpc(tokens.access_token)).status===200,'failed grant write preserves approved scope');
 ok(JSON.parse(cli('oauth-fixture.php','client-write-failure',fixture.actor)).error==='server_error','client persistence failure reported');
 r=await post(meta.token,{grant_type:'refresh_token',client_id:meta.client,resource:meta.resource,refresh_token:tokens.refresh_token});ok(r.status===200,'refresh rotates tokens');const rotated=r.body;
 ok((await rpc(tokens.access_token)).status===401,'refresh invalidates old access token');
 ok((await post(meta.token,{grant_type:'refresh_token',client_id:meta.client,resource:meta.resource,refresh_token:tokens.refresh_token})).status===400,'refresh cannot replay');
 tokens=rotated;ok((await rpc(tokens.access_token)).status===200,'rotated access works');
 const orphan=await exchange(code().code);cli('oauth-fixture.php','drop-refresh',fixture.actor,orphan.body.access_token);ok((await rpc(orphan.body.access_token)).status===401,'consumed refresh invalidates access even if access row remains');
 await post(meta.revoke,{client_id:'unknown',token:tokens.access_token});ok((await rpc(tokens.access_token)).status===200,'different client cannot revoke owned token');
 await post(meta.revoke,{client_id:meta.client,token:tokens.refresh_token});ok((await rpc(tokens.access_token)).status===401,'refresh revocation also invalidates paired access');
 c=code().code;cli('oauth-fixture.php','expire-code',fixture.actor,c);ok((await exchange(c)).status===400,'expired authorization code refused');
 tokens=(await exchange(code().code)).body;cli('oauth-fixture.php','expire-access',fixture.actor,tokens.access_token);ok((await rpc(tokens.access_token)).status===401,'expired access token refused');
 tokens=(await exchange(code().code)).body;cli('oauth-fixture.php','rotate-grant',fixture.actor);ok((await rpc(tokens.access_token)).status===401,'scope re-save invalidates older token');
 tokens=(await exchange(code().code)).body;cli('oauth-fixture.php','demote-owner',fixture.actor);ok((await rpc(tokens.access_token)).status===401,'revoked client owner capability invalidates access');cli('oauth-fixture.php','restore-owner',fixture.actor);
 // Real cookie/nonce consent and client management; redirects are inspected without visiting ChatGPT.
 webFixtures=JSON.parse(cli('create-admin.php'));
 function session(){const cookies=new Map();return async (url,params={})=>{const response=await http(url,{...params,redirect:'manual',headers:{...params.headers,Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; ')}});for(const h of response.headers.getSetCookie()){const part=h.split(';')[0];const i=part.indexOf('=');cookies.set(part.slice(0,i),part.slice(i+1));}return {status:response.status,location:response.headers.get('location'),body:await response.text()};};}
 async function login(actor){const sessionFetch=session();await sessionFetch('http://localhost:8091/wp-login.php');const response=await sessionFetch('http://localhost:8091/wp-login.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({log:actor.login,pwd:actor.password,'wp-submit':'Log In',testcookie:'1'})});ok(response.status===302,'disposable cookie login');return sessionFetch;}
 const admin=await login(webFixtures.administrator),editor=await login(webFixtures.editor);
 const consentURL=meta.authorize+'&'+new URLSearchParams(parameters());
 let screen=await admin(consentURL);ok(screen.status===200 && screen.body.includes('Setujui koneksi dengan scope ini') && screen.body.includes('create_draft') && screen.body.includes(`value="${fixture.actor}"`),'consent shows concrete actor/actions/fields');
 const nonce=screen.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
 const consent=p=>({method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_oauth_authorize',...parameters(),actor:fixture.actor,decision:'approve',_wpnonce:nonce,...p})});
 ok((await admin(meta.authorize,consent({_wpnonce:'invalid'}))).status===403,'consent requires nonce');
 ok((await admin(meta.authorize,consent({state:'tampered'}))).status===403,'consent nonce binds exact request');
 ok((await editor(consentURL)).status===403,'editor cannot approve OAuth');
 let approved=await admin(meta.authorize,consent({decision:'deny'}));ok(approved.status===303 && new URL(approved.location).searchParams.get('error')==='access_denied','human denial returns bound error');
 approved=await admin(meta.authorize,consent({}));const callback=new URL(approved.location);ok(approved.status===303 && callback.origin==='https://chatgpt.com' && callback.searchParams.get('state')==='fixture_state' && callback.searchParams.get('iss')===meta.issuer,'explicit human consent returns code only to registered callback');
 ok((await exchange(callback.searchParams.get('code'))).status===200,'cookie consent code exchanges with PKCE');
 screen=await admin('http://localhost:8091/wp-admin/admin.php?page=falcon-wf-ai');const addNonce=screen.body.match(/name="operation" value="add_oauth_client"[\s\S]*?name="_wpnonce" value="([^"]+)"/)?.[1];
 const registration=p=>({method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:'add_oauth_client',screen:'ai',client_name:'HTTP fixture',redirect_uri:meta.redirect,_wpnonce:addNonce,...p})});
 ok((await admin('http://localhost:8091/wp-admin/admin-post.php',registration({_wpnonce:'invalid'}))).status===403,'client registration requires nonce');
 ok((await editor('http://localhost:8091/wp-admin/admin-post.php',registration({}))).status===403,'editor cannot register client');
 ok((await admin('http://localhost:8091/wp-admin/admin-post.php',registration({}))).status===302,'human client registration action completes');
 screen=await admin('http://localhost:8091/wp-admin/admin.php?page=falcon-wf-ai');ok(screen.body.includes('<h3>HTTP fixture</h3>') && screen.body.includes('Client ID:'),'registered public client visible for setup');
 tokens=(await exchange(code().code)).body;cli('oauth-fixture.php','password-revoke',fixture.actor);ok((await rpc(tokens.access_token)).status===401,'application password removal invalidates OAuth');cli('oauth-fixture.php','password-renew',fixture.actor);
 tokens=(await exchange(code().code)).body;cli('oauth-fixture.php','remove-client',fixture.actor);ok((await rpc(tokens.access_token)).status===401,'removed client invalidates access');
 ok((await rpc('malformed')).status===401,'malformed Bearer refused');
 console.log(`OAuth HTTP checks passed: ${checks}. Local client only, not ChatGPT acceptance.`);
}finally{
 if(configured){cli('oauth-fixture.php','cleanup',fixture.actor,webFixtures?.administrator.id??0);}
 if(webFixtures){cli('cleanup-admin.php',webFixtures.administrator.id,webFixtures.editor.id);}
 cli('cleanup-agent.php',fixture.actor,'delete',fixture.published,fixture.private);
}
