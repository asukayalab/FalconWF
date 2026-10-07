import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
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
