import { notify } from './toast.js';
export default function confirm(button) {
  const dialog = document.getElementById('confirmation-dialog');
  if (!dialog) return;
  button.addEventListener('click', () => {
    dialog.querySelector('[data-confirm-message]').textContent = button.dataset.confirmMessage || 'Remove this item from the preview?';
    dialog.showModal();
    const action = dialog.querySelector('[data-confirm-action]');
    const confirmed = () => {
      dialog.close();
      button.dispatchEvent(new CustomEvent('preview:confirmed', { bubbles: true }));
      notify('Preview action confirmed. No server data was changed.');
    };
    action.addEventListener('click', confirmed, { once: true });
    dialog.addEventListener('close', () => { action.removeEventListener('click', confirmed); button.focus(); }, { once: true });
  });
}
