import {readFileSync, writeFileSync, mkdirSync, rmSync, mkdtempSync, lstatSync, readdirSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import path from 'node:path';
import {tmpdir} from 'node:os';
import {fileURLToPath} from 'node:url';
import assert from 'node:assert/strict';
import Ajv from 'ajv';
import semver from 'semver';
process.chdir(fileURLToPath(new URL('../',import.meta.url)));
const contract=JSON.parse(readFileSync('packages/falcon-plugin/resources/project-runtime.json'));
assert.equal(contract.schema,1);
const extensions=[...contract.text_extensions,...contract.binary_extensions];
const extension=file=>path.extname(file).slice(1);
const args=process.argv.slice(2);
assert(args.length===0 || (args.length===4 && args[0]==='--source' && args[2]==='--output'),'Use --source <project directory> --output <artifact directory>');
const source=args.length?path.resolve(args[1]):'examples/projects/falcon-reference';
const spec=JSON.parse(readFileSync(`${source}/project.json`));
assert.equal(spec.schema,1);assert.match(spec.project_id,/^[a-z][a-z0-9-]{1,63}$/);assert(!['falcon-wf','falcon-theme'].includes(spec.project_id));
assert.equal(spec.package.id,spec.project_id);assert.equal(spec.package.type,'theme');assert.equal(spec.package.parent,'falcon-theme');
assert.match(spec.version,/^\d+\.\d+\.\d+-(alpha|beta|rc)\.[1-9]\d*$/);assert.equal(spec.status,'development');
assert(Array.isArray(spec.runtime) && new Set(spec.runtime).size===spec.runtime.length);
for(const required of contract.required_files) assert(spec.runtime.includes(required),`Project runtime requires ${required}; add it before building a release`);
const hash=bytes=>createHash('sha256').update(bytes).digest('hex');
let build=`build/projects/${spec.project_id}`;const output=args.length?path.resolve(args[3]):'dist/projects';
const sourceRoot=path.resolve(source),outputRoot=path.resolve(output);
assert(outputRoot!==sourceRoot && !outputRoot.startsWith(sourceRoot+path.sep),'Output must be outside project source');
// Exported tooling can live inside the project repo; stage outside its source tree.
if(path.resolve(build).startsWith(sourceRoot+path.sep)){const staging=mkdtempSync(path.join(tmpdir(),'falcon-project-build-'));build=path.join(staging,spec.project_id);process.once('exit',()=>rmSync(staging,{recursive:true,force:true}));}
const buildRoot=path.resolve(build);
const overlaps=(a,b)=>a===b || a.startsWith(b+path.sep) || b.startsWith(a+path.sep);
assert(!overlaps(sourceRoot,buildRoot) && !overlaps(outputRoot,buildRoot),'Source/output must not overlap generated build');
rmSync(build,{recursive:true,force:true});mkdirSync(build,{recursive:true});mkdirSync(output,{recursive:true});
let expandedBytes=0;
const inputs=[{source:'project-runtime-contract',sha256:hash(readFileSync('packages/falcon-plugin/resources/project-runtime.json'))},{source:`${source}/project.json`,sha256:hash(readFileSync(`${source}/project.json`))}];
for(const relative of spec.runtime){
 assert(typeof relative==='string' && !path.isAbsolute(relative) && !relative.includes('..') && !relative.includes('\\') && !relative.includes('\0') && relative.split('/').every(part=>part && part!=='.'));
 assert(extensions.includes(extension(relative)) || contract.json_files.includes(relative),`Unsupported project runtime asset: ${relative}`);const file=path.join(source,relative);
 let checked=sourceRoot;assert(!lstatSync(checked).isSymbolicLink());for(const part of relative.split('/')){checked=path.join(checked,part);assert(!lstatSync(checked).isSymbolicLink(),'Symlink runtime source refused');}
 assert(lstatSync(file).isFile());
 const original=readFileSync(file);
 let bytes=original;
 if(contract.text_extensions.includes(extension(relative)) || contract.json_files.includes(relative)){const text=original.toString('utf8').replaceAll('@@PROJECT_VERSION@@',spec.version).replaceAll('@@PROJECT_ID@@',spec.project_id);assert(!text.includes('@@PROJECT_'));bytes=Buffer.from(text);}
 expandedBytes+=bytes.length;assert(expandedBytes<=contract.max_expanded_bytes,'Project runtime exceeds expanded ZIP limit');
 const target=path.join(build,relative);mkdirSync(path.dirname(target),{recursive:true});writeFileSync(target,bytes);
 inputs.push({source:file,path:relative,sha256:hash(original)});
}
const walk=folder=>readdirSync(folder,{withFileTypes:true}).filter(e=>!['.git','.github','.falcon-tooling','node_modules'].includes(e.name)).flatMap(e=>e.isDirectory()?walk(path.join(folder,e.name)):[path.join(folder,e.name)]);
const runtime=walk(source).filter(file=>(extensions.includes(extension(file)) || contract.json_files.includes(path.relative(source,file)))).map(file=>path.relative(source,file));
assert.deepEqual(runtime.sort(),[...spec.runtime].sort(),'All runtime sources must be mapped');
const style=readFileSync(`${build}/style.css`,'utf8');assert.equal(style.match(/^[ *]*Template:[ \t]*(.+)$/m)?.[1]?.trim(),spec.package.parent,'Parent header must match manifest');assert.equal(style.match(/^[ *]*Version:[ \t]*(.+)$/m)?.[1]?.trim(),spec.version,'Version header must match manifest');
assert(style.match(/^[ *]*Theme Name:[ \t]*(.+)$/m)?.[1]?.trim(),'Theme Name header is required');
let blueprint;
if(spec.runtime.includes('falcon-blueprint.json')){
 const schema=JSON.parse(readFileSync('packages/falcon-plugin/resources/project-blueprint.schema.json'));
 const document=JSON.parse(readFileSync(`${build}/falcon-blueprint.json`));const validate=new Ajv({strict:false,allErrors:true}).compile(schema);
 assert(validate(document),JSON.stringify(validate.errors));assert.equal(document.project_id,spec.project_id);assert.equal(document.version,spec.version);
 assert.equal(new Set(document.pages.map(p=>p.key)).size,document.pages.length,'Duplicate blueprint roles');assert.equal(new Set(document.pages.map(p=>p.slug)).size,document.pages.length,'Duplicate blueprint slugs');
 for(const page of document.pages)assert(page.template==='default'||(page.template.endsWith('.php')&&spec.runtime.includes(page.template)),'Blueprint template must be a mapped runtime file');
 for(const [id,min] of Object.entries({'falcon-wf':'0.1.0-alpha.22','falcon-theme':'0.1.0-alpha.7'})){const range=spec.compatibility?.[id];assert(range && !range.optional && semver.gte(range.min,min),`Blueprint requires ${id} >=${min}, not optional`);}
 for(const group of Object.values(document.definitions?.groups??{})){if(group.types?.includes('page')){assert(group.page_roles?.length,'Page field groups require page_roles');}if(group.page_roles){assert(group.types.length===1 && group.types[0]==='page' && group.page_roles.length && group.page_roles.every(role=>document.pages.some(p=>p.key===role)),'Unknown or invalid Page role location');}}
 blueprint={schema:1,file:'falcon-blueprint.json',project_id:document.project_id,version:document.version,sha256:hash(readFileSync(`${build}/falcon-blueprint.json`))};
 inputs.push({source:'project-blueprint-schema',sha256:hash(readFileSync('packages/falcon-plugin/resources/project-blueprint.schema.json'))});
}
const artifact=`${spec.package.id}-${spec.version}.zip`;
const zipped=spawnSync('python3',['scripts/zip.py',build,`${output}/${artifact}`,spec.package.id],{stdio:'inherit'});if(zipped.status!==0)throw Error('Project ZIP failed');
assert(readFileSync(`${output}/${artifact}`).length<=contract.max_archive_bytes,'Project ZIP exceeds compressed archive limit');
const git=argv=>spawnSync('git',argv,{encoding:'utf8',cwd:sourceRoot});
const commit=git(['rev-parse','HEAD']),dirty=git(['status','--porcelain']);
const tracked=git(['ls-files','--error-unmatch','project.json',...spec.runtime]);
const clean=commit.status===0 && dirty.status===0 && !dirty.stdout.trim() && tracked.status===0;
const manifest={schema:1,project_id:spec.project_id,version:spec.version,status:spec.status,source_commit:commit.status===0 && tracked.status===0?commit.stdout.trim():null,dirty:!clean,source_digest:hash(JSON.stringify(inputs)),packages:[{...spec.package,version:spec.version,artifact,sha256:hash(readFileSync(`${output}/${artifact}`)),min_wp:spec.min_wp,min_php:spec.min_php,compatibility:spec.compatibility,...(blueprint?{blueprint}:{})}]};
writeFileSync(`${output}/project-manifest.json`,JSON.stringify(manifest,null,2)+'\n');
writeFileSync(`${output}/build-report.json`,JSON.stringify({inputs,acceptance:'Local reference only; live client/private distribution acceptance pending.'},null,2)+'\n');
console.log(`Built project: ${output}/${artifact}`);
