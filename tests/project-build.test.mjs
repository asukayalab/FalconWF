import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {readFileSync} from 'node:fs';
const build=()=>{const r=spawnSync(process.execPath,['scripts/build-project.mjs'],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);};
test('client example ZIP is separate, complete and deterministic',()=>{
 build();const spec=JSON.parse(readFileSync('examples/projects/falcon-reference/project.json'));
 const manifest=JSON.parse(readFileSync('dist/projects/project-manifest.json'));assert.equal(manifest.project_id,spec.project_id);assert.equal(manifest.packages[0].version,spec.version);
 const zip=`dist/projects/${manifest.packages[0].artifact}`;const before=readFileSync(zip);
 const checked=spawnSync('python3',['tests/check_project_zip.py'],{encoding:'utf8'});assert.equal(checked.status,0,checked.stderr);
 build();assert.deepEqual(readFileSync(zip),before);console.log(checked.stdout.trim());
});
