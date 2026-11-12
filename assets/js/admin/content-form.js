import { copyPreview } from './preview-dom.js';

export default function contentForm(form) {
  const source = form.querySelector('[data-slug-source]'); const slug = form.querySelector('[data-slug-value]');
  let automatic = slug && !slug.value;
  source?.addEventListener('input', () => { if (automatic) slug.value = source.value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); });
  slug?.addEventListener('input', () => { automatic = false; });
  form.querySelectorAll('[data-counter-for]').forEach(counter => {
    const field = document.getElementById(counter.dataset.counterFor);
    const update = () => { counter.textContent = `${field.value.length} / ${field.maxLength} characters`; };
    field.addEventListener('input', update); update();
  });
  document.addEventListener('media:chosen', event => {
    const field = [...form.querySelectorAll('[data-media-field]')].find(item => item.dataset.mediaField === event.detail.target);
    if (!field) return;
    const image = field.querySelector('[data-content-image]'); image.src = event.detail.url; image.alt = event.detail.alt; image.hidden = false;
    field.querySelector('[data-content-image-id]').value = event.detail.id;
  });
  form.querySelectorAll('[data-content-image-remove]').forEach(button => button.addEventListener('click', () => { const field = button.closest('[data-media-field]'); field.querySelector('img').hidden = true; field.querySelector('input').value = ''; }));
  form.querySelector('[data-content-preview]')?.addEventListener('click', event => {
    const dialog = document.getElementById('content-preview-dialog'); dialog.querySelector('[data-content-preview-title]').textContent = source.value || 'Untitled page';
    copyPreview(form.querySelector('.ql-editor') || form.querySelector('[data-editor-surface]'), dialog.querySelector('[data-content-preview-body]'));
    dialog.showModal(); dialog.addEventListener('close', () => event.target.focus(), { once: true });
  });
  const start = form.querySelector('[name=startsAt]'); const end = form.querySelector('[name=endsAt]');
  function checkDates() { if (end) end.setCustomValidity(start.value && end.value && end.value < start.value ? 'End date must be after the start date.' : ''); }
  start?.addEventListener('input', checkDates); end?.addEventListener('input', checkDates); checkDates();
}
