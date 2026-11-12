import { notify } from './toast.js';
import { focusControl } from '../core/focus.js';

export default function validate(form) {
  const preview = form.hasAttribute('data-success-message') || null !== form.querySelector('fieldset[data-preview-fields]');
  form.noValidate = true;
  const check = (field) => {
    if (!field.willValidate) return true;
    const valid = field.checkValidity(); field.setAttribute('aria-invalid', String(!valid));
    const error = document.getElementById(`${field.id}-error`);
    if (error) { error.textContent = valid ? '' : field.validationMessage; error.hidden = valid; }
    return valid;
  };
  form.addEventListener('submit', (event) => {
    const fields = [...form.querySelectorAll('input, select, textarea')];
    const invalid = fields.filter((field) => !check(field));
    if (invalid.length) { event.preventDefault(); focusControl(invalid[0]); return; }
    if (preview) {
      event.preventDefault();
      notify(form.dataset.successMessage || 'Form validated in preview. Nothing was saved or sent.');
      form.dispatchEvent(new CustomEvent('preview:valid', { bubbles: true }));
    }
  });
  form.addEventListener('input', (event) => { if (event.target.getAttribute('aria-invalid') === 'true') check(event.target); });
  form.querySelectorAll('[data-preview-fields]').forEach(fieldset => { fieldset.disabled = false; });
}
