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
  // Native showModal() makes the rest of the page inert, traps focus and handles Escape.
  dialog.addEventListener('close', () => opener?.focus());
}
