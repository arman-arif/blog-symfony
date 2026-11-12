export default function mediaPicker(dialog) {
  let target;
  let selected;
  const buttons = [...dialog.querySelectorAll('[data-media-select]')];
  const insert = dialog.querySelector('[data-media-insert]');
  const query = dialog.querySelector('[data-media-query]');
  document.querySelectorAll('[data-media-target]').forEach(trigger => trigger.addEventListener('click', () => {
    target = trigger.dataset.mediaTarget; selected = null; insert.disabled = true; query.value = '';
    buttons.forEach(button => { button.hidden = false; button.setAttribute('aria-pressed', 'false'); });
    dialog.querySelector('[data-media-empty]').hidden = true;
    dialog.querySelector('[data-media-selection]').textContent = 'No image selected';
  }));
  buttons.forEach(button => button.addEventListener('click', () => {
    selected = button; insert.disabled = false;
    buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    dialog.querySelector('[data-media-selection]').textContent = button.dataset.mediaName;
  }));
  query.addEventListener('input', () => {
    const value = query.value.trim().toLocaleLowerCase();
    buttons.forEach(button => { button.hidden = !`${button.dataset.mediaName} ${button.dataset.mediaAlt}`.toLocaleLowerCase().includes(value); });
    dialog.querySelector('[data-media-empty]').hidden = buttons.some(button => !button.hidden);
    if (selected?.hidden) { selected.setAttribute('aria-pressed', 'false'); selected = null; insert.disabled = true; dialog.querySelector('[data-media-selection]').textContent = 'No image selected'; }
  });
  insert.addEventListener('click', () => {
    if (!selected || !target) return;
    document.dispatchEvent(new CustomEvent('media:chosen', { detail: { target, id: selected.dataset.mediaId, url: selected.dataset.mediaUrl, alt: selected.dataset.mediaAlt, caption: selected.dataset.mediaCaption, width: Number(selected.dataset.mediaWidth), height: Number(selected.dataset.mediaHeight) } }));
    dialog.close();
  });
}
