import { enhance } from '../core/components.js';

export default function redirectPreview(root) {
  const body = root.querySelector('[data-redirect-rows]');
  const form = document.getElementById('redirect-form');
  const dialog = document.getElementById('redirect-dialog');
  const empty = root.querySelector('[data-redirect-empty]');
  const bind = button => {
    button.addEventListener('preview:confirmed', () => { button.closest('tr').remove(); empty.hidden = body.children.length !== 0; root.querySelector('[data-redirect-add]').focus(); });
    button.disabled = false;
  };
  root.querySelectorAll('[data-redirect-remove]').forEach(bind);
  root.querySelector('[data-redirect-add]').disabled = false;
  form.addEventListener('preview:valid', () => {
    const row = document.createElement('tr');
    const values = ['sourcePath', 'targetUrl', 'statusCode'].map(name => form.elements.namedItem(name).value);
    [...values, '0'].forEach(value => { const cell = document.createElement('td'); cell.textContent = value; row.append(cell); });
    const cell = document.createElement('td'); const button = document.createElement('button');
    button.type = 'button'; button.className = 'btn btn-ghost btn-sm'; button.textContent = 'Remove';
    button.dataset.component = 'confirm'; button.dataset.confirmMessage = 'Remove this local redirect row? No server redirect is changed.';
    cell.append(button); row.append(cell); body.append(row); bind(button); enhance(button);
    empty.hidden = true; dialog.close(); form.reset(); root.querySelector('[data-redirect-add]').focus();
  });
}
