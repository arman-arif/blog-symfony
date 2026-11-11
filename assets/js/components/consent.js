export default function consent(banner) {
  let saved;
  try { saved = localStorage.getItem('fieldnotes-consent'); } catch { /* A choice still applies for this page. */ }
  banner.hidden = ['essential', 'all'].includes(saved);
  banner.querySelectorAll('[data-consent-choice]').forEach(button => button.addEventListener('click', () => {
    const choice = button.dataset.consentChoice;
    try { localStorage.setItem('fieldnotes-consent', choice); } catch { /* No optional tracking is active. */ }
    banner.hidden = true;
    document.dispatchEvent(new CustomEvent('app:consent', { detail: { optional: choice === 'all' } }));
  }));
  document.querySelectorAll('[data-consent-settings]').forEach(button => button.addEventListener('click', () => {
    banner.hidden = false; banner.querySelector('button').focus();
  }));
}
