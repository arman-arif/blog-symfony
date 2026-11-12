import { notify } from '../components/toast.js';

export default function mediaLibrary(root) {
  const items = [...root.querySelectorAll('[data-media-item]')]; const removed = new Set();
  const query = root.querySelector('[data-media-search]'); const type = root.querySelector('[data-media-type]'); const date = root.querySelector('[data-media-date]');
  const drawer = document.getElementById('media-detail-drawer'); let current;
  function filter() {
    items.forEach(item => { item.hidden = removed.has(item) || !`${item.dataset.name} ${item.dataset.caption} ${item.dataset.alt}`.toLowerCase().includes(query.value.trim().toLowerCase()) || (type.value && item.dataset.type !== type.value) || (date.value && item.dataset.date !== date.value); });
    const count = items.filter(item => !item.hidden).length; root.querySelector('[data-media-no-results]').hidden = count !== 0;
    root.querySelector('[data-media-summary]').textContent = `${count} of ${items.length - removed.size} local mock files`;
  }
  for (const control of [query, type, date]) control.addEventListener(control === query ? 'input' : 'change', filter);
  root.querySelectorAll('[data-media-view]').forEach(button => button.addEventListener('click', () => { root.querySelector('[data-media-results]').dataset.view = button.dataset.mediaView; root.querySelectorAll('[data-media-view]').forEach(item => item.setAttribute('aria-pressed', String(item === button))); }));
  items.forEach(item => item.querySelector('[data-media-details]').addEventListener('click', () => {
    current = item; const data = item.dataset; const image = drawer.querySelector('[data-detail-image]'); image.hidden = data.type !== 'image';
    if (!image.hidden) { image.src = data.url; image.alt = data.alt; }
    drawer.querySelector('[data-detail-name]').textContent = data.name;
    drawer.querySelector('[data-detail-meta]').textContent = `${data.mime} · ${data.size} bytes${data.type === 'image' ? ` · ${data.width} × ${data.height}` : ''} · Uploaded by ${data.uploader}`;
    drawer.querySelector('#media-alt').value = data.alt; drawer.querySelector('#media-caption').value = data.caption;
    drawer.querySelector('[data-detail-download]').href = data.url;
  }));
  drawer.querySelector('#media-detail-form').addEventListener('preview:valid', () => {
    if (!current) return;
    current.dataset.alt = drawer.querySelector('#media-alt').value; current.dataset.caption = drawer.querySelector('#media-caption').value;
    const image = current.querySelector('img'); if (image) image.alt = current.dataset.alt;
    drawer.close(); filter();
  });
  drawer.querySelector('[data-media-delete]').addEventListener('preview:confirmed', () => { if (current) { removed.add(current); drawer.close(); filter(); query.focus(); } });
  const upload = root.querySelector('#media-upload-form'); const input = upload.querySelector('input[type=file]'); const error = root.querySelector('[data-upload-error]');
  let files = []; let timer;
  function validateFiles(next) {
    files = [...next]; const allowed = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain']);
    const invalid = files.find(file => !allowed.has(file.type) || file.size > 10 * 1024 * 1024);
    error.textContent = invalid ? `${invalid.name}: unsupported type or larger than 10 MB.` : (files.length ? '' : 'Choose at least one file.');
    error.hidden = !error.textContent; input.setCustomValidity(error.textContent); return error.hidden;
  }
  input.addEventListener('change', () => validateFiles(input.files));
  const zone = root.querySelector('[data-upload-zone]'); zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('border-brand'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('border-brand'));
  zone.addEventListener('drop', event => { event.preventDefault(); zone.classList.remove('border-brand'); validateFiles(event.dataTransfer.files); input.focus(); });
  upload.addEventListener('submit', event => { if (!validateFiles(files)) { event.preventDefault(); event.stopImmediatePropagation(); input.focus(); } }, true);
  upload.addEventListener('preview:valid', () => {
    clearInterval(timer); root.querySelector('[data-upload-progress]').hidden = false; const progress = root.querySelector('progress'); progress.value = 0;
    const list = root.querySelector('[data-upload-files]'); list.replaceChildren(...files.map(file => { const li = document.createElement('li'); li.textContent = `${file.name} · ${file.size} bytes · local only`; return li; }));
    const status = root.querySelector('[data-upload-status]'); status.textContent = 'Simulating progress · no network request';
    timer = setInterval(() => { progress.value += 20; if (progress.value === 100) { clearInterval(timer); status.textContent = 'Simulation complete. Files were not uploaded or added to the library.'; notify('Upload simulation complete. No files left your device.'); } }, 160);
  });
  filter();
}
