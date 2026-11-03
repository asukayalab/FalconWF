import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const compose=['compose','--env-file','local/.env','-f','local/compose.yml'];
const owner=randomUUID(),name='fwf-release-http-'+owner,base='http://localhost:8092';
function docker(args){const r=spawnSync('docker',[...compose,...args],{encoding:'utf8'});if(r.status!==0)throw Error('Release HTTP Docker operation failed (credential output suppressed)');return r.stdout.trim();}
const cli=(file,...args)=>docker(['run','--rm','cli','wp','eval-file','/fwf-tests/'+file,...args]);
let fixtures,started=false,captured=false,count=0;
const ok=(condition,label)=>{assert(condition,label);count++;console.log('PASS:',label);};
function session(){const cookies=new Map();return async(url,params={})=>{const r=await fetch(base+url,{...params,redirect:'manual',headers:{...params.headers,Connection:'close',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});for(const h of r.headers.getSetCookie()){const p=h.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text()};};}
async function login(actor){const request=session();await request('/wp-login.php');assert.equal((await request('/wp-login.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({log:actor.login,pwd:actor.password,testcookie:'1','wp-submit':'Log In',redirect_to:base+'/wp-admin/'})})).status,302);return request;}
try{
 cli('release-http-state.php','capture',owner);captured=true;fixtures=JSON.parse(cli('create-admin.php'));
 const config=JSON.parse(docker(['config','--format','json']));const extra=config.services.cli.environment.WORDPRESS_CONFIG_EXTRA.replace("define('WP_ENVIRONMENT_TYPE', 'local');","define('WP_ENVIRONMENT_TYPE', 'production');")+"\ndefine('DISABLE_WP_CRON', true);";
 assert(extra.includes("define('WP_ENVIRONMENT_TYPE', 'production');"));
 docker(['run','--rm','-d','--name',name,'-p','127.0.0.1:8092:8092','-e','WORDPRESS_CONFIG_EXTRA='+extra,'cli','php','-S','0.0.0.0:8092','-t','/var/www/html']);started=true;
 for(let i=0;i<20;i++){try{await fetch(base+'/wp-login.php',{headers:{Connection:'close'}});break;}catch(e){if(i===19)throw e;await new Promise(resolve=>setTimeout(resolve,200));}}
 const admin=await login(fixtures.administrator),editor=await login(fixtures.editor);
 const screen=async()=> (await admin('/wp-admin/admin.php?page=falcon-wf-connections')).body;
 let html=await screen();ok((html.match(/Terbaru, termasuk uji coba<\/option>/g)||[]).length===2 && (html.match(/Tag release tertentu<\/option>/g)||[]).length===2,'production screen offers all three choices for core and project');
 ok(html.includes('release-choice.js') && html.includes('data-fwf-release-tag'),'production screen loads dynamic tag control');
 const nonce=op=>html.match(new RegExp('name="operation" value="'+op+'"[\\s\\S]*?name="_wpnonce" value="([^"]+)"'))?.[1];
 const submit=(request,op,values)=>request('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:op,screen:'connections',_wpnonce:nonce(op),...values})});
 const project={project_repo:'https://github.com/asukayalab/falcon-wf-project-trial',project_id:'',theme_id:'',project_channel:'testing'};
 ok((await submit(admin,'save_project',{...project,_wpnonce:'invalid'})).status===403,'project connection still requires nonce');
 ok((await submit(editor,'save_project',project)).status===403,'editor cannot save project connection');
 ok((await submit(admin,'save_project',project)).status===302,'production accepts prerelease-only repository through real GitHub discovery');html=await screen();
 ok(html.includes('value="falcon-distribution-trial"') && /name="project_channel"[\s\S]*?<option value="testing"\s+selected=/.test(html) && !html.includes('notice-error'),'actual manifest identity and all-release choice persisted in production');
 ok((await submit(admin,'save_repo',{repo:'asukayalab/FalconWF',update_channel:'testing'})).status===302,'production saves core latest-including-prerelease choice');html=await screen();
 ok(/name="update_channel"[\s\S]*?<option value="testing"\s+selected=/.test(html),'core latest-including-prerelease selection remains saved');
 await submit(admin,'save_project',{...project,project_channel:'manual',project_tag:'v0.1.0-alpha.1'});html=await screen();
 ok(/name="project_channel"[\s\S]*?<option value="manual"\s+selected=/.test(html) && html.includes('value="v0.1.0-alpha.1" required'),'production saves a specific prerelease tag and requires the tag input');
 await submit(admin,'save_project',{...project,project_channel:'manual',project_tag:''});html=await screen();
 ok(html.includes('v0.1.0-alpha.1') && html.includes('Isi tag rilis manual'),'empty manual tag rejected while preserving existing pin');
 console.log(`Release choice HTTP checks passed: ${count}. Production-configured local server, real public GitHub, no install/theme switch.`);
}finally{
 if(started){const r=spawnSync('docker',['stop',name],{encoding:'utf8'});assert.equal(r.status,0,'Owned production-configured test server cleanup failed');}
 if(captured)cli('release-http-state.php','cleanup',owner);
 if(fixtures)cli('cleanup-admin.php',String(fixtures.administrator.id),String(fixtures.editor.id));
}
