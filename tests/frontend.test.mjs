import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
function state(mode){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/frontend-state.php',mode],{encoding:'utf8'});assert.equal(r.status,0,'Local fixture state failed');}
// State changes run WP-CLI long enough to outlive Apache keep-alive connections.
const get=url=>fetch(url,{headers:{Connection:'close'}});
let checks=0;function ok(x,label){assert(x,label);checks++;console.log('PASS:',label);}
let captured=false;
try {
 state('identity-fixture');captured=true;
 const initial=await get('http://localhost:8091/');const html=await initial.text();
 ok(initial.status===200 && html.includes('DALAM PEMBANGUNAN'),'default FT renders installed runtime');
 ok(html.includes('WP Site Fixture') && html.includes('WP Tagline Fixture') && !html.includes('Falcon WF Local'),'header uses native site title and tagline');
 ok(html.includes('Website dikelola dengan Falcon WF dari') && html.includes('href="http://asukayalab.com"'),'requested footer credit links Asukayalab');
 ok(html.includes('noindex') && html.includes('href="#main"') && html.includes('id="main" tabindex="-1"'),'coming-soon robots and skip link present');
 const asset=html.match(/href=['"]([^'"]+main\.[a-f0-9]+\.css[^'"]*)['"]/);ok(!!asset,'generated CSS enqueued');
 const css=await get(asset[1].replaceAll('&#038;','&'));ok(css.status===200,'generated CSS serves without 404');
 state('maintenance-on');
 const maintenance=await get('http://localhost:8091/');ok(maintenance.status===503 && maintenance.headers.get('retry-after')==='3600','maintenance uses HTTP 503 and Retry-After');
 const login=await get('http://localhost:8091/wp-login.php');ok(login.status===200,'maintenance preserves login recovery');
 state('maintenance-off');state('deactivate');
 const fallback=await get('http://localhost:8091/');ok(fallback.status===200 && (await fallback.text()).includes('DALAM PEMBANGUNAN'),'FT remains usable with FP actually deactivated');
 state('activate');
 const restored=await get('http://localhost:8091/');ok(restored.status===200,'FP reactivation retains working frontend');
 console.log(`Frontend HTTP checks passed: ${checks}. Responsive visual review is separate.`);
} finally {if(captured){state('identity-restore');}}
