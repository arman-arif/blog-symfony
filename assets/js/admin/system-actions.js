import { notify } from '../components/toast.js';

export default function systemActions(root) {
  root.querySelectorAll('[data-toggle-local-status]').forEach(button => {
    button.addEventListener('click', () => {
      const row = button.closest('[data-row]');
      row.dataset.status = row.dataset.status === 'active' ? 'inactive' : 'active';
      row.querySelector('[data-local-status-label]').textContent = row.dataset.status === 'active' ? 'Active' : 'Inactive';
      row.closest('[data-component="data-table"]').dispatchEvent(new CustomEvent('table:changed'));
      notify('Status changed locally. No account, consent or access was changed.');
    });
    button.disabled = false;
  });
  const exportButton = root.querySelector('[data-export-preview]');
  exportButton?.addEventListener('click', () => {
    const rows = [...root.querySelectorAll('[data-row]')].map(row => [row.dataset.email, row.dataset.status]);
    const quote = value => `"${String(value).replaceAll('"', '""')}"`;
    const csv = [['email','status'],...rows].map(row=>row.map(quote).join(',')).join('\r\n');
    document.querySelector('[data-export-content]').textContent = csv;
    const dialog = document.getElementById('export-preview-dialog');
    dialog.showModal(); dialog.addEventListener('close', () => exportButton.focus(), { once: true });
  });
  if (exportButton) exportButton.disabled = false;
}
