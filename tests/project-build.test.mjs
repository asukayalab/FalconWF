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
