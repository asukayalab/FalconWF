import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const seed=()=>{
 const r=spawnSync(process.execPath,['scripts/local.mjs','seed'],{encoding:'utf8'});
 assert.equal(r.status,0,'Local demo seed must succeed');return JSON.parse(r.stdout);
};
const rows=seed();let checks=0;const ok=(x,label)=>{assert(x,label);checks++;console.log('PASS:',label);};
ok(rows.length===2 && rows[0].preserved,'demo seed preserves Project and creates listing page only');
const project=await fetch(rows[0].url,{headers:{Connection:'close'}});const html=await project.text();
ok(project.status===200 && html.includes('[DEMO]') && html.includes('content-details'),'published Project renders structured fields');
const image=html.match(/<img[^>]+src="([^"]+)"/);assert(image,'Demo cover must be rendered');
const asset=await fetch(image[1].replaceAll('&#038;','&'));ok(asset.status===200 && asset.headers.get('content-type')?.startsWith('image/'),'generated Project cover serves');await asset.arrayBuffer();
const listing=await fetch(rows[1].url,{headers:{Connection:'close'}});const listingHTML=await listing.text();
ok(listing.status===200 && listingHTML.includes('fwf-listing') && listingHTML.includes('demo-kebun-belajar-bersama'),'Karya Bangunan lists categorized Project');
const repeated=seed();ok(repeated[1].id===rows[1].id && repeated[0].id===rows[0].id,'repeated seed does not duplicate listing or Project');
console.log(`Demo HTTP/seed checks passed: ${checks}. Fictional local examples only.`);
