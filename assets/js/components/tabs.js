export default function tabs(element) {
  const buttons = [...element.querySelectorAll('[role="tab"]')];
  const activate = (button) => {
    buttons.forEach((tab) => {
      const active = tab === button;
      tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1;
      const panel = document.getElementById(tab.getAttribute('aria-controls'));
      if (panel) panel.hidden = !active;
    });
  };
  buttons.forEach((button, index) => {
    button.addEventListener('click', () => activate(button));
    button.addEventListener('keydown', (event) => {
      if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
      activate(buttons[next]); buttons[next].focus();
    });
  });
}
