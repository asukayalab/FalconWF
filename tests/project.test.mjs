import {spawnSync} from 'node:child_process';
import {randomUUID} from 'node:crypto';
import assert from 'node:assert/strict';
const owner=randomUUID();
function fixture(mode){const r=spawnSync('docker',['compose','--env-file','local/.env','-f','local/compose.yml','run','--rm','cli','wp','eval-file','/fwf-tests/project-fixture.php',mode,owner],{encoding:'utf8'});if(r.status!==0)throw Error(`Reference ${mode} failed: ${r.stderr}`);return r.stdout;}
const get=url=>fetch(url,{headers:{Connection:'close'}});
let checks=0;const ok=(value,label)=>{assert(value,label);checks++;console.log('PASS:',label);};
try{
 const output=fixture('setup');const data=JSON.parse(output.split('FWF_REFERENCE_JSON:')[1].trim());
 for(const [route,template] of [['home','front-page'],['page','page'],['archive','project-archive'],['single','project-single']]){
  const response=await get(data[route]);const html=await response.text();
  ok(response.status===200 && html.includes(`data-falcon-template="${template}"`),`reference ${route} resolves child template`);
  ok((html.match(/<h1[\s>]/g)||[]).length===1 && html.includes('id="main" tabindex="-1"'),`reference ${route} has one primary heading and skip target`);
  ok(!html.includes('REFERENCE_PRIVATE_LOCATION'),`reference ${route} excludes private custom field`);
  if(route==='home' || route==='archive'){
   ok(html.includes('Reference published') && !html.includes('Reference draft') && !html.includes('Reference private') && !html.includes('Reference password'),`reference ${route} listing is published and password-free`);
  }
  if(route==='single' || route==='page' || route==='home'){
   ok((html.match(/<link rel="canonical"/g)||[]).length===1 && (html.match(/<title>/g)||[]).length===1,`SEO ${route} retains one native canonical and title`);
   const schema=JSON.parse(html.match(/<script type="application\/ld\+json">(.*?)<\/script>/s)?.[1]||'null');
   ok(schema?.['@graph']?.[0]?.['@type']==='WebPage' && (html.match(/property="og:title"/g)||[]).length===1,`SEO ${route} schema and Open Graph render once`);
   ok(!html.includes('REFERENCE_PRIVATE_LOCATION'),`SEO ${route} excludes private metadata`);
  } else {ok(!html.includes('property="og:title"'),'archive leaves SEO metadata to external owner');}
  if(route==='single'){ok(html.includes('class="content-details"') && html.includes('2026'),'Project detail renders public schema fields through parent helper');}
  if(route==='home'){
   ok(html.includes('Reference homepage editable content.') && !html.includes('DALAM PEMBANGUNAN') && !html.includes('noindex'), 'final homepage renders editable page and permits indexing on public site');
   const asset=html.match(/href=['"]([^'"]+falcon-reference\/assets\/design\.css[^'"]*)['"]/);ok(!!asset,'child design stylesheet enqueued');
   const css=await get(asset[1].replaceAll('&#038;','&'));ok(css.status===200 && (await css.text()).includes('--reference-heading-font'),'child design tokens load without 404');
  }
 }
 for(const route of ['draft','private']){const response=await get(data[route]);ok(response.status===404,`guest ${route} Project refused`);}
 const protectedResponse=await get(data.password);const protectedHtml=await protectedResponse.text();
 ok(protectedResponse.status===200 && protectedHtml.includes('post-password-form') && !protectedHtml.includes('REFERENCE_PRIVATE_LOCATION') && !protectedHtml.includes('Reference body password') && !protectedHtml.includes('class="content-details"'), 'password Project hides body and public/private metadata');
 ok(!protectedHtml.includes('property="og:title"') && !protectedHtml.includes('application/ld+json'),'protected Project emits no Falcon SEO');
 fixture('design');
 for(const route of ['home','page','archive','single']){const html=await (await get(data[route])).text();ok(html.includes('--fwf-ink:#135724;') && html.includes('--fwf-h1:68px;'),`saved design connects to child ${route}`);}
 fixture('hide-site');let response=await get(data.home);ok((await response.text()).includes('noindex'),'child homepage respects WordPress discourage-indexing setting');
 fixture('dynamic-home');response=await get(data.home);const dynamicHtml=await response.text();ok(dynamicHtml.includes('data-falcon-template="front-page"'),'child homepage supports latest-posts Reading mode');
 ok((dynamicHtml.match(/<link rel="canonical"/g)||[]).length===1 && dynamicHtml.includes('"@type":"WebSite"'),'latest-posts home gets one supplemental canonical and site schema');
 fixture('deactivate');response=await get(data.page);const withoutPlugin=await response.text();ok(response.status===200 && withoutPlugin.includes('data-falcon-template="page"'),'child native Page survives FP deactivation');
 ok(withoutPlugin.includes('property="og:title"') && withoutPlugin.includes('application/ld+json'),'SEO metadata survives actual FP deactivation');
 ok(withoutPlugin.includes('--fwf-ink:#135724;'),'theme retains saved design rendering after FP deactivation');
 fixture('parent');response=await get(data.home);const fallback=await response.text();ok(fallback.includes('DALAM PEMBANGUNAN') && fallback.includes('noindex'),'parent coming-soon remains noindex on public site');
 ok(!fallback.includes('--fwf-ink:#135724;'),'child settings do not leak into parent theme');
 console.log(`Project HTTP checks passed: ${checks}. Local reference, not client acceptance.`);
}finally{fixture('restore');}
