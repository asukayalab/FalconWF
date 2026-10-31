import {spawnSync} from 'node:child_process';
import {mkdtempSync,cpSync,writeFileSync,readFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
const source=mkdtempSync(path.join(tmpdir(),'fwf-contract-'));
const output=path.resolve('dist/project-contract');
try{
 cpSync('examples/projects/falcon-reference',source,{recursive:true});
 const spec=JSON.parse(readFileSync(path.join(source,'project.json')));spec.project_id='fwf-contract-fixture';spec.package.id=spec.project_id;
 spec.runtime.push('assets/motion.js','assets/font.woff2');
 writeFileSync(path.join(source,'assets/motion.js'),'window.FalconContractFixture=true;');writeFileSync(path.join(source,'assets/font.woff2'),Buffer.from([0,255,128,70,79,78,84]));
 writeFileSync(path.join(source,'project.json'),JSON.stringify(spec));
 let r=spawnSync(process.execPath,['scripts/build-project.mjs','--source',source,'--output',output],{encoding:'utf8'});assert.equal(r.status,0,r.stderr);
 r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/project-contract.php'],{stdio:'inherit'});assert.equal(r.status,0,'builder → verifier contract failed');
}finally{rmSync(source,{recursive:true,force:true});rmSync(output,{recursive:true,force:true});}
