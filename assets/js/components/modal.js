export default function modal(dialog) {
  if (!(dialog instanceof HTMLDialogElement)) return;
  let opener;
  document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
    if (trigger.dataset.dialogOpen !== dialog.id) return;
    trigger.addEventListener('click', () => { opener = trigger; dialog.showModal(); });
  });
  dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', (event) => {
    const box = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
  });
  // showModal() makes background content inert and handles Escape. Explicit
  // boundary wrapping also prevents Tab from moving into browser chrome.
  dialog.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const controls = [...dialog.querySelectorAll('a[href], button, input, select, textarea, summary, [tabindex], [contenteditable="true"]')]
      .filter(control => !control.matches(':disabled') && control.tabIndex >= 0 && control.getClientRects().length);
    const first = controls[0], last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  dialog.addEventListener('close', () => opener?.focus());
}
