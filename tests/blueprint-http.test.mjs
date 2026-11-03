import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const compose=['compose','--env-file','local/.env','-f','local/compose.yml'],owner=randomUUID(),name='fwf-bp-http-'+owner,base='http://localhost:8093';
function docker(args){const r=spawnSync('docker',[...compose,...args],{encoding:'utf8'});if(r.status!==0)throw Error('Blueprint fixture failed (credentials suppressed)');return r.stdout.trim();}
const cli=(file,...args)=>docker(['run','--rm','cli','wp','eval-file','/fwf-tests/'+file,...args]);
let started=false,captured=false,users,fixture,count=0;function ok(v,label){assert(v,label);count++;console.log('PASS:',label);}
function session(){const cookies=new Map();return async(path,values)=>{const r=await fetch(base+path,{redirect:'manual',method:values?'POST':'GET',headers:{Connection:'close',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; '),...(values?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:values?new URLSearchParams(values):undefined});for(const h of r.headers.getSetCookie()){const p=h.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text()};};}
async function login(user){const r=session();await r('/wp-login.php');assert.equal((await r('/wp-login.php',{log:user.login,pwd:user.password,testcookie:'1','wp-submit':'Log In'})).status,302);return r;}
const nonce=(html,op)=>html.match(new RegExp(`name="operation" value="${op}"[\\s\\S]*?name="_wpnonce" value="([^"]+)"`))?.[1];
try{
 fixture=JSON.parse(cli('blueprint-http.php','capture',owner));captured=true;users=JSON.parse(cli('create-admin.php'));
 const config=JSON.parse(docker(['config','--format','json']));const extra=config.services.cli.environment.WORDPRESS_CONFIG_EXTRA+"\ndefine('WPMU_PLUGIN_DIR', '/fwf-tests/blueprint-mu');";
 docker(['run','--rm','-d','--name',name,'-p','127.0.0.1:8093:8093','-e','WORDPRESS_CONFIG_EXTRA='+extra,'-e','FWF_BLUEPRINT_HTTP_OWNER='+owner,'cli','php','-S','0.0.0.0:8093','-t','/var/www/html']);started=true;
 for(let i=0;i<20;i++){try{await fetch(base+'/wp-login.php',{headers:{Connection:'close'}});break;}catch(e){if(i===19)throw e;await new Promise(r=>setTimeout(r,200));}}
 const admin=await login(users.administrator),editor=await login(users.editor);let html=(await admin('/wp-admin/admin.php?page=falcon-wf-pages')).body;
 ok(html.includes('Setup website dari paket') && html.includes('HTTP fixture'),'actual blueprint setup UI renders from installed theme context');
 const revision=html.match(/name="operation" value="review_blueprint"[\s\S]*?name="revision" value="([^"]+)"/)?.[1];assert(revision);
 const values={action:'fwf_action',operation:'review_blueprint',screen:'pages',revision,'targets[about]':'new','targets[contact]':String(fixture.existing),'modes[about]':'new','modes[contact]':'keep','definitions[groups/bp_http_about]':'new',_wpnonce:nonce(html,'review_blueprint')};
 ok((await admin('/wp-admin/admin-post.php',{...values,_wpnonce:'invalid'})).status===403,'blueprint review requires nonce');
 ok((await editor('/wp-admin/admin-post.php',values)).status===403,'editor cannot review system structure');
 ok((await admin('/wp-admin/admin-post.php',values)).status===302,'human review request succeeds');html=(await admin('/wp-admin/admin.php?page=falcon-wf-pages')).body;
 ok(html.includes('Review penerapan') && !JSON.parse(cli('blueprint-http.php','read',owner)).state.roles.about,'HTTP review writes no Page');
 const token=html.match(/name="review_token" value="([^"]+)"/)?.[1];const apply={action:'fwf_action',operation:'apply_blueprint',screen:'pages',review_token:token,_wpnonce:nonce(html,'apply_blueprint'),confirm:'yes'};assert(token);
 ok((await admin('/wp-admin/admin-post.php',{...apply,_wpnonce:'invalid'})).status===403,'blueprint Apply requires its nonce');
 ok((await editor('/wp-admin/admin-post.php',apply)).status===403,'editor cannot apply blueprint');
 ok((await admin('/wp-admin/admin-post.php',apply)).status===302,'HTTP confirmed Apply completes');
 const state=JSON.parse(cli('blueprint-http.php','read',owner));ok(state.about_status==='draft' && state.state.status==='ready' && state.existing_body==='KEEP HTTP CONTENT','HTTP setup creates draft and preserves existing content');
 const edit=(await admin('/wp-admin/post.php?post='+state.state.roles.about+'&action=edit')).body;ok(edit.includes('name="fields[bp_http_hero]"'),'scoped custom field appears in actual native Page editor');
 const hidden=name=>edit.match(new RegExp(`name="${name}" value="([^"]+)"`))?.[1];
 const native={action:'editpost',post_ID:String(state.state.roles.about),post_type:'page',post_title:'HTTP About',post_status:'draft',_wpnonce:hidden('_wpnonce'),fwf_fields_nonce:hidden('fwf_fields_nonce'),fwf_meta_revision:hidden('fwf_meta_revision'),fwf_content_revision:hidden('fwf_content_revision'),'fields[bp_http_hero]':'Human HTTP hero'};
 ok((await admin('/wp-admin/post.php',native)).status===302 && JSON.parse(cli('blueprint-http.php','read',owner)).hero==='Human HTTP hero','native Page editor saves scoped custom field');
 ok((await admin('/wp-admin/post.php',{...native,'fields[bp_http_hero]':'Stale overwrite'})).status===409,'stale scoped editor form cannot overwrite newer value');
 const unrelated=(await admin('/wp-admin/post.php?post='+fixture.existing+'&action=edit')).body;ok(!unrelated.includes('name="fields[bp_http_hero]"'),'About field absent from unrelated Contact editor');
 await admin('/wp-admin/admin-post.php',apply);const after=JSON.parse(cli('blueprint-http.php','read',owner));ok(after.state.roles.about===state.state.roles.about,'consumed review cannot create duplicate draft');
 console.log(`Blueprint HTTP checks passed: ${count}. Request-local theme context; persisted active theme preserved.`);
}finally{
 try{if(started){const stopped=spawnSync('docker',['stop',name],{encoding:'utf8'});assert.equal(stopped.status,0,'owned HTTP server stop');}}finally{try{if(captured)cli('blueprint-http.php','cleanup',owner);}finally{if(users)cli('cleanup-admin.php',...Object.values(users).map(x=>String(x.id)));}}
}
