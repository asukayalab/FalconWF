(() => {
  const total = document.querySelector('[data-fwf-backup-total]');
  if (!total) return;
  const boxes = [...document.querySelectorAll('input[name="components[]"][data-bytes]')];
  const format = bytes => {
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    let index = 0;
    while (bytes >= 1024 && index < units.length - 1) { bytes /= 1024; index++; }
    return `${bytes.toLocaleString('id-ID', {maximumFractionDigits: 2})} ${units[index]}`;
  };
  const refresh = () => {
    const database = boxes.some(box => box.checked && box.value === 'database');
    const bytes = boxes.reduce((sum, box) => sum + (box.checked && !(database && box.value === 'settings') ? Number(box.dataset.bytes) : 0), 0);
    total.textContent = format(bytes);
    const included = document.querySelector('[data-fwf-settings-included]');
    if (included) included.hidden = !database;
  };
  boxes.forEach(box => box.addEventListener('change', refresh));
  refresh();
})();

(() => {
  const root=document.querySelector('[data-fwf-backup-jobs]');
  if (!root) return;
  const labels=JSON.parse(root.dataset.labels);
  const terminal=new Set(['done','cancelled','failed']);
  let timer;
  const poll=async()=>{
    try {
      const response=await fetch(root.dataset.url+'?action=fwf_backup_jobs&_ajax_nonce='+encodeURIComponent(root.dataset.nonce),{credentials:'same-origin',cache:'no-store'});
      if (!response.ok) return;
      const result=await response.json();if (!result.success || !Array.isArray(result.data)) return;
      let active=false,finished=false;
      for (const job of result.data) {
        const section=[...root.querySelectorAll('[data-job-id]')].find(el=>el.dataset.jobId===job.id);
        if (!section) continue;
        const status=section.querySelector('[data-job-status]'),error=section.querySelector('[data-job-error]');
        if (job.phase==='done' && status.dataset.phase!=='done') finished=true;
        status.dataset.phase=job.phase;status.textContent=(labels[job.phase]||job.phase)+(job.total?' · '+job.cursor+' / '+job.total+' berkas':'');error.textContent=job.error;
        if (!terminal.has(job.phase)) active=true;
      }
      if (finished) { location.reload();return; }
      if (active) timer=setTimeout(poll,5000);
    } catch { timer=setTimeout(poll,10000); }
  };
  if ([...root.querySelectorAll('[data-job-status]')].some(el=>!terminal.has(el.dataset.phase))) timer=setTimeout(poll,3000);
  window.addEventListener('pagehide',()=>clearTimeout(timer),{once:true});
})();

(() => {
  for (const picker of document.querySelectorAll('[data-fwf-media-picker]')) {
    const form=picker.closest('form'),prefix=picker.dataset.prefix;
    const modes=[...picker.querySelectorAll('input[type="radio"]')],ids=picker.querySelector('input[type="hidden"]');
    const summary=picker.querySelector('[data-fwf-media-summary]'),button=form.querySelector('input[type="submit"]');
    const componentName=prefix==='manual'?'components[]':'scheduled_components[]';
    const media=form.querySelector(`input[name="${componentName}"][value="media"]`),database=form.querySelector(`input[name="${componentName}"][value="database"]`);
    const fullBytes=media.dataset.bytes,sizeLabel=media.closest('label').querySelector('[data-fwf-component-size]'),fileLabel=media.closest('label').querySelector('[data-fwf-component-files]');const fullSize=sizeLabel?.textContent,fullFiles=fileLabel?.textContent;let epoch=0,frame;
    const estimate=(bytes,files)=>{if(fullBytes===undefined)return;media.dataset.bytes=String(bytes);if(sizeLabel){const units=['B','KiB','MiB','GiB','TiB'];let value=bytes,index=0;while(value>=1024&&index<units.length-1){value/=1024;index++;}sizeLabel.textContent=`${value.toLocaleString('id-ID',{maximumFractionDigits:2})} ${units[index]}`;}if(fileLabel)fileLabel.textContent=` (${files} file)`;media.dispatchEvent(new Event('change'));};
    const selected=()=>modes.find(mode=>mode.checked)?.value==='selected';
    const refresh=async()=>{
      const current=++epoch;database.disabled=selected();if(selected()){database.checked=false;media.checked=true;}
      if(!selected()) {button.disabled=false;summary.textContent='Semua uploads.';if(fullBytes!==undefined){media.dataset.bytes=fullBytes;if(sizeLabel)sizeLabel.textContent=fullSize;if(fileLabel)fileLabel.textContent=fullFiles;media.dispatchEvent(new Event('change'));}return;}
      button.disabled=true;if(!ids.value){summary.textContent='Pilih minimal satu media/report melalui Media Library.';estimate(0,0);return;}
      summary.textContent='Memeriksa file dan dependensi…';
      try {
        const response=await fetch(picker.dataset.url,{method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({action:'fwf_backup_media',_ajax_nonce:picker.dataset.nonce,ids:ids.value})});
        const result=await response.json();if(current!==epoch)return;if(!response.ok||!result.success)throw new Error(result.data?.message||'Pilihan media gagal diperiksa.');
        summary.textContent=`${result.data.attachments} attachment (termasuk dependensi), ${result.data.files} file · ${(result.data.bytes/1048576).toLocaleString('id-ID',{maximumFractionDigits:2})} MiB sebelum kompresi. ID utama: ${ids.value}`;
        estimate(result.data.bytes,result.data.files);button.disabled=false;
      }catch(error){if(current===epoch){summary.textContent=error.message||'Tidak dapat memeriksa media. Coba lagi.';estimate(0,0);}}
    };
    modes.forEach(mode=>mode.addEventListener('change',refresh));
    picker.querySelector('[data-fwf-media-choose]').addEventListener('click',()=>{
      if(!window.wp?.media){summary.textContent='Media Library belum tersedia. Muat ulang halaman.';return;}
      if(!frame){frame=wp.media({title:'Pilih media/report untuk backup',button:{text:'Gunakan pilihan ini'},multiple:true});frame.on('open',()=>{const selection=frame.state().get('selection');selection.reset();ids.value.split(',').filter(Boolean).forEach(id=>selection.add(wp.media.attachment(Number(id))));});frame.on('select',()=>{ids.value=frame.state().get('selection').map(item=>item.id).join(',');modes.find(mode=>mode.value==='selected').checked=true;refresh();});}frame.open();
    });
    refresh();
  }
})();
