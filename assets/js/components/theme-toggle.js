function applyTheme(enabled) {
  document.documentElement.classList.toggle('dark', enabled);
  document.querySelectorAll('[data-component~="theme"]').forEach((control) => control.setAttribute('aria-pressed', String(enabled)));
}

export function initializeTheme() {
  let saved;
  try { saved = localStorage.getItem('fieldnotes-theme'); } catch { /* Storage can be disabled. */ }
  const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  applyTheme(dark);
}

export default function themeToggle(button) {
  button.setAttribute('aria-pressed', String(document.documentElement.classList.contains('dark')));
  button.addEventListener('click', () => {
    const enabled = !document.documentElement.classList.contains('dark'); applyTheme(enabled);
    try { localStorage.setItem('fieldnotes-theme', enabled ? 'dark' : 'light'); } catch { /* Theme still works without storage. */ }
  });
}
