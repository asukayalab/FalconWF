import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,cpSync,readFileSync,writeFileSync,mkdirSync,symlinkSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {createHash} from 'node:crypto';
import {validateProjectArtifacts} from '../scripts/publish-project.mjs';
import {publishRelease} from '../scripts/publish-release.mjs';

test('exported standalone kit builds clean project and publishes exactly ZIP + manifest without overwrites',async t=>{
 const temp=mkdtempSync(path.join(tmpdir(),'fwf-project-automation-'));t.after(()=>rmSync(temp,{recursive:true,force:true}));
 const root=path.join(temp,'project'),kit=path.join(temp,'kit'),output=path.join(temp,'output');const previous=process.env.FWF_PROJECT_OUTPUT;process.env.FWF_PROJECT_OUTPUT=output;t.after(()=>{if(previous===undefined)delete process.env.FWF_PROJECT_OUTPUT;else process.env.FWF_PROJECT_OUTPUT=previous;});
 const run=(cmd,args,cwd=process.cwd())=>{const r=spawnSync(cmd,args,{cwd,encoding:'utf8'});assert.equal(r.status,0,r.stderr);return r.stdout.trim();};
 run(process.execPath,['scripts/export-project-automation.mjs',kit]);
 cpSync('examples/projects/falcon-reference',root,{recursive:true});cpSync(kit,root,{recursive:true});
 writeFileSync(path.join(root,'.gitignore'),'.falcon-output/\n');
 const spec=JSON.parse(readFileSync(path.join(root,'project.json')));mkdirSync(path.join(root,'release-notes'));writeFileSync(path.join(root,'release-notes',`${spec.version}.md`),'Design update\n');
 symlinkSync(path.resolve('node_modules'),path.join(root,'.falcon-tooling/node_modules'));
 run('git',['init'],root);run('git',['add','.'],root);run('git',['-c','user.name=Fixture','-c','user.email=fixture@example.invalid','commit','-m','Fixture'],root);
 const commit=run('git',['rev-parse','HEAD'],root);
 run(process.execPath,[path.join(root,'.falcon-tooling/scripts/build-project.mjs'),'--source',root,'--output',output]);
 const check=()=>validateProjectArtifacts({root,expectedSha:commit,repository:'fixture/website'});const release=check();assert.equal(release.files.length,2);
 const calls=[];let draft=null,ref=null;
 const gh={api:async endpoint=>{
   if(endpoint.includes('/releases?'))return draft?[draft]:[];
   if(endpoint.includes('/git/ref/')){if(!ref)throw Object.assign(Error('missing'),{status:404});return {object:{type:'commit',sha:ref}};}
   throw Error(endpoint);
 },run:async args=>{calls.push(args);
   if(args[0]==='api'){ref=commit;return;}
   if(args[1]==='create'){draft={tag_name:release.tag,draft:true,prerelease:true,target_commitish:commit,assets:[]};}
   if(args[1]==='upload'){draft.assets=release.files.map(file=>{const bytes=readFileSync(file);return {name:path.basename(file),size:bytes.length,digest:'sha256:'+createHash('sha256').update(bytes).digest('hex'),state:'uploaded'};});}
   if(args[1]==='edit')draft.draft=false;
 }};
 const published=await publishRelease({release,gh,validate:validateProjectArtifacts});assert.equal(published.status,'published');assert.equal(draft.assets.length,2);assert(calls.some(c=>c[1]==='edit'));
 const count=calls.length;assert.equal((await publishRelease({release,gh,validate:validateProjectArtifacts})).status,'skipped');assert.equal(calls.length,count,'published release untouched');
 draft.draft=true;await assert.rejects(publishRelease({release,gh,validate:validateProjectArtifacts}),/operator review/);
 writeFileSync(release.files[0],'corrupted');assert.throws(check,/checksum/);
 run(process.execPath,[path.join(root,'.falcon-tooling/scripts/build-project.mjs'),'--source',root,'--output',output]);
 writeFileSync(path.join(root,'page.php'),'<?php // changed');assert.throws(check,/Source changed/);
 assert.throws(()=>validateProjectArtifacts({root,expectedSha:commit,repository:'asukayalab/FalconWF'}));
});
