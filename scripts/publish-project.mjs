import assert from 'node:assert/strict';
import {readFileSync,lstatSync,realpathSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {publishRelease,githubCli} from './publish-release.mjs';

// Runs in the separate project repository. No core/client account is guessed.
export function validateProjectArtifacts({root,expectedSha,repository}) {
  assert(/^[\w.-]+\/[\w.-]+$/.test(repository??'') && repository!=='asukayalab/FalconWF','Use the identified separate project repository');
  assert(/^[a-f0-9]{40}$/.test(expectedSha??''),'Expected full commit SHA required');
  const spec=JSON.parse(readFileSync(path.join(root,'project.json')));
  assert(spec.status==='development' && /^\d+\.\d+\.\d+-(alpha|beta|rc)\.[1-9]\d*$/.test(spec.version),'Automatic stable publication awaits release acceptance');
  assert(/^[a-z][a-z0-9-]{1,63}$/.test(spec.project_id) && !['falcon-wf','falcon-theme'].includes(spec.project_id));
  const output=process.env.FWF_PROJECT_OUTPUT;assert(output && path.isAbsolute(output),'Absolute FWF_PROJECT_OUTPUT required');
  const manifestFile=path.join(output,'project-manifest.json');
  const read=file=>{assert(lstatSync(file).isFile() && !lstatSync(file).isSymbolicLink());assert(path.dirname(realpathSync(file))===realpathSync(output),'Artifact escaped output');return readFileSync(file);};
  const manifest=JSON.parse(read(manifestFile));
  assert(manifest.schema===1 && manifest.project_id===spec.project_id && manifest.version===spec.version && manifest.status===spec.status);
  assert(manifest.dirty===false && manifest.source_commit===expectedSha && /^[a-f0-9]{64}$/.test(manifest.source_digest??''),'Clean matching source provenance required');
  assert(Array.isArray(manifest.packages) && manifest.packages.length===1);
  const p=manifest.packages[0];
  assert(spec.package.id===spec.project_id && p.id===spec.package.id && p.type==='theme' && p.parent==='falcon-theme' && p.version===spec.version);
  assert(p.artifact===`${p.id}-${p.version}.zip` && /^[a-f0-9]{64}$/.test(p.sha256??''));
  assert.deepEqual(p.compatibility,spec.compatibility);assert(p.min_wp===spec.min_wp && p.min_php===spec.min_php);
  const zip=path.join(output,p.artifact);assert(createHash('sha256').update(read(zip)).digest('hex')===p.sha256,'Project ZIP checksum mismatch');
  const git=argv=>spawnSync('git',argv,{cwd:root,encoding:'utf8'});
  const head=git(['rev-parse','HEAD']),state=git(['status','--porcelain']),tracked=git(['ls-files','--error-unmatch','project.json',...spec.runtime]);
  assert(head.status===0 && head.stdout.trim()===expectedSha && state.status===0 && !state.stdout.trim() && tracked.status===0,'Source changed after build');
  const notes=path.join(root,'release-notes',`${spec.version}.md`);const relative=path.relative(realpathSync(root),realpathSync(notes));assert(relative!== '..' && !relative.startsWith('..'+path.sep) && !path.isAbsolute(relative),'Notes escaped project');assert(lstatSync(notes).isFile() && !lstatSync(notes).isSymbolicLink() && readFileSync(notes,'utf8').trim(),'Add project release notes');
  return {root,artifactRoot:output,repository,commit:expectedSha,version:spec.version,tag:`v${spec.version}`,manifest,files:[zip,manifestFile],notes,title:spec.project_id};
}
if(process.argv[1] && path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
  assert(process.env.GITHUB_ACTIONS==='true' && process.env.GITHUB_EVENT_NAME==='push' && process.env.GITHUB_REF==='refs/heads/main','Publication requires the project main-push workflow');
  const release=validateProjectArtifacts({root:process.env.GITHUB_WORKSPACE,expectedSha:process.env.GITHUB_SHA,repository:process.env.GITHUB_REPOSITORY});
  console.log(JSON.stringify(await publishRelease({release,gh:githubCli(),validate:validateProjectArtifacts})));
}
