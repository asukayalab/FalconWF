import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
const owner=randomUUID();
function cli(mode,verify=''){
 const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/project-update.php',mode,owner,verify],{stdio:'inherit'});
 if(r.status!==0)throw Error(`Project fixture ${mode} failed (exit ${r.status}).`);
}
let passed=false;
try{cli('run');cli('fail');cli('retry');passed=true;}finally{cli('cleanup',passed?'verify':'');}
