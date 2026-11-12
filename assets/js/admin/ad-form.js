export default function adForm(form) {
  const type = form.querySelector('#ad-type'); const content = form.querySelector('#ad-content');
  function update() {
    const image = type.value === 'image'; form.querySelector('[data-ad-image-panel]').hidden = !image;
    form.querySelector('[data-ad-code-panel]').hidden = image; content.disabled = image; content.required = !image;
    form.querySelector('[data-ad-preview]').textContent = image ? 'Local banner selection · no advertising request' : `${type.value === 'html' ? 'HTML' : 'Network'} creative is displayed as inert text only.`;
    form.querySelector('[data-ad-code-preview]').textContent = image ? '' : content.value;
  }
  type.addEventListener('change', update); content.addEventListener('input', update); update();
}
