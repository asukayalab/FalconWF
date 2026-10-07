import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const base='http://localhost:8091';
function cli(file,...args){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file',`/fwf-tests/${file}`,...args.map(String)],{encoding:'utf8'});if(r.status!==0)throw new Error('Fixture operation failed (credential output suppressed)');return r.stdout.trim();}
const fixtures=JSON.parse(cli('create-admin.php'));
const content=JSON.parse(cli('content-fixture.php','create'));
cli('builder-fixture.php','snapshot');
function session(){
 const cookies=new Map();
 return async (path,params={})=>{
   const r=await fetch(base+path,{...params,redirect:'manual',headers:{...params.headers,'Cookie':[...cookies].map(([k,v])=>`${k}=${v}`).join('; ')}});
   for(const header of r.headers.getSetCookie()){const part=header.split(';')[0];const index=part.indexOf('=');cookies.set(part.slice(0,index),part.slice(index+1));}
   return {status:r.status,location:r.headers.get('location'),body:await r.text()};
 };
}
let checks=0;const ok=(x,label)=>{assert(x,label);checks++;console.log('PASS:',label);};
async function login(actor){const s=session();await s('/wp-login.php');const r=await s('/wp-login.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({log:actor.login,pwd:actor.password,'wp-submit':'Log In',testcookie:'1',redirect_to:base+'/wp-admin/'})});ok(r.status===302,'local fixture login');return s;}
try{
 const admin=await login(fixtures.administrator);
 const screens=['','-content','-identity','-modules','-connections','-ai','-updates','-maintenance','-audit'];
 for(const screen of screens){const r=await admin('/wp-admin/admin.php?page=falcon-wf'+screen);ok(r.status===200 && r.body.includes('class="wrap fwf"') && !r.body.includes('Fatal error'),'admin screen '+(screen||'overview')+' renders');}
 const identity=await admin('/wp-admin/admin.php?page=falcon-wf-identity');
 const nonce=identity.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];ok(!!nonce,'identity action uses nonce');
 let r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:'save_identity',screen:'identity',name:'Falcon WF Local',contact:'',_wpnonce:nonce})});
 ok(r.status===302 && r.location.includes('falcon-wf-identity'),'authorized identity action redirects to screen');
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:'save_identity',name:'Bad'})});ok(r.status===403,'missing nonce refused');
 const editor=await login(fixtures.editor);
 r=await editor('/wp-admin/admin.php?page=falcon-wf-content');ok(r.status===403,'editor cannot change content definitions');
 r=await editor('/wp-admin/admin.php?page=falcon-wf-ai');ok(r.status===403,'editor cannot open AI policy page directly');
 r=await editor('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_action',operation:'install_theme',_wpnonce:nonce})});ok(r.status===403,'editor direct install action refused');
 r=await admin('/wp-admin/admin.php?page=falcon-wf-ai');ok(!r.body.includes(fixtures.administrator.password),'no fixture credential in HTML');
 const formPath='/wp-admin/post.php?post='+content.id+'&action=edit';
 const form=await editor(formPath);
 ok(form.status===200 && form.body.includes('fwf_fields_nonce') && form.body.includes('name="fields[project_stage]"'),'custom fields render on native content editor');
 const nativeNonce=form.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
 const metaNonce=form.body.match(/name="fwf_fields_nonce" value="([^"]+)"/)?.[1];
 const metaRevision=form.body.match(/name="fwf_meta_revision" value="([^"]+)"/)?.[1];
 const nativeValues={action:'editpost',post_ID:String(content.id),post_type:'fwf_project',post_title:content.fields.title,post_status:'draft',_wpnonce:nativeNonce,fwf_fields_nonce:metaNonce,fwf_meta_revision:metaRevision,'fields[location]':'Native HTTP changed','fields[project_year]':'2026','fields[project_stage]':'development','fields[cover_image]':'0'};
 r=await editor('/wp-admin/post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(nativeValues)});
 ok(r.status===302 && JSON.parse(cli('content-fixture.php','get',content.id)).fields.location==='Native HTTP changed','native editor HTTP save persists custom fields');
 r=await editor('/wp-admin/post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({...nativeValues,'fields[location]':'Stale HTTP attempt'})});
 ok(JSON.parse(cli('content-fixture.php','get',content.id)).fields.location==='Native HTTP changed','stale native HTTP metadata save refused');
 const builder=await admin('/wp-admin/admin.php?page=falcon-wf-content');
 ok(builder.body.includes('Content Builder') && builder.body.includes('Field Groups') && !builder.body.includes('Schema dikelola melalui kode'),'builder replaces fixed directory');
 const create=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=types&new=1');
 ok(create.body.includes('Seperti Pages') && create.body.includes('name="revision"'),'new type offers model and conflict revision');
 r=await editor('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'types',key:'fwf_unauthorized'})});ok(r.status===403,'editor definition mutation denied');
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'types',key:'fwf_bad_nonce'})});ok(r.status===403,'builder missing nonce denied');
 const builderNonce=create.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
 const builderRevision=create.body.match(/name="revision" value="([^"]+)"/)?.[1];
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'types',key:'fwf_http_type',label:'HTTP Type Fixture',slug:'http-type-fixture',model:'page',active:'1',revision:builderRevision,_wpnonce:builderNonce})});
 ok(r.status===302,'administrator creates type through builder HTTP boundary');
 const typeList=await admin('/wp-admin/admin.php?page=falcon-wf-content');ok(typeList.body.includes('HTTP Type Fixture') && typeList.body.includes('fwf_http_type'),'created definition and native menu appear');
 const newGroup=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=groups&new=1');
 const groupNonce=newGroup.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];const groupRevision=newGroup.body.match(/name="revision" value="([^"]+)"/)?.[1];
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'groups',key:'http_fields',label:'HTTP Fields Fixture',active:'1',revision:groupRevision,_wpnonce:groupNonce,'types[0]':'post','types[1]':'page','types[2]':'fwf_http_type','fields[0][key]':'http_extra_note','fields[0][label]':'HTTP Extra Note','fields[0][kind]':'text','fields[0][default]':''})});
 ok(r.status===302,'administrator creates field group through HTTP boundary');
 const groupList=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=groups');ok(groupList.body.includes('HTTP Fields Fixture'),'created field group persists');
 const fieldForm=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=groups&key=project_details');
 ok(fieldForm.body.includes('Field Type') && fieldForm.body.includes('data-add-field') && fieldForm.body.includes('project_year'),'ACF style field rows render');
 const navigation=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=navigation');
 ok(navigation.body.includes('fwf-menu-row') && navigation.body.includes('Reset urutan bawaan'),'navigation reorder and reset render');
 const listing=await admin('/wp-admin/admin.php?page=falcon-wf-content&tab=listing');
 const termNonce=listing.body.match(/data-nonce="([^"]+)"/)?.[1];
 const requestTerms=(client,values)=>client('/wp-admin/admin-ajax.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_listing_terms',type:'post',taxonomy:'category',nonce:termNonce,...values})});
 let termResponse=await requestTerms(admin,{});ok(termResponse.status===200 && Array.isArray(JSON.parse(termResponse.body).data.terms),'listing loads registered taxonomy terms');
 termResponse=await requestTerms(admin,{nonce:'invalid'});ok(termResponse.status===403,'listing terms require valid nonce');
 termResponse=await requestTerms(editor,{});ok(termResponse.status===403,'editor cannot access builder terms endpoint');
 termResponse=await requestTerms(admin,{type:'page'});ok(termResponse.status===400,'listing rejects taxonomy unrelated to type');
 const navNonce=navigation.body.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'navigation',_wpnonce:navNonce,'order[0]':'edit.php?post_type=fwf_project','order[1]':'edit.php?post_type=page'})});
 const reordered=await admin('/wp-admin/admin.php?page=falcon-wf-content');ok(reordered.body.indexOf('id="menu-posts-fwf_project"')<reordered.body.indexOf('id="menu-pages"'),'saved navigation order applied to native menu');
 r=await admin('/wp-admin/admin-post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'fwf_builder',section:'navigation',_wpnonce:navNonce,reset:'1'})});
 const resetMenu=await admin('/wp-admin/admin.php?page=falcon-wf-content');ok(resetMenu.body.indexOf('id="menu-pages"')<resetMenu.body.indexOf('id="menu-posts-fwf_project"'),'navigation reset restores Project after Pages');
 const overview=await admin('/wp-admin/admin.php?page=falcon-wf');
 ok(overview.body.match(/class="wrap fwf"/g)?.length===1 && overview.body.includes('1 · Periksa dan pasang') && overview.body.includes('2 · Pilih theme aktif') && overview.body.includes('3 · Identitas dan konten'),'setup steps reflect actual theme state');
 console.log(`Admin HTTP checks passed: ${checks}. Visual browser review is separate.`);
}finally{try{cli('builder-fixture.php','restore');cli('content-fixture.php','cleanup',content.id);}finally{cli('cleanup-admin.php',...Object.values(fixtures).map(x=>x.id));}}
