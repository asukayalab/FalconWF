import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {readFileSync,writeFileSync,cpSync,mkdtempSync,mkdirSync,copyFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {createHash} from 'node:crypto';
const build=()=>{const r=spawnSync(process.execPath,['scripts/build-release.mjs'],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);};
test('clean development ZIP contains only declared runtime, all assets, identical FT bundle',()=>{
  build();
  const r=spawnSync('python3',['tests/check_zip.py'],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);
  console.log(r.stdout.trim());
});
test('repeated build preserves final artifact bytes',()=>{
  const manifest=JSON.parse(readFileSync('dist/release-manifest.json'));
  const before=manifest.packages.map(p=>readFileSync(`dist/${p.artifact}`));
  build();
  manifest.packages.forEach((p,i)=>assert.deepEqual(readFileSync(`dist/${p.artifact}`),before[i]));
});

test('FP and FT package versions remain independent of product release version',()=>{
  const fixture=mkdtempSync(path.join(tmpdir(),'fwf-component-build-'));
  try {
    for(const directory of ['packages','release','scripts'])cpSync(directory,path.join(fixture,directory),{recursive:true});
    mkdirSync(path.join(fixture,'tests'));copyFileSync('tests/check_zip.py',path.join(fixture,'tests/check_zip.py'));
    const components=JSON.parse(readFileSync(path.join(fixture,'release/components.json')));
    components.versions['falcon-theme']='0.1.1-alpha.1';writeFileSync(path.join(fixture,'release/components.json'),JSON.stringify(components));
    const built=spawnSync(process.execPath,['scripts/build-release.mjs'],{cwd:fixture,encoding:'utf8'});assert.equal(built.status,0,built.stderr);
    const checked=spawnSync('python3',['tests/check_zip.py'],{cwd:fixture,encoding:'utf8'});assert.equal(checked.status,0,checked.stderr);
    const manifest=JSON.parse(readFileSync(path.join(fixture,'dist/release-manifest.json')));
    assert.equal(manifest.version,components.version);assert.equal(manifest.packages[0].version,components.versions['falcon-wf']);assert.equal(manifest.packages[1].version,'0.1.1-alpha.1');assert.equal(manifest.dirty,true);
  }finally{rmSync(fixture,{recursive:true,force:true});}
});
