import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {readFileSync, mkdtempSync, cpSync, writeFileSync, rmSync, symlinkSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
const build=()=>{const r=spawnSync(process.execPath,['scripts/build-project.mjs'],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);};
test('client example ZIP is separate, complete and deterministic',()=>{
 build();const spec=JSON.parse(readFileSync('examples/projects/falcon-reference/project.json'));
 const manifest=JSON.parse(readFileSync('dist/projects/project-manifest.json'));assert.equal(manifest.project_id,spec.project_id);assert.equal(manifest.packages[0].version,spec.version);
 const zip=`dist/projects/${manifest.packages[0].artifact}`;const before=readFileSync(zip);
 const checked=spawnSync('python3',['tests/check_project_zip.py'],{encoding:'utf8'});assert.equal(checked.status,0,checked.stderr);
 build();assert.deepEqual(readFileSync(zip),before);console.log(checked.stdout.trim());
});
test('external project uses its own identity/provenance and refuses unsafe paths',()=>{
 const temp=mkdtempSync(path.join(tmpdir(),'fwf-project-'));
 try {
  const source=path.join(temp,'source'),output=path.join(temp,'output');cpSync('examples/projects/falcon-reference',source,{recursive:true});
  const spec=JSON.parse(readFileSync(path.join(source,'project.json')));spec.project_id='external-trial';spec.package.id='external-trial';
  writeFileSync(path.join(source,'project.json'),JSON.stringify(spec));
  const run=(src=source,out=output)=>spawnSync(process.execPath,['scripts/build-project.mjs','--source',src,'--output',out],{encoding:'utf8'});
  let result=run();assert.equal(result.status,0,result.stderr);
  let manifest=JSON.parse(readFileSync(path.join(output,'project-manifest.json')));assert.equal(manifest.project_id,'external-trial');assert.equal(manifest.source_commit,null);assert.equal(manifest.dirty,true);
  const git=argv=>{const r=spawnSync('git',argv,{cwd:source,encoding:'utf8'});assert.equal(r.status,0,r.stderr);return r.stdout.trim();};
  git(['init']);git(['add','.']);git(['-c','user.name=Fixture','-c','user.email=fixture@example.invalid','commit','-m','Fixture']);
  result=run();assert.equal(result.status,0,result.stderr);manifest=JSON.parse(readFileSync(path.join(output,'project-manifest.json')));assert.equal(manifest.source_commit,git(['rev-parse','HEAD']));assert.equal(manifest.dirty,false);
  writeFileSync(path.join(source,'page.php'),readFileSync(path.join(source,'page.php'),'utf8')+'\n');result=run();assert.equal(result.status,0,result.stderr);assert.equal(JSON.parse(readFileSync(path.join(output,'project-manifest.json'))).dirty,true);
  assert.notEqual(run(source,path.join(source,'dist')).status,0);
  assert.notEqual(run(source,'build/projects/external-trial/output').status,0);
  rmSync(path.join(source,'assets'),{recursive:true});symlinkSync(path.resolve('examples/projects/falcon-reference/assets'),path.join(source,'assets'));
  assert.notEqual(run().status,0,'Directory symlink must be rejected');
 } finally {rmSync(temp,{recursive:true,force:true});}
});

test('project contract requires entrypoints and preserves binary assets exactly',()=>{
 const temp=mkdtempSync(path.join(tmpdir(),'fwf-assets-'));
 try{
  const source=path.join(temp,'source'),output=path.join(temp,'output');cpSync('examples/projects/falcon-reference',source,{recursive:true});
  const spec=JSON.parse(readFileSync(path.join(source,'project.json')));spec.project_id='asset-trial';spec.package.id='asset-trial';
  const save=()=>writeFileSync(path.join(source,'project.json'),JSON.stringify(spec));
  const run=()=>spawnSync(process.execPath,['scripts/build-project.mjs','--source',source,'--output',output],{encoding:'utf8'});
  spec.runtime=spec.runtime.filter(x=>x!=='index.php');rmSync(path.join(source,'index.php'));save();let r=run();assert.notEqual(r.status,0);assert.match(r.stderr,/requires index.php/);
  writeFileSync(path.join(source,'index.php'),'<?php // Entry point');spec.runtime.push('index.php');
  const binary=Buffer.from([0,255,128,254,65,0,1,2,3]);
  writeFileSync(path.join(source,'assets/font.woff2'),binary);writeFileSync(path.join(source,'assets/picture.png'),binary);
  writeFileSync(path.join(source,'assets/motion.js'),'console.log("@@PROJECT_VERSION@@");');
  spec.runtime.push('assets/font.woff2','assets/picture.png','assets/motion.js');save();r=run();assert.equal(r.status,0,r.stderr);
  const manifest=JSON.parse(readFileSync(path.join(output,'project-manifest.json')));
  const check=spawnSync('python3',['-c',`from zipfile import ZipFile\nimport sys\nwith ZipFile(sys.argv[1]) as z:\n assert z.read('asset-trial/assets/font.woff2')==bytes([0,255,128,254,65,0,1,2,3])\n assert z.read('asset-trial/assets/picture.png')==bytes([0,255,128,254,65,0,1,2,3])\n assert b'@@PROJECT_' not in z.read('asset-trial/assets/motion.js')\n`,path.join(output,manifest.packages[0].artifact)],{encoding:'utf8'});assert.equal(check.status,0,check.stderr);
  writeFileSync(path.join(source,'assets/font.woff2'),Buffer.alloc(40*1024*1024));r=run();assert.notEqual(r.status,0);assert.match(r.stderr,/expanded ZIP limit/);writeFileSync(path.join(source,'assets/font.woff2'),binary);
  spec.runtime=spec.runtime.filter(x=>x!=='assets/motion.js');save();assert.notEqual(run().status,0,'unmapped JS must fail');
  spec.runtime.push('assets/motion.js','assets/config.env');writeFileSync(path.join(source,'assets/config.env'),'not a runtime asset');save();assert.notEqual(run().status,0,'unsupported extension refused');
  spec.runtime=spec.runtime.filter(x=>x!=='assets/config.env');spec.runtime.push('assets//motion.js');save();assert.notEqual(run().status,0,'canonical alias refused');
  spec.runtime=spec.runtime.filter(x=>x!=='assets//motion.js');rmSync(path.join(source,'assets/font.woff2'));symlinkSync(path.join(source,'assets/picture.png'),path.join(source,'assets/font.woff2'));save();assert.notEqual(run().status,0,'asset symlink refused');
 }finally{rmSync(temp,{recursive:true,force:true});}
});

test('blueprint project is schema validated and bound to its package identity and requirements',()=>{
 const temp=mkdtempSync(path.join(tmpdir(),'fwf-blueprint-'));
 try{
  const source=path.join(temp,'source'),output=path.join(temp,'output');cpSync('examples/projects/falcon-blueprint-reference',source,{recursive:true});
  const run=()=>spawnSync(process.execPath,['scripts/build-project.mjs','--source',source,'--output',output],{encoding:'utf8'});
  let r=run();assert.equal(r.status,0,r.stderr);const manifest=JSON.parse(readFileSync(path.join(output,'project-manifest.json')));assert.equal(manifest.packages[0].blueprint.file,'falcon-blueprint.json');assert.equal(manifest.packages[0].blueprint.project_id,manifest.project_id);
  const docPath=path.join(source,'falcon-blueprint.json'),doc=JSON.parse(readFileSync(docPath));doc.options={untrusted:'not allowed'};writeFileSync(docPath,JSON.stringify(doc));assert.notEqual(run().status,0,'arbitrary settings prohibited by schema');delete doc.options;
  doc.pages.push({...doc.pages[0]});writeFileSync(docPath,JSON.stringify(doc));assert.notEqual(run().status,0,'duplicate roles refused');doc.pages.pop();
  doc.project_id='wrong-project';writeFileSync(docPath,JSON.stringify(doc));assert.notEqual(run().status,0,'blueprint identity bound to source');doc.project_id=manifest.project_id;writeFileSync(docPath,JSON.stringify(doc));
  const group=Object.values(doc.definitions.groups)[0],roles=group.page_roles;delete group.page_roles;writeFileSync(docPath,JSON.stringify(doc));assert.notEqual(run().status,0,'Page group cannot apply globally without roles');group.page_roles=roles;writeFileSync(docPath,JSON.stringify(doc));
  const specPath=path.join(source,'project.json'),spec=JSON.parse(readFileSync(specPath));spec.compatibility['falcon-wf'].min='0.1.0-alpha.21';writeFileSync(specPath,JSON.stringify(spec));assert.notEqual(run().status,0,'old FP minimum cannot claim blueprint support');
 }finally{rmSync(temp,{recursive:true,force:true});}
});
