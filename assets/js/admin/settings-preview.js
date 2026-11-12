export function safeUrl(value, allowPath = false) {
  if (!value) return true;
  if (/[\s\\]/.test(value)) return false;
  if (allowPath && value.startsWith('/') && !value.startsWith('//')) return true;
  try { return ['http:', 'https:'].includes(new URL(value).protocol); }
  catch { return false; }
}

export default function settingsPreview(form) {
  const safeFields = [...form.querySelectorAll('[data-safe-url]')];
  const pathFields = [...form.querySelectorAll('[data-local-path]')];
  const confirmations = [...form.querySelectorAll('[data-password-confirm]')];
  const update = () => {
    safeFields.forEach(field => field.setCustomValidity(safeUrl(field.value, field.hasAttribute('data-allow-path')) ? '' : 'Use an HTTP(S) URL or an allowed local path.'));
    pathFields.forEach(field => field.setCustomValidity(field.value.startsWith('/') && !field.value.startsWith('//') && !/[\s\\?#]/.test(field.value) ? '' : 'Use a local path beginning with one slash, without query or fragment.'));
    confirmations.forEach(field => field.setCustomValidity(field.value === document.getElementById(field.dataset.passwordConfirm).value ? '' : 'The dummy passwords must match.'));
  };
  form.addEventListener('input', update, true);
  form.addEventListener('preview:valid', () => {
    form.querySelectorAll('[data-sensitive-preview]').forEach(field => { field.value = ''; field.removeAttribute('aria-invalid'); });
    update();
  });
  update();
}
