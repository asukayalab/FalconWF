import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const result=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','-e',`FWF_STORAGE_TEST_OWNER=${randomUUID()}`,'cli','wp','--require=/fwf-tests/backup-storage-bootstrap.php','eval-file','/fwf-tests/backup-storage.php'],{encoding:'utf8'});
process.stdout.write(result.stdout);process.stderr.write(result.stderr);
assert.equal(result.status,0,'private storage setup/backup/restore integration failed');
