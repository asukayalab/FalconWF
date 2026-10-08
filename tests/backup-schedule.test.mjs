import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const owner=randomUUID();let captured=false;
function cli(mode,args,expected=0){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','-e',`FWF_SCHEDULE_TEST_OWNER=${owner}`,'-e',`FWF_SCHEDULE_TEST_MODE=${mode}`,'cli','wp','--require=/fwf-tests/backup-schedule-bootstrap.php',...args],{encoding:'utf8'});process.stdout.write(r.stdout);process.stderr.write(r.stderr);assert.equal(r.status,expected,`schedule ${mode} failed`);}
try{captured=true;cli('capture',['eval-file','/fwf-tests/backup-schedule.php','capture']);cli('run',['cron','event','run','fwf_backup_scheduled','--due-now']);cli('assert-run',['eval-file','/fwf-tests/backup-schedule.php','assert-run']);cli('die-slot',['eval-file','/fwf-tests/backup-schedule.php','die-slot'],13);cli('assert-death',['eval-file','/fwf-tests/backup-schedule.php','assert-death']);}
finally{if(captured)cli('cleanup',['eval-file','/fwf-tests/backup-schedule.php','cleanup']);}
