export default function dropdown(element) {
  const trigger = element.querySelector('[data-dropdown-trigger]');
  const panel = element.querySelector('[data-dropdown-panel]');
  if (!trigger || !panel) return;
  const items = () => [...panel.querySelectorAll('a[href], button:not([disabled])')];
  const setOpen = (open, focus = false) => {
    panel.hidden = !open;
    trigger.setAttribute('aria-expanded', String(open));
    if (open && focus) items()[0]?.focus();
  };
  trigger.addEventListener('click', () => setOpen(panel.hidden));
  element.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { setOpen(false); trigger.focus(); }
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault(); setOpen(true);
      const options = items();
      let index = options.indexOf(document.activeElement);
      index = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowUp' ? -1 : 1) + options.length) % options.length;
      options[index]?.focus();
    }
  });
  document.addEventListener('click', (event) => { if (!element.contains(event.target)) setOpen(false); });
  element.addEventListener('focusout', (event) => { if (!element.contains(event.relatedTarget)) setOpen(false); });
}
