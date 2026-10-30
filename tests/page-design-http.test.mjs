import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const base='http://localhost:8091';
function cli(file,...args){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file',`/fwf-tests/${file}`,...args.map(String)],{encoding:'utf8'});if(r.status!==0)throw new Error('Fixture failed (credential output suppressed)');return r.stdout.trim();}
const users=JSON.parse(cli('create-admin.php'));let fixture;let count=0;
function ok(value,label){assert(value,label);console.log('PASS:',label);count++;}
const cookies=new Map();
async function request(path,body){const r=await fetch(path.startsWith('http')?path:base+path,{redirect:'manual',method:body?'POST':'GET',headers:{Connection:'close',Cookie:[...cookies].map(([k,v])=>`${k}=${v}`).join('; '),...(body?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:body?new URLSearchParams(body):undefined});for(const h of r.headers.getSetCookie()){const s=h.split(';')[0],i=s.indexOf('=');cookies.set(s.slice(0,i),s.slice(i+1));}return {status:r.status,body:await r.text()};}
function form(html){const section=html.match(new RegExp(`<section class="card">(?:(?!<section class="card">)[\\s\\S])*?name="page_id" value="${fixture.id}"[\\s\\S]*?</section>`))?.[0];assert(section,'fixture row found');const values={};for(const match of section.matchAll(/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)){values[match[1]]=match[2];}return {...values,template:fixture.file,confirm:'yes'};}
try{
 fixture=JSON.parse(cli('page-design-http-fixture.php','create'));
 await request('/wp-login.php');const login=await request('/wp-login.php',{log:users.administrator.login,pwd:users.administrator.password,testcookie:'1','wp-submit':'Log In'});ok(login.status===302,'HTTP admin login');
 let screen=await request('/wp-admin/admin.php?page=falcon-wf-pages');let values=form(screen.body);
 let r=await request('/wp-admin/admin-post.php',{...values,_wpnonce:'invalid'});ok(r.status===403,'invalid nonce denied without assignment');
 r=await request('/wp-admin/admin-post.php',values);ok(r.status===302,'HTTP Apply redirects');
 const state=JSON.parse(cli('page-design-http-fixture.php','read',fixture.id));ok(state.template===fixture.file && state.body==='Preserved HTTP body' && state.status==='publish' && state.url===fixture.url,'HTTP assignment preserves page content status URL');
 const front=await request(fixture.url);ok(front.status===200 && front.body.includes('FWF_HTTP_DESIGN_RENDER'),'actual frontend renders assigned named template');
 await request('/wp-admin/admin-post.php',{...values,template:'default'});ok(JSON.parse(cli('page-design-http-fixture.php','read',fixture.id)).template===fixture.file,'stale HTTP Apply cannot overwrite assignment');
 screen=await request('/wp-admin/admin.php?page=falcon-wf-pages');values=form(screen.body);
 cli('page-design-http-fixture.php','native',fixture.id);
 await request('/wp-admin/admin-post.php',values);ok(JSON.parse(cli('page-design-http-fixture.php','read',fixture.id)).template==='default','native editor change invalidates old dashboard form');
 screen=await request('/wp-admin/admin.php?page=falcon-wf-pages');const values2=form(screen.body);const targetSection=screen.body.slice(screen.body.indexOf(`name="page_id" value="${fixture.id}"`));ok(targetSection.includes('value="default" selected'),'dashboard reflects native editor default');
 await request('/wp-admin/admin-post.php',{...values2,template:'../../foreign.php'});ok(JSON.parse(cli('page-design-http-fixture.php','read',fixture.id)).template==='default','foreign template HTTP request refused');
 console.log(`Page design HTTP: ${count} checks passed.`);
} finally {
 if(fixture)cli('page-design-http-fixture.php','cleanup',fixture.id);
 cli('cleanup-admin.php',users.administrator.id,users.editor.id);
}
