import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','-e',`FWF_MEDIA_TEST_OWNER=${randomUUID()}`,'cli','wp','--require=/fwf-tests/backup-media-bootstrap.php','eval-file','/fwf-tests/backup-media.php'],{encoding:'utf8'});process.stdout.write(r.stdout);process.stderr.write(r.stderr);assert.equal(r.status,0,'selected media integration failed');
