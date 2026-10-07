import {existsSync,readFileSync,readdirSync} from 'node:fs';
import assert from 'node:assert/strict';
import path from 'node:path';
const inventory=JSON.parse(readFileSync('release/inventory.json'));
const seen=new Set();
for (const i of inventory) {
  assert(existsSync(i.source),`Missing ${i.source}`);
  assert(i.loaded_by && i.proof,`Missing connection/proof ${i.source}`);
  assert(!seen.has(i.source),`Duplicate source ${i.source}`); seen.add(i.source);
  if (i.source.includes('/src/') && i.source.endsWith('.php')) {
    const content=readFileSync(i.source,'utf8');
    const suffix=i.source.split('/src/')[1];
    const ns=`FalconWF${path.dirname(suffix)==='.'?'':'\\'+path.dirname(suffix).replaceAll('/','\\')}`;
    assert(content.includes(`namespace ${ns};`),`Autoload namespace mismatch ${i.source}`);
    assert(content.includes(`class ${path.basename(suffix,'.php')}`),`Autoload class mismatch ${i.source}`);
  }
}
function walk(folder) {return readdirSync(folder,{withFileTypes:true}).flatMap(e=>e.isDirectory()?walk(`${folder}/${e.name}`):[`${folder}/${e.name}`]);}
for (const file of walk('packages').filter(p=>p.endsWith('.php') || p.endsWith('/style.css'))) assert(seen.has(file),`Unmapped runtime ${file}`);
for (const file of ['packages/falcon-plugin/assets/src/admin.css','packages/falcon-theme/assets/src/main.css']) assert(existsSync(file));
console.log(`Static source/namespace/inventory checks passed (${inventory.length} paths). Runtime evidence is separate.`);
