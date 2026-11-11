export function notify(message, tone = 'success') {
  document.dispatchEvent(new CustomEvent('app:toast', { detail: { message, tone } }));
}
export default function toast(region) {
  document.addEventListener('app:toast', ({ detail }) => {
    const item = document.createElement('div');
    item.className = 'card flex items-start gap-3 p-4 text-sm';
    item.setAttribute('role', detail.tone === 'error' ? 'alert' : 'status');
    const text = document.createElement('p'); text.textContent = detail.message;
    const close = document.createElement('button'); close.type = 'button'; close.className = 'btn btn-ghost btn-sm';
    close.setAttribute('aria-label', 'Dismiss notification'); close.textContent = '×';
    close.addEventListener('click', () => item.remove());
    item.append(text, close); region.append(item);
    // Keep messages available until dismissed; never remove a keyboard-focused toast.
  });
}
