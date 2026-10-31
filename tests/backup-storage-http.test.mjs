import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const compose=['compose','--env-file','local/.env','-f','local/compose.yml'];
const owner=randomUUID(),name='fwf-storage-http-'+owner,base='http://localhost:8092';
function docker(args){const r=spawnSync('docker',[...compose,...args],{encoding:'utf8'});if(r.status!==0)throw Error('Storage HTTP Docker operation failed (credential output suppressed)');return r.stdout.trim();}
const cli=(file,...args)=>docker(['run','--rm','cli','wp','eval-file','/fwf-tests/'+file,...args]);
let fixtures,path,started=false,checks=0;
const ok=(condition,label)=>{assert(condition,label);checks++;console.log('PASS:',label);};
function session(){const cookies=new Map();return async(url,params={})=>{const r=await fetch(base+url,{...params,redirect:'manual',headers:{...params.headers,Connection:'close',Cookie:[...cookies].map(([k,v])=>k+'='+v).join('; ')}});for(const h of r.headers.getSetCookie()){const p=h.split(';')[0],i=p.indexOf('=');cookies.set(p.slice(0,i),p.slice(i+1));}return {status:r.status,body:await r.text()};};}
async function login(actor){const request=session();await request('/wp-login.php');assert.equal((await request('/wp-login.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({log:actor.login,pwd:actor.password,testcookie:'1','wp-submit':'Log In',redirect_to:base+'/wp-admin/'})})).status,302);return request;}
try{
 path=cli('backup-storage-http.php','capture',owner);fixtures=JSON.parse(cli('create-admin.php'));
 const config=JSON.parse(docker(['config','--format','json']));const original=config.services.cli.environment.WORDPRESS_CONFIG_EXTRA;
 assert(original.includes("define('FWF_BACKUP_DIR', '/fwf-backups');"));
 const extra=original.replace("define('FWF_BACKUP_DIR', '/fwf-backups');",'// No backup constant in HTTP setup fixture.');
 docker(['run','--rm','-d','--name',name,'-p','127.0.0.1:8092:8092','-e','WORDPRESS_CONFIG_EXTRA='+extra,'cli','php','-S','0.0.0.0:8092','-t','/var/www/html']);started=true;
 // Read-only readiness retry; never retry a mutation.
 for(let i=0;i<20;i++){try{await fetch(base+'/wp-login.php',{headers:{Connection:'close'}});break;}catch(e){if(i===19)throw e;await new Promise(resolve=>setTimeout(resolve,200));}}
 const admin=await login(fixtures.administrator),editor=await login(fixtures.editor);
 const screen=()=>admin('/wp-admin/admin.php?page=falcon-wf-backup');let html=(await screen()).body;
 ok(html.includes('Siapkan penyimpanan backup') && html.includes('name="backup_destination"'),'unconfigured HTTP screen offers manual backup without requiring technical setup');
 const nonce=html.match(/name="operation" value="setup_backup_storage"[\s\S]*?name="_wpnonce" value="([^"]+)"/)?.[1];assert(nonce);
 const submit=(request,values)=>request('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:'setup_backup_storage',screen:'backup',_wpnonce:nonce,...values})});
 ok((await submit(admin,{backup_directory:path,_wpnonce:'invalid'})).status===403,'HTTP storage setup requires dedicated nonce');
 ok((await submit(editor,{backup_directory:path})).status===403,'HTTP editor cannot register storage');
 await submit(admin,{backup_directory:'/var/www/html'});html=(await screen()).body;
 ok(html.includes('Siapkan penyimpanan backup') && html.includes('name="backup_destination"'),'HTTP rejects public folder while retaining the manual backup entry');
 ok((await submit(admin,{backup_directory:path})).status===302,'HTTP administrator registers operator-provided private directory');html=(await screen()).body;
 ok(html.includes('<strong>Siap.</strong>') && html.includes('name="components[]"') && html.includes('Simpan jadwal') && html.includes('Simpan retensi') && html.includes('Import dan periksa ZIP'),'successful HTTP setup restores approved forms');
 await submit(admin,{backup_directory:'/var/www/html'});html=(await screen()).body;
 ok(html.includes(path) && html.includes('name="components[]"'),'repeat HTTP setup cannot replace working storage');
 console.log(`Backup storage HTTP checks passed: ${checks}. Temporary local PHP server, real cookie/nonce/admin action.`);
}finally{
 if(started){const stopped=spawnSync('docker',['stop',name],{encoding:'utf8'});assert.equal(stopped.status,0,'Owned HTTP server stop failed');}
 if(path)cli('backup-storage-http.php','cleanup',owner);
 if(fixtures)cli('cleanup-admin.php',String(fixtures.administrator.id),String(fixtures.editor.id));
}
