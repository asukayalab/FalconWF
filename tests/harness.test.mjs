import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
function fingerprint(){
  const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/site-state.php','fingerprint',randomUUID()],{encoding:'utf8'});
  assert.equal(r.status,0,'Local state fingerprint failed (values suppressed)');
  assert.match(r.stdout.trim(),/^[a-f0-9]{64}$/);return r.stdout.trim();
}
const before=fingerprint();
const failure=spawnSync(process.execPath,['scripts/local.mjs','test-state-recovery'],{encoding:'utf8'});
assert.notEqual(failure.status,0,'Injected fixture failure must remain a failure');
assert(failure.stdout.includes('Original site settings restored and verified'),'Failed child must run the shared restoration guard');
assert.equal(fingerprint(),before,'Failure must preserve exact original settings and absent options');
console.log('PASS: real child failure remains nonzero and restores original site settings');
