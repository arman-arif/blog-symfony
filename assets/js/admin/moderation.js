import { notify } from '../components/toast.js';

export default function moderation(root) {
  let current; const dialog = document.getElementById('comment-reply-dialog');
  root.querySelectorAll('[data-moderate]').forEach(button => {
    button.addEventListener('click', () => {
      const row = button.closest('[data-row]'); row.dataset.status = button.dataset.moderate;
      const badge = row.querySelector('[data-moderation-status]'); badge.textContent = button.dataset.moderate;
      badge.className = `badge ${row.dataset.status === 'approved' ? 'badge-success' : 'badge-neutral'}`;
      row.closest('[data-component="data-table"]').dispatchEvent(new CustomEvent('table:changed')); notify('Moderation changed locally. No server record or notification was changed.');
    }); button.disabled = false;
  });
  root.querySelectorAll('[data-comment-reply]').forEach(button => {
    button.addEventListener('click', () => { current = button.closest('[data-row]'); dialog.querySelector('[data-reply-context]').textContent = `Local reply to ${current.dataset.name}`; dialog.querySelector('textarea').value = ''; }); button.disabled = false;
  });
  dialog.querySelector('form').addEventListener('preview:valid', () => {
    if (!current) return;
    const reply = document.createElement('p'); reply.className = 'mt-3 rounded-control bg-canvas p-3 text-sm'; reply.textContent = `Your local reply: ${dialog.querySelector('textarea').value}`;
    current.querySelector('[data-local-replies]').append(reply); dialog.close();
  });
}
