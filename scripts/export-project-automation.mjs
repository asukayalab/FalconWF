import {mkdirSync,copyFileSync,existsSync,writeFileSync,readFileSync} from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url)),destination=process.argv[2];
assert(destination,'Use node scripts/export-project-automation.mjs <new kit folder>');
const target=path.resolve(destination);assert(!existsSync(target),'Choose a new kit folder; existing files are never overwritten');
for(const file of ['scripts/build-project.mjs','scripts/publish-project.mjs','scripts/publish-release.mjs','scripts/zip.py','packages/falcon-plugin/resources/project-runtime.json','packages/falcon-plugin/resources/project-blueprint.schema.json','package.json','package-lock.json']){
  const output=path.join(target,'.falcon-tooling',file);mkdirSync(path.dirname(output),{recursive:true});copyFileSync(path.join(root,file),output);
}
const tooling=path.join(target,'.falcon-tooling');const metadata=JSON.parse(readFileSync(path.join(tooling,'package.json')));metadata.scripts={'build:project':'node scripts/build-project.mjs','publish:project':'node scripts/publish-project.mjs'};writeFileSync(path.join(tooling,'package.json'),JSON.stringify(metadata,null,2)+'\n');
mkdirSync(path.join(target,'.github/workflows'),{recursive:true});copyFileSync(path.join(root,'examples/automation/project-release.yml'),path.join(target,'.github/workflows/falcon-project.yml'));
writeFileSync(path.join(target,'.falcon-tooling/.gitignore'),'node_modules/\nbuild/\ndist/\n');
writeFileSync(path.join(target,'README-automation.md'),'Copy .falcon-tooling and .github into the separate project repository. Keep project.json and mapped runtime at its root. Add release-notes/<version>.md. Commit all sources; push main builds and publishes a new prerelease with ZIP + project-manifest.json. Existing releases/drafts are never overwritten. No WordPress deployment or theme activation. Stable automatic publishing remains blocked. This kit contains tooling only, no credentials, client content or core installer.\n');
console.log(`Project automation kit: ${target}`);
