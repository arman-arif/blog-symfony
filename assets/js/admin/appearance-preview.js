import { safeUrl } from './settings-preview.js';

export default function appearancePreview(form) {
  const sample = form.querySelector('[data-appearance-sample]');
  const list = form.querySelector('[data-appearance-sections]');
  const update = () => {
    const sections = [...list.children];
    sections.forEach((section, index) => {
      section.querySelector('[data-section-move="up"]').disabled = index === 0;
      section.querySelector('[data-section-move="down"]').disabled = index === sections.length - 1;
    });
    const names = sections.filter(section => section.querySelector('input').checked).map(section => section.querySelector('input').value);
    const output = sample.querySelector('[data-sample-sections]');
    output.replaceChildren(...names.map(name => { const item = document.createElement('li'); item.textContent = name; return item; }));
    form.querySelector('[data-section-status]').textContent = `${names.length} visible sections · local order: ${names.join(', ')}`;
    const dark = form.querySelector('#appearance-theme').value === 'dark' || (form.querySelector('#appearance-theme').value === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    sample.style.backgroundColor = dark ? '#131b17' : '#ffffff';
    sample.style.color = dark ? '#eff3eb' : '#1f2a25';
    sample.querySelector('[data-sample-brand]').style.color = form.querySelector('#appearance-primary').value;
    sample.querySelector('[data-sample-accent]').style.color = form.querySelector('#appearance-secondary').value;
    const luminance = hex => {
      const rgb = hex.match(/[0-9a-f]{2}/gi).map(pair => parseInt(pair, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
      return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
    };
    const background = luminance(dark ? '#131b17' : '#ffffff');
    const ratios = ['primary', 'secondary'].map(key => { const color = luminance(form.querySelector(`#appearance-${key}`).value); return (Math.max(color, background) + .05) / (Math.min(color, background) + .05); });
    form.querySelector('[data-color-contrast]').textContent = `Sample text contrast: primary ${ratios[0].toFixed(2)}:1, secondary ${ratios[1].toFixed(2)}:1. ${ratios.some(ratio => ratio < 4.5) ? 'Warning: one or more colors do not meet AA normal-text contrast. Choose a clearer color before production.' : 'Both meet AA normal-text contrast.'}`;
    sample.querySelector('[data-sample-layout]').textContent = `${form.querySelector('#appearance-header').selectedOptions[0].text} header / ${dark ? 'Dark' : 'Light'} sample`;
    sample.querySelector('[data-sample-header]').style.paddingBottom = form.querySelector('#appearance-header').value === 'compact' ? '.5rem' : '1.5rem';
    sample.querySelector('[data-sample-sidebar]').textContent = form.querySelector('#appearance-sidebar').selectedOptions[0].text;
    sample.querySelector('[data-sample-sidebar]').hidden = form.querySelector('#appearance-sidebar').value === 'none';
    sample.querySelector('[data-sample-footer]').textContent = `${form.querySelector('#appearance-footer').selectedOptions[0].text}: ${form.querySelector('#appearance-footerText').value}`;
    form.querySelectorAll('[data-safe-url]').forEach(field => field.setCustomValidity(safeUrl(field.value) ? '' : 'Use an HTTP(S) social URL.'));
  };
  list.querySelectorAll('[data-section-move]').forEach(button => button.addEventListener('click', () => {
    const item = button.closest('[data-appearance-section]');
    const sibling = button.dataset.sectionMove === 'up' ? item.previousElementSibling : item.nextElementSibling;
    if (sibling) button.dataset.sectionMove === 'up' ? list.insertBefore(item, sibling) : list.insertBefore(sibling, item);
    update();
    // Boundary controls become disabled after a move; keep keyboard focus in the item.
    (button.disabled ? item.querySelector('[data-section-move]:not(:disabled)') : button)?.focus();
  }));
  form.addEventListener('input', update, true);
  form.addEventListener('change', update);
  update();
}
