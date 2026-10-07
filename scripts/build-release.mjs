import {readFileSync, writeFileSync, mkdirSync, rmSync, copyFileSync} from 'node:fs';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
process.chdir(root);
const components=JSON.parse(readFileSync('release/components.json'));
const inventory=JSON.parse(readFileSync('release/inventory.json'));
const hash=b=>createHash('sha256').update(b).digest('hex');
const inputs=[];
for (const folder of ['build/plugin','build/theme']) {
  rmSync(folder,{recursive:true,force:true}); mkdirSync(folder,{recursive:true});
}
mkdirSync('dist',{recursive:true});
for (const item of inventory) {
  const target=path.join('build',item.package,item.path);
  mkdirSync(path.dirname(target),{recursive:true});
  let body=readFileSync(item.source,'utf8');
  inputs.push([item.source,hash(body)]);
  body=body.replaceAll('0.1.0-alpha.2',components.version);
  writeFileSync(target,body);
}
for (const [pkg,name] of [['plugin','admin'],['theme','main']]) {
  const source=`packages/falcon-${pkg}/assets/src/${name}.css`;
  const bytes=readFileSync(source); inputs.push([source,hash(bytes)]);
  const filename=`${name}.${hash(bytes).slice(0,12)}.css`;
  mkdirSync(`build/${pkg}/assets/dist`,{recursive:true});
  writeFileSync(`build/${pkg}/assets/dist/${filename}`,bytes);
  writeFileSync(`build/${pkg}/assets/manifest.json`,JSON.stringify({[`${name}.css`]:`dist/${filename}`},null,2)+'\n');
}
function zip(source,out,prefix) {
  const result=spawnSync('python3',['scripts/zip.py',source,out,prefix],{stdio:'inherit'});
  if (result.status!==0) throw new Error('ZIP build failed');
}
const ft=`falcon-theme-${components.version}.zip`, fp=`falcon-wf-${components.version}.zip`;
zip('build/theme',`dist/${ft}`,'falcon-theme');
mkdirSync('build/plugin/bundles',{recursive:true});
copyFileSync(`dist/${ft}`,'build/plugin/bundles/falcon-theme.zip');
const themeHash=hash(readFileSync(`dist/${ft}`));
writeFileSync('build/plugin/installer-manifest.json',JSON.stringify({schema:1,id:'falcon-wf',version:components.version,theme:{id:'falcon-theme',version:components.version,artifact:'bundles/falcon-theme.zip',sha256:themeHash,min_wp:components.min_wp,min_php:components.min_php}},null,2)+'\n');
zip('build/plugin',`dist/${fp}`,'falcon-wf');
const commit=spawnSync('git',['rev-parse','HEAD'],{encoding:'utf8'});
const dirty=spawnSync('git',['status','--porcelain'],{encoding:'utf8'});
const manifest={schema:1,product:components.product,version:components.version,status:components.release_status,source_commit:commit.status===0?commit.stdout.trim():null,dirty:!!dirty.stdout.trim(),source_digest:hash(JSON.stringify(inputs)),built_at:new Date().toISOString(),packages:[
  {id:'falcon-wf',type:'plugin',version:components.version,artifact:fp,sha256:hash(readFileSync(`dist/${fp}`)),min_wp:components.min_wp,min_php:components.min_php},
  {id:'falcon-theme',type:'theme',version:components.version,artifact:ft,sha256:themeHash,min_wp:components.min_wp,min_php:components.min_php}
]};
writeFileSync('dist/release-manifest.json',JSON.stringify(manifest,null,2)+'\n');
writeFileSync('dist/checksums.sha256',manifest.packages.map(p=>`${p.sha256}  ${p.artifact}`).join('\n')+'\n');
writeFileSync('dist/build-report.json',JSON.stringify({source_commit:manifest.source_commit,dirty:manifest.dirty,inputs,tests:'Not run by build; see engineering evidence. Release gates remain pending.'},null,2)+'\n');
console.log(`Built development installer: dist/${fp}`);
