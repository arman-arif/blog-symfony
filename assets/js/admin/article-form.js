import { notify } from '../components/toast.js';
import { copyPreview, httpUrl } from './preview-dom.js';

export default function articleForm(form) {
  const title = form.querySelector('#article-title');
  const slug = form.querySelector('#article-slug');
  const status = form.querySelector('#article-status');
  const schedule = form.querySelector('#article-scheduled-at');
  const seoTitle = form.querySelector('#article-seo-title');
  const description = form.querySelector('#article-seo-description');
  const canonical = form.querySelector('#article-canonical');
  let autoSlug = form.dataset.newArticle === 'true';
  let timer;
  function regenerate() { slug.value = title.value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function updateSeo() {
    form.querySelector('[data-seo-title-count]').textContent = `${seoTitle.value.length} / 70 characters`;
    form.querySelector('[data-seo-description-count]').textContent = `${description.value.length} / 160 characters`;
    form.querySelector('[data-snippet-title]').textContent = seoTitle.value || title.value || 'Your article title';
    form.querySelector('[data-snippet-description]').textContent = description.value || form.querySelector('#article-excerpt').value || 'A short description of your story will appear here.';
    form.querySelector('[data-snippet-url]').textContent = `fieldnotes.example/blog/${slug.value}`;
    canonical.setCustomValidity(canonical.value && !httpUrl(canonical.value) ? 'Enter an absolute HTTP(S) URL.' : '');
  }
  title.addEventListener('input', () => { if (autoSlug) regenerate(); updateSeo(); });
  slug.addEventListener('input', () => { autoSlug = false; updateSeo(); });
  form.querySelector('[data-regenerate-slug]').addEventListener('click', () => { regenerate(); autoSlug = true; updateSeo(); localChange(); });
  function updateSchedule() { const scheduled = status.value === 'scheduled'; form.querySelector('[data-schedule-panel]').hidden = !scheduled; schedule.required = scheduled; schedule.disabled = !scheduled; }
  status.addEventListener('change', updateSchedule);
  form.querySelector('[data-remove-featured]').addEventListener('click', () => {
    form.querySelector('[data-featured-image]').hidden = true; form.querySelector('[data-featured-id]').value = '';
    form.querySelector('[data-featured-caption]').textContent = 'No image selected'; localChange();
  });
  document.addEventListener('media:chosen', event => {
    const item = event.detail;
    if (!['featured', 'og'].includes(item.target)) return;
    const image = form.querySelector(item.target === 'featured' ? '[data-featured-image]' : '[data-og-image]');
    image.src = item.url; image.alt = item.alt; image.hidden = false;
    if (item.target === 'featured') { form.querySelector('[data-featured-id]').value = item.id; form.querySelector('[data-featured-caption]').textContent = item.caption; }
    else form.querySelector('[data-og-label]').textContent = item.alt;
    localChange();
  });
  const tagsInput = form.querySelector('[data-tags-input]');
  const tagsList = form.querySelector('[data-tags-list]');
  function addTag() {
    const value = tagsInput.value.trim().replace(/,$/, '');
    if (!value) return;
    if ([...tagsList.querySelectorAll('[data-tag]')].some(tag => tag.dataset.tag.toLocaleLowerCase() === value.toLocaleLowerCase())) { notify('This topic is already selected.'); return; }
    const chip = document.createElement('span'); chip.className = 'badge badge-neutral'; chip.dataset.tag = value;
    chip.append(document.createTextNode(value));
    const input = document.createElement('input'); input.type = 'hidden'; input.name = 'tags[]'; input.value = value;
    const button = document.createElement('button'); button.type = 'button'; button.dataset.removeTag = ''; button.setAttribute('aria-label', `Remove ${value}`); button.textContent = '×';
    chip.append(input, button); tagsList.append(chip); tagsInput.value = ''; localChange();
  }
  form.querySelector('[data-add-tag]').addEventListener('click', addTag);
  tagsInput.addEventListener('keydown', event => { if (['Enter', ','].includes(event.key)) { event.preventDefault(); addTag(); } });
  tagsList.addEventListener('click', event => { const button = event.target.closest('[data-remove-tag]'); if (button) { button.closest('[data-tag]').remove(); tagsInput.focus(); localChange(); } });
  function localChange() {
    const indicator = form.querySelector('[data-autosave-status]'); indicator.textContent = 'Updating the local preview…';
    clearTimeout(timer); timer = setTimeout(() => { indicator.textContent = 'Local preview up to date · nothing persisted'; }, 600);
  }
  form.addEventListener('input', () => { updateSeo(); localChange(); });
  form.addEventListener('change', localChange);
  form.addEventListener('editor:changed', localChange);
  form.querySelector('[data-live-preview]').addEventListener('click', () => {
    const dialog = document.getElementById('article-live-preview');
    dialog.querySelector('[data-live-title]').textContent = title.value || 'Untitled story';
    dialog.querySelector('[data-live-subtitle]').textContent = form.querySelector('#article-subtitle').value;
    const image = form.querySelector('[data-featured-image]'); const target = dialog.querySelector('[data-live-image]');
    target.hidden = image.hidden; if (!image.hidden) { target.src = image.src; target.alt = image.alt; }
    const source = form.querySelector('.ql-editor') || form.querySelector('[data-editor-surface]');
    copyPreview(source, dialog.querySelector('[data-live-content]')); dialog.showModal();
  });
  document.getElementById('article-live-preview').addEventListener('close', () => form.querySelector('[data-live-preview]').focus());
  // Validation attaches asynchronously; schedule controls remain intentionally disabled when not scheduled.
  form.addEventListener('preview:valid', () => { updateSchedule(); form.querySelector('[data-autosave-status]').textContent = 'Validated locally · nothing persisted'; });
  updateSeo(); updateSchedule();
}
