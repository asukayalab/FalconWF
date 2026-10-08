import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const owner=randomUUID();let captured=false,checks=0;
function cli(mode,expected=0){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/backup-jobs.php',mode,owner],{encoding:'utf8'});assert.equal(r.status,expected,`Backup job ${mode} failed: ${r.stderr}`);return r.stdout.trim();}
function ok(pass,label){assert(pass,label);checks++;console.log('PASS:',label);}
try {
 const id=cli('capture');captured=true;ok(/^[a-f0-9-]{36}$/.test(id),'persistent queue returns private owned job ID');
 const snapshot=JSON.parse(cli('capture-step'));ok(snapshot.phase==='packing' && snapshot.total>8,'single snapshot persists before archive workers');
 const first=JSON.parse(cli('pack-step'));ok(first.cursor===8 && first.cursor<first.total,'archive checkpoint records a partial batch');
 cli('die-with-lease',13);const next=JSON.parse(cli('pack-step'));ok(next.cursor>first.cursor,'separate process resumes checkpoint after terminated lease holder');
 ok(cli('corrupt-zip')==='failed','damaged partial ZIP pauses with failure status');
 ok(cli('resume')==='resumed','human retry queues archive rebuild from existing snapshot');
 const finished=JSON.parse(cli('finish'));ok(finished.phase==='done' && finished.cursor===finished.total && finished.components[0]==='media','validated final ZIP preserves snapshot despite live file changes');
 cli('snapshot-interrupt',13);ok(cli('snapshot-restart')==='done','worker killed during database snapshot restarts safely in a new process');
 ok(cli('denial')==='denied','queue controls and status enforce administrator capability');
 ok(cli('cancel')==='cancelled','cancel discards staging without changing site files');
 ok(cli('revocation')==='failed','worker rechecks requesting administrator permission');
 console.log(`Backup job checks passed: ${checks}. Separate Docker processes, local only.`);
} finally {if(captured)cli('cleanup');}
