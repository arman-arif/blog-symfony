export default function listing(element) {
  const results = element.querySelector('[data-results]');
  const buttons = [...element.querySelectorAll('[data-view-button]')];
  buttons.forEach((button) => button.addEventListener('click', () => {
    if (!results) return;
    results.dataset.view = button.dataset.viewButton;
    buttons.forEach((control) => control.setAttribute('aria-pressed', String(control === button)));
  }));
}
