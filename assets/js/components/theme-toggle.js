export default function themeToggle(button) {
  let saved;
  try { saved = localStorage.getItem('fieldnotes-theme'); } catch { /* Storage can be disabled. */ }
  const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  const apply = (enabled) => {
    document.documentElement.classList.toggle('dark', enabled);
    document.querySelectorAll('[data-component~="theme"]').forEach((control) => control.setAttribute('aria-pressed', String(enabled)));
  };
  apply(dark);
  button.addEventListener('click', () => {
    const enabled = !document.documentElement.classList.contains('dark'); apply(enabled);
    try { localStorage.setItem('fieldnotes-theme', enabled ? 'dark' : 'light'); } catch { /* Theme still works without storage. */ }
  });
}
