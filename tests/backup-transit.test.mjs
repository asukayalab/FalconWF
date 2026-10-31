import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const owner=randomUUID(),root='/fwf-backups/transit-test-'+owner;
const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','-e','FWF_TRANSIT_TEST_OWNER='+owner,'-e','HOME='+root+'/home','-e','TMPDIR='+root+'/transit','cli','wp','--require=/fwf-tests/backup-transit-bootstrap.php','eval-file','/fwf-tests/backup-transit.php'],{stdio:'inherit'});
assert.equal(r.status,0,'private transit integration failed');
