import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
test('backup total follows selection and counts settings once with database',()=>{
 const mib=1024*1024,output={textContent:''},included={hidden:false};
 const boxes=Object.entries({database:40,media:100,plugins:10,themes:5,settings:1}).map(([value,size])=>({value,checked:true,dataset:{bytes:String(size*mib)},addEventListener(event,callback){this.change=callback;}}));
 vm.runInNewContext(readFileSync('packages/falcon-plugin/assets/backup.js','utf8'),{document:{querySelector:selector=>selector==='[data-fwf-backup-total]'?output:selector==='[data-fwf-backup-jobs]'?null:included,querySelectorAll:selector=>selector.includes('components[]')?boxes:[]}});
 assert.equal(output.textContent,'155 MiB');assert.equal(included.hidden,false);
 boxes[0].checked=false;boxes[0].change();assert.equal(output.textContent,'116 MiB');assert.equal(included.hidden,true);
 boxes.forEach(box=>box.checked=false);boxes[0].change();assert.equal(output.textContent,'0 B');
 boxes[4].checked=true;boxes[4].change();assert.equal(output.textContent,'1 MiB');
});
