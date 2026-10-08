import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const owner=randomUUID(),compose=['compose','--env-file','local/.env','-f','local/compose.yml'];let fixture,checks=0;
const run=(args,isolated=false)=>spawnSync('docker',[...compose,'run','--rm',...(isolated?['-e',`WORDPRESS_TABLE_PREFIX=${fixture.prefix}`,'-e',`FWF_RECOVERY_TEST_OWNER=${owner}`]:[]),'cli',...args],{encoding:'utf8'});
const cli=(mode,isolated=false,...args)=>{const result=run(['wp',...(isolated?['--skip-plugins','--skip-themes','--require=/fwf-tests/backup-recovery-bootstrap.php']:[]),'eval-file','/fwf-tests/backup-recovery.php',mode,owner,...args],isolated);assert.equal(result.status,0,result.stderr+result.stdout);return result.stdout.trim();};
const rescue=(id,review,confirm=true)=>run(['php','/fwf-tests/backup-recovery-rescue.php',`--${review===undefined?'inspect':'apply'}=${id}`,...(review===undefined?[]:[`--review=${review}`,...(confirm?['--confirm']:[])])],true);
const ok=(value,label)=>{assert(value,label);checks++;console.log('PASS:',label);};
try{
 fixture=JSON.parse(cli('setup'));cli('archive',true);
 for(const phase of ['prepared','journaled','file_written','database_written','before_commit','commit_ack','after_commit','retired']){
  const result=run(['wp','--skip-plugins','--skip-themes','--require=/fwf-tests/backup-recovery-bootstrap.php','eval-file','/fwf-tests/backup-recovery.php','crash',owner,phase],true);assert.equal(result.status,71,result.stderr+result.stdout);ok(result.status===71,`separate restore process dies at ${phase}`);const id=JSON.parse(result.stdout.trim().split('\n').at(-1)).journal;
  const status=JSON.parse(cli('status',true)),committed=['after_commit','commit_ack','retired'].includes(phase);ok(status.identity===`${committed?'AFTER':'BEFORE'} ${owner}` && status.markers===(committed && phase!=='retired'?1:0) && (phase==='retired'?!status.lock && !status.queue_denied:status.lock && status.queue_denied),`${phase}: actual database commit proof, retained owner lock and pending journal guard`);
  if(phase==='before_commit'){
   const broken=run(['wp','option','get','blogname'],true);ok(broken.status!==0 && (broken.stderr+broken.stdout).includes('OWNED RECOVERY BOOT FAILURE'),'fixture plugin blocks normal WordPress bootstrap');
   process.stdout.write(cli('guards',true));checks+=6;
  }
  const inspected=rescue(id);assert.equal(inspected.status,0,inspected.stderr);ok(inspected.status===0,`${phase}: SHORTINIT rescue reads signed journal without plugin/theme bootstrap`);const review=JSON.parse(inspected.stdout);ok(review.decision===(committed?'committed':'rollback'),`${phase}: commit result selects safe resolution without replaying database writes`);
  if(phase==='before_commit'){
   cli('drift',true);const stale=rescue(id,review.review);ok(stale.status!==0,'later file edit invalidates reviewed recovery');const changed=rescue(id);assert.equal(changed.status,0,changed.stderr);const refuse=rescue(id,JSON.parse(changed.stdout).review);ok(refuse.status!==0 && refuse.stderr.includes('File target berubah'),'fresh review still cannot overwrite unrelated newer bytes');cli('repairdrift',true);
  }
  if(phase==='retired'){ok(review.cleanup_only===true,'retired journal offers private cleanup only');cli('drift',true);}
  const current=rescue(id);assert.equal(current.status,0,current.stderr);const applied=rescue(id,JSON.parse(current.stdout).review);ok(applied.status===0,`${phase}: explicit CLI recovery completes owned journal`);
  const after=JSON.parse(cli('status',true));ok(after.pending.length===0 && after.markers===0 && !after.lock && after.retired===0 && after.bad===(committed && phase!=='retired'),`${phase}: rollback restores before files; committed result remains; journal/proof/owner lock cleaned`);if(phase==='retired')ok(after.edited,'retired cleanup preserves newer live file bytes');cli('fixplugin',true);
 }
 const ack=JSON.parse(cli('ack_error',true));let status=JSON.parse(cli('status',true));ok(ack.error && ack.message.includes('Database sudah commit') && status.identity===`AFTER ${owner}` && status.bad && status.pending.length===0,'handled exception in commit acknowledgement preserves committed database/files');cli('fixplugin',true);
 const disconnected=JSON.parse(cli('connection_loss',true));status=JSON.parse(cli('status',true));ok(disconnected.error && status.pending.length===1 && status.bad && status.markers===0,'lost database connection retains journal and files without claiming rollback');const unknown=rescue(status.pending[0]);ok(unknown.status!==0 && unknown.stderr.includes('hasil database belum pasti'),'SHORTINIT refuses unresolved transaction connection changes');cli('fixplugin',true);
 const denied=await fetch('http://localhost:8091/wp-content/plugins/falcon-wf/rescue.php',{headers:{Connection:'close'}});ok(denied.status===403,'rescue CLI refuses HTTP execution');
 console.log(`Recovery crash checks passed: ${checks}. Cloned tables, owner fixture plugin/private directory; actual process death and SHORTINIT.`);
}finally{if(fixture)cli('cleanup');}
