import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { localImage, httpUrl, videoUrl } from './preview-dom.js';

const BlockEmbed = Quill.import('blots/block/embed');
class Divider extends BlockEmbed { static blotName = 'divider'; static tagName = 'HR'; }
class Figure extends BlockEmbed {
  static blotName = 'figure'; static tagName = 'FIGURE'; static className = 'editor-figure';
  static create(value) {
    const node = super.create(); const image = document.createElement('img');
    image.src = localImage(value.url) || ''; image.alt = value.alt || ''; image.width = value.width || 1200; image.height = value.height || 800;
    const caption = document.createElement('figcaption'); caption.textContent = value.caption || '';
    node.append(image, caption); return node;
  }
  static value(node) { const image = node.querySelector('img'); return { url: image?.src, alt: image?.alt, width: image?.width, height: image?.height, caption: node.querySelector('figcaption')?.textContent }; }
}
class VideoPlaceholder extends BlockEmbed {
  static blotName = 'videoPlaceholder'; static tagName = 'FIGURE'; static className = 'editor-video';
  static create(value) {
    const node = super.create(); node.dataset.embedUrl = value;
    const label = document.createElement('p'); label.textContent = 'External video · click the link to open the provider';
    const link = document.createElement('a'); link.href = value; link.rel = 'noopener noreferrer'; link.target = '_blank'; link.textContent = value;
    node.append(label, link); return node;
  }
  static value(node) { return node.dataset.embedUrl; }
}
Quill.register(Divider); Quill.register(Figure); Quill.register(VideoPlaceholder);

export default function editor(root) {
  const surface = root.querySelector('[data-editor-surface]');
  const form = root.closest('form');
  const quill = new Quill(surface, { theme: 'snow', modules: { toolbar: false, table: true, history: { delay: 500, userOnly: true } }, placeholder: 'Start with a small observation…' });
  quill.root.setAttribute('role', 'textbox'); quill.root.setAttribute('aria-multiline', 'true');
  quill.root.setAttribute('aria-labelledby', `${root.dataset.editorId}-label`);
  quill.root.setAttribute('aria-describedby', `${root.dataset.editorId}-help`);
  // Prevent pasted remote images/video from contacting a third party without a deliberate action.
  const Delta = Quill.import('delta');
  quill.clipboard.addMatcher('IMG', node => localImage(node.getAttribute('src')) ? new Delta().insert({ image: localImage(node.getAttribute('src')) }) : new Delta());
  quill.clipboard.addMatcher('IFRAME', () => new Delta());
  let range = { index: 0, length: 0 };
  function focus() { quill.focus(); quill.setSelection(Math.min(range.index, quill.getLength() - 1), range.length, 'silent'); }
  function changed() {
    root.querySelector('[data-editor-error]').hidden = true; quill.root.setAttribute('aria-invalid', 'false');
    root.querySelector('[data-editor-output]').value = quill.getSemanticHTML();
    const count = quill.getText().trim().split(/\s+/).filter(Boolean).length;
    root.querySelector('[data-editor-count]').textContent = `${count} words · ${Math.max(1, Math.ceil(count / 200))} min read · local only`;
    form.dispatchEvent(new CustomEvent('editor:changed'));
  }
  quill.on('text-change', changed);
  quill.on('selection-change', value => { if (value) { range = value; updateToolbar(); } });
  root.querySelector('[data-editor-toolbar]').addEventListener('mousedown', event => { if (event.target.closest('button')) event.preventDefault(); });
  function updateToolbar() {
    const formats = quill.getFormat(range);
    root.querySelectorAll('[data-editor-format]').forEach(button => button.setAttribute('aria-pressed', String(!!formats[button.dataset.editorFormat])));
    root.querySelectorAll('[data-editor-list]').forEach(button => button.setAttribute('aria-pressed', String(formats.list === button.dataset.editorList)));
    root.querySelector('[data-editor-heading]').value = String(formats.header || 0);
    root.querySelector('[data-editor-align]').value = formats.align || '';
    const inTable = !!quill.getModule('table').getTable(range)[0];
    root.querySelectorAll('[data-editor-table-action]').forEach(button => { button.disabled = !inTable; });
  }
  root.querySelectorAll('[data-editor-format]').forEach(button => button.addEventListener('click', () => { focus(); const key = button.dataset.editorFormat; quill.format(key, !quill.getFormat(range)[key], 'user'); updateToolbar(); }));
  root.querySelectorAll('[data-editor-list]').forEach(button => button.addEventListener('click', () => { focus(); quill.format('list', quill.getFormat(range).list === button.dataset.editorList ? false : button.dataset.editorList, 'user'); updateToolbar(); }));
  root.querySelector('[data-editor-heading]').addEventListener('change', event => { const value = Number(event.target.value); focus(); quill.format('header', value || false, 'user'); });
  root.querySelector('[data-editor-align]').addEventListener('change', event => { const value = event.target.value; focus(); quill.format('align', value || false, 'user'); });
  root.querySelectorAll('[data-editor-table-action]').forEach(button => button.addEventListener('click', () => { focus(); quill.getModule('table')[button.dataset.editorTableAction](); updateToolbar(); }));
  function insertEmbed(type, value) {
    focus(); quill.history.cutoff(); quill.insertEmbed(range.index, type, value, 'user');
    quill.setSelection(range.index + 1, 0, 'silent'); range = quill.getSelection(); quill.history.cutoff(); updateToolbar();
  }
  root.querySelectorAll('[data-editor-action]').forEach(button => button.addEventListener('click', () => {
    const action = button.dataset.editorAction;
    if (action === 'undo' || action === 'redo') { quill.history[action](); updateToolbar(); return; }
    if (action === 'divider') { insertEmbed('divider', true); return; }
    if (action === 'image') return;
    const dialog = document.getElementById(`editor-${action}-dialog`);
    if (action === 'link') { dialog.querySelector('#editor-link-text').value = quill.getText(range.index, range.length); dialog.querySelector('#editor-link-url').value = quill.getFormat(range).link || ''; }
    dialog.showModal(); dialog.addEventListener('close', () => button.focus(), { once: true });
  }));
  const linkForm = document.getElementById('editor-link-form');
  const linkUrl = linkForm.querySelector('#editor-link-url');
  linkUrl.addEventListener('input', () => linkUrl.setCustomValidity(httpUrl(linkUrl.value) ? '' : 'Enter an absolute HTTP(S) URL.'));
  linkForm.addEventListener('preview:valid', () => {
    focus(); quill.history.cutoff(); quill.deleteText(range.index, range.length, 'user');
    const text = linkForm.querySelector('#editor-link-text').value;
    quill.insertText(range.index, text, { link: httpUrl(linkUrl.value).href }, 'user'); quill.setSelection(range.index + text.length, 0, 'silent');
    quill.history.cutoff(); document.getElementById('editor-link-dialog').close();
  });
  document.getElementById('editor-table-form').addEventListener('preview:valid', () => {
    focus(); quill.history.cutoff(); quill.getModule('table').insertTable(Number(document.getElementById('editor-table-rows').value), Number(document.getElementById('editor-table-columns').value));
    quill.history.cutoff(); updateToolbar(); document.getElementById('editor-table-dialog').close();
  });
  const videoForm = document.getElementById('editor-video-form');
  const videoInput = videoForm.querySelector('#editor-video-url');
  videoInput.addEventListener('input', () => videoInput.setCustomValidity(videoUrl(videoInput.value) ? '' : 'Use a valid HTTPS YouTube or Vimeo video URL.'));
  videoForm.addEventListener('preview:valid', () => { insertEmbed('videoPlaceholder', videoUrl(videoInput.value)); document.getElementById('editor-video-dialog').close(); });
  document.addEventListener('media:chosen', event => { if (event.detail.target === 'editor') insertEmbed('figure', event.detail); });
  form.addEventListener('submit', event => {
    const invalid = quill.getText().trim().length < 20;
    if (invalid && [...form.querySelectorAll('input, select, textarea')].some(field => field.willValidate && !field.checkValidity())) return;
    root.querySelector('[data-editor-error]').hidden = !invalid; quill.root.setAttribute('aria-invalid', String(invalid));
    if (invalid) { event.stopImmediatePropagation(); event.preventDefault(); quill.focus(); }
  }, true);
  quill.history.clear(); updateToolbar(); changed();
}
