import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('release choice shows and requires tag only for a pinned release, preserving its value',()=>{
 const handlers={};const input={value:'v0.1.0-alpha.2',disabled:false,required:false};const channel={value:'stable',addEventListener:(event,callback)=>handlers[event]=callback};
 const row={hidden:false,querySelector:()=>input};const group={querySelector:selector=>selector==='[data-fwf-release-channel]'?channel:row};
 vm.runInNewContext(readFileSync('packages/falcon-plugin/assets/release-choice.js','utf8'),{document:{querySelectorAll:()=>[group]}});
 assert(row.hidden && input.disabled && !input.required);
 channel.value='manual';handlers.change();assert(!row.hidden && !input.disabled && input.required);assert.equal(input.value,'v0.1.0-alpha.2');
 channel.value='testing';handlers.change();assert(row.hidden && input.disabled && !input.required);
 channel.value='manual';handlers.change();assert.equal(input.value,'v0.1.0-alpha.2');
});
