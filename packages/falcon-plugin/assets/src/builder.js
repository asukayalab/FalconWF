(() => {
  let next = document.querySelectorAll('.fwf-field-row').length;
  const list = document.querySelector('#fwf-field-list');
  function update(row) {
    const kind = row.querySelector('[name$="[kind]"]')?.value;
    row.querySelectorAll('[data-kind]').forEach(el => { el.hidden = el.dataset.kind !== kind; });
    const target = row.querySelector('[name$="[target]"]')?.closest('label');
    if (target) target.hidden = kind !== 'relationship';
    const label = row.querySelector('[name$="[label]"]');
    if (label) row.querySelector('.fwf-field-title').textContent = label.value || 'Field baru';
  }
  document.querySelectorAll('.fwf-field-row').forEach(update);
  document.addEventListener('input', e => { const row = e.target.closest('.fwf-field-row'); if (row) update(row); });
  document.addEventListener('click', e => {
    const button = e.target.closest('button'); if (!button) return;
    if (button.hasAttribute('data-add-field')) {
      const wrapper = document.createElement('div'); wrapper.innerHTML = document.querySelector('#fwf-field-template').innerHTML.replaceAll('__INDEX__', String(next++));
      const row = wrapper.firstElementChild; row.open = true; list.append(row); update(row); row.querySelector('input').focus();
    }
    if (button.hasAttribute('data-open-fields') || button.hasAttribute('data-close-fields')) list?.querySelectorAll('.fwf-field-row').forEach(row => { row.open = button.hasAttribute('data-open-fields'); });
    if ((button.hasAttribute('data-check-all') || button.hasAttribute('data-uncheck-all')) && !button.closest('#fwf-listing-terms')) button.closest('[data-check-scope]')?.querySelectorAll('input[type=checkbox]').forEach(input => { input.checked = button.hasAttribute('data-check-all'); });
    const row = button.closest('.fwf-field-row, .fwf-menu-row');
    if (row && button.hasAttribute('data-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
    if (row && button.hasAttribute('data-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    if (row && button.hasAttribute('data-remove')) row.remove();
    if (row && button.hasAttribute('data-duplicate')) {
      const copy = row.cloneNode(true); const index = next++;
      copy.querySelectorAll('[name]').forEach(el => { const source = row.querySelector(`[name="${el.name}"]`); if ('value' in el) el.value = source.value; if ('checked' in el) el.checked = source.checked; el.name = el.name.replace(/fields\[[^\]]+\]/, `fields[${index}]`); });
      const key = copy.querySelector('[name$="[key]"]'); key.value = ''; key.readOnly = false; delete key.dataset.manual; copy.open = true; row.after(copy); update(copy); key.focus();
    }
    if (button.hasAttribute('data-copy-listing')) {
      const status = document.querySelector('#fwf-copy-status');
      navigator.clipboard.writeText(document.querySelector('#fwf-listing-code').textContent).then(() => { status.textContent = 'Shortcode disalin.'; }).catch(() => { status.textContent = 'Pilih dan salin shortcode secara manual.'; });
    }
  });
  let dragged;
  document.addEventListener('dragstart', e => {
    if (!e.target.closest('.fwf-drag-handle')) { e.preventDefault(); return; }
    dragged = e.target.closest('.fwf-field-row,.fwf-menu-row'); if (dragged) e.dataTransfer.setData('text/plain', 'reorder');
  });
  document.addEventListener('dragover', e => { const target = e.target.closest('.fwf-field-row,.fwf-menu-row'); if (dragged && target && target.parentNode === dragged.parentNode) e.preventDefault(); });
  document.addEventListener('drop', e => { const target = e.target.closest('.fwf-field-row,.fwf-menu-row'); if (dragged && target && target !== dragged && target.parentNode === dragged.parentNode) { e.preventDefault(); const rect = target.getBoundingClientRect(); if (e.clientY > rect.top + rect.height / 2) target.after(dragged); else target.before(dragged); } dragged = null; });
  document.addEventListener('dragend', () => { dragged = null; });
  const form = document.querySelector('.fwf-builder-form');
  if (form) {
    const name = form.querySelector('[name="label"]'); const key = form.querySelector('[name="key"]'); const slug = form.querySelector('[name="slug"]');
    const clean = text => text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    if (key && !key.readOnly && name) {
      key.addEventListener('input', () => { key.dataset.manual = '1'; });
      if (slug) slug.addEventListener('input', () => { slug.dataset.manual = '1'; });
      name.addEventListener('input', () => { const value = clean(name.value); if (!key.dataset.manual) key.value = (slug ? 'fwf_' : '') + value.replaceAll('-', '_').slice(0, slug ? 16 : 30); if (slug && !slug.dataset.manual) slug.value = value.slice(0, 60); });
    }
    form.addEventListener('input', e => {
      if (e.target.name?.endsWith('[key]')) { e.target.dataset.manual = '1'; return; }
      if (!e.target.name?.endsWith('[label]')) return;
      const row = e.target.closest('.fwf-field-row'); const fieldKey = row?.querySelector('[name$="[key]"]');
      if (fieldKey && !fieldKey.readOnly && !fieldKey.dataset.manual) fieldKey.value = clean(e.target.value).replaceAll('-', '_').slice(0, 40);
    });
    form.querySelectorAll('[name$="[key]"]').forEach(key => key.addEventListener('input', () => { key.dataset.manual = '1'; }));
  }
  // Pointer handles support mouse/touch dragging without dragging form controls.
  let pointerDrag = null;
  document.addEventListener('pointerdown', e => {
    const handle = e.target.closest('.fwf-drag-handle');
    if (!handle || e.button !== 0) return;
    const row = handle.closest('.fwf-field-row,.fwf-menu-row');
    e.preventDefault(); handle.setPointerCapture(e.pointerId);
    pointerDrag = {row, handle, x:e.clientX, y:e.clientY, active:false, open:row.open};
  });
  document.addEventListener('pointermove', e => {
    const d = pointerDrag; if (!d) return;
    if (!d.active && Math.hypot(e.clientX-d.x,e.clientY-d.y)<6) return;
    d.active = true; d.row.classList.add('fwf-dragging');
    if (d.row.tagName === 'DETAILS') d.row.open = false;
    if (e.clientY < 70) window.scrollBy(0,-18);
    else if (e.clientY > window.innerHeight-70) window.scrollBy(0,18);
    const target = document.elementFromPoint(e.clientX,e.clientY)?.closest('.fwf-field-row,.fwf-menu-row');
    if (target && target !== d.row && target.parentNode === d.row.parentNode) {
      const rect = target.getBoundingClientRect();
      if (e.clientY > rect.top + rect.height/2) target.after(d.row); else target.before(d.row);
      d.handle.setPointerCapture(e.pointerId);
    }
  });
  function endPointerDrag() {
    if (!pointerDrag) return;
    pointerDrag.row.classList.remove('fwf-dragging');
    if (pointerDrag.row.tagName === 'DETAILS') pointerDrag.row.open = pointerDrag.open;
    pointerDrag = null;
  }
  document.addEventListener('pointerup',endPointerDrag);
  document.addEventListener('pointercancel',endPointerDrag);
  const generator = document.querySelector('#fwf-listing-generator');
  if (generator) {
    const type = generator.querySelector('[name="listing_type"]');
    const taxonomy = generator.querySelector('[name="listing_taxonomy"]');
    const limit = generator.querySelector('[name="listing_limit"]');
    const options = document.querySelector('#fwf-term-options');
    const termsBox = document.querySelector('#fwf-listing-terms');
    const status = document.querySelector('#fwf-term-status');
    const code = document.querySelector('#fwf-listing-code');
    const copy = document.querySelector('[data-copy-listing]');
    const more = document.querySelector('[data-more-terms]');
    let all = false, offset = 0, loading = false, failed = false, controller;
    function generate() {
      const selected = [...options.querySelectorAll('input:checked')].map(el => el.value);
      const count = Number(limit.value);
      let message = '';
      if (!Number.isInteger(count) || count < 1 || count > 50) message = 'Isi jumlah konten antara 1 dan 50.';
      else if (taxonomy.value && (loading || failed)) message = loading ? 'Memuat pilihan…' : 'Pilihan belum berhasil dimuat. Ganti taksonomi untuk mencoba lagi.';
      else if (taxonomy.value && !all && !selected.length) message = 'Pilih setidaknya satu kategori/tag, atau gunakan Check all.';
      else if (selected.length > 200 && !all) message = 'Maksimal 200 term spesifik. Gunakan Check all untuk seluruh taksonomi.';
      copy.disabled = !!message;
      code.textContent = message || `[falcon_listing type="${type.value}"${taxonomy.value ? ` taxonomy="${taxonomy.value}" term="${all ? '*' : selected.join(',')}"` : ''} limit="${count}"]`;
    }
    async function loadTerms(append = false) {
      controller?.abort(); controller = new AbortController(); const current = controller;
      if (!append) { options.replaceChildren(); offset = 0; all = false; }
      termsBox.hidden = !taxonomy.value; more.hidden = true; failed = false;
      if (!taxonomy.value) { loading = false; status.textContent = ''; generate(); return; }
      loading = true; status.textContent = 'Memuat pilihan…'; generate();
      try {
        const body = new URLSearchParams({action:'fwf_listing_terms',nonce:generator.dataset.nonce,type:type.value,taxonomy:taxonomy.value,offset:String(offset)});
        const response = await fetch(generator.dataset.url,{method:'POST',body,credentials:'same-origin',signal:current.signal});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error('terms');
        if (controller !== current) return;
        for (const term of result.data.terms) {
          const label = document.createElement('label'); label.className = 'fwf-choice';
          const input = document.createElement('input'); input.type = 'checkbox'; input.value = term.slug; input.checked = all;
          label.append(input,document.createTextNode(` ${term.name} (${term.count})`)); options.append(label);
        }
        offset = result.data.offset; more.hidden = !result.data.more;
        status.textContent = offset ? `${offset} pilihan dimuat.` : 'Belum ada kategori/tag. Tambahkan melalui dashboard WordPress.';
      } catch (error) {
        if (error.name === 'AbortError' || controller !== current) return;
        failed = true; status.textContent = 'Pilihan gagal dimuat. Ganti taksonomi untuk mencoba lagi.';
      } finally {
        if (controller === current) { loading = false; generate(); }
      }
    }
    function updateTaxonomies() {
      for (const option of taxonomy.options) {
        const available = !option.value || option.dataset.types.split('|').includes(type.value);
        option.hidden = !available; option.disabled = !available;
      }
      if (taxonomy.selectedOptions[0]?.disabled) taxonomy.value = '';
    }
    type.addEventListener('change',() => { updateTaxonomies(); loadTerms(); });
    taxonomy.addEventListener('change',() => loadTerms());
    limit.addEventListener('input',generate);
    options.addEventListener('change',() => { all = false; generate(); });
    termsBox.addEventListener('click',e => {
      const button = e.target.closest('button'); if (!button) return;
      if (button.hasAttribute('data-check-all') || button.hasAttribute('data-uncheck-all')) {
        all = button.hasAttribute('data-check-all'); options.querySelectorAll('input').forEach(input => { input.checked = all; }); generate();
      }
      if (button.hasAttribute('data-more-terms') && !loading) loadTerms(true);
    });
    updateTaxonomies(); generate();
  }
})();
