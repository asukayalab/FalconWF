import {spawnSync} from 'node:child_process';
import {rmSync} from 'node:fs';
import assert from 'node:assert/strict';
const output='dist/blueprint-contract';
try{
 let r=spawnSync(process.execPath,['scripts/build-project.mjs','--source','examples/projects/falcon-blueprint-reference','--output',output],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);
 r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/blueprint-package.php'],{stdio:'inherit'});assert.equal(r.status,0,'blueprint package contract failed');
}finally{rmSync(output,{recursive:true,force:true});}
