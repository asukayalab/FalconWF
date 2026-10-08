import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const result=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','-e',`FWF_RETENTION_TEST_OWNER=${randomUUID()}`,'cli','wp','--require=/fwf-tests/backup-retention-bootstrap.php','eval-file','/fwf-tests/backup-retention.php'],{encoding:'utf8'});
process.stdout.write(result.stdout);process.stderr.write(result.stderr);assert.equal(result.status,0,'isolated retention integration failed');
