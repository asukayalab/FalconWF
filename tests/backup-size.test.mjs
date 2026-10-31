import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('backup total follows selection and counts settings once with database',()=>{
 const mib=1024*1024,output={textContent:''},included={hidden:false};
 const boxes=Object.entries({database:40,media:100,plugins:10,themes:5,settings:1}).map(([value,size])=>({value,checked:true,dataset:{bytes:String(size*mib)},addEventListener(event,callback){this.change=callback;}}));
 vm.runInNewContext(readFileSync('packages/falcon-plugin/assets/backup.js','utf8'),{document:{querySelector:selector=>selector==='[data-fwf-backup-total]'?output:selector==='[data-fwf-settings-included]'?included:null,querySelectorAll:selector=>selector.includes('components[]')?boxes:[]}});
 assert.equal(output.textContent,'155 MiB');assert.equal(included.hidden,false);
 boxes[0].checked=false;boxes[0].change();assert.equal(output.textContent,'116 MiB');assert.equal(included.hidden,true);
 boxes.forEach(box=>box.checked=false);boxes[0].change();assert.equal(output.textContent,'0 B');
 boxes[4].checked=true;boxes[4].change();assert.equal(output.textContent,'1 MiB');
});

test('requested backup download submits once and keeps a manual fallback',()=>{
 const script=readFileSync('packages/falcon-plugin/assets/backup.js','utf8');let submitted=0;class Form{requestSubmit(){submitted++;}}
 const form=new Form(),marker={dataset:{fwfAutoDownload:'owned-job'},previousElementSibling:form},values=new Map();
 const context={HTMLFormElement:Form,sessionStorage:{getItem:key=>values.get(key),setItem:(key,value)=>values.set(key,value)},document:{querySelector:selector=>selector==='[data-fwf-auto-download]'?marker:null,querySelectorAll:()=>[]}};
 vm.runInNewContext(script,context);vm.runInNewContext(script,context);assert.equal(submitted,1);assert.equal(form.target,'fwf-backup-download-frame');
 context.sessionStorage={getItem(){throw Error('Storage disabled');}};vm.runInNewContext(script,context);assert.equal(submitted,1);assert.equal(typeof form.requestSubmit,'function');
});
