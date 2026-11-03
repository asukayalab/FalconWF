document.querySelectorAll('[data-fwf-release-choice]').forEach(group => {
  const channel = group.querySelector('[data-fwf-release-channel]');
  const row = group.querySelector('[data-fwf-release-tag]');
  const input = row.querySelector('input');
  const sync = () => {
    const manual = channel.value === 'manual';
    row.hidden = !manual;
    input.disabled = !manual;
    input.required = manual;
  };
  channel.addEventListener('change', sync);
  sync();
});
