export default function autosave(form) {
  const url = form.dataset.autosaveUrl;
  const token = form.dataset.autosaveToken;
  const status = form.querySelector('[data-autosave-status]');
  const title = form.querySelector('#article-title');
  const output = form.querySelector('[data-editor-output]');
  if (!url || !token || !status || !title || !output) return;

  let timer;
  let inFlight = false;
  let lastPayload = JSON.stringify({ title: title.value.trim(), content: output.value.trim() });

  function setStatus(message, state = '') {
    status.textContent = message;
    status.dataset.autosaveState = state;
  }

  async function save() {
    const content = output.value.trim();
    if (inFlight || content.length < 20 || title.value.trim().length < 5) return;
    const payload = JSON.stringify({ title: title.value.trim(), content });
    if (payload === lastPayload) { setStatus('All changes saved. Manual save creates a permanent revision.', 'ready'); return; }
    inFlight = true;
    setStatus('Saving draft…', 'saving');
    try {
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        body: payload,
      });
      if (!response.ok) throw new Error(`Autosave failed (${response.status})`);
      const data = await response.json();
      lastPayload = payload;
      setStatus(`Draft saved at ${new Date(data.savedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, 'saved');
    } catch (error) {
      setStatus(error.message || 'Autosave failed. Save manually to keep your changes.', 'error');
    } finally {
      inFlight = false;
    }
  }

  function schedule() {
    clearTimeout(timer);
    setStatus('Unsaved changes · autosave will run shortly', 'pending');
    timer = setTimeout(save, 4000);
  }

  form.addEventListener('editor:changed', schedule);
  form.addEventListener('input', event => { if (event.target !== output) schedule(); });
  form.addEventListener('submit', () => clearTimeout(timer));
  setStatus('Autosave is on. Manual save creates a permanent revision.', 'ready');
}
