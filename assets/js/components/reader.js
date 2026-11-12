import { notify } from './toast.js';

export default function reader(article) {
  const content = article.querySelector('[data-article-content]');
  const toc = article.querySelector('[data-toc]');
  const progress = article.querySelector('[data-reading-progress]');
  const headings = [...content.querySelectorAll('h2, h3')];
  headings.forEach((heading, index) => {
    if (!heading.id) heading.id = `${heading.textContent.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}-${index}`;
    const link = document.createElement('a'); link.href = `#${heading.id}`; link.textContent = heading.textContent;
    link.className = `block border-l-2 border-line py-2 text-sm text-muted hover:text-brand ${heading.tagName === 'H3' ? 'pl-6' : 'pl-4'}`;
    toc.append(link);
  });
  const update = () => {
    const box = content.getBoundingClientRect();
    const amount = Math.max(0, Math.min(1, (innerHeight * .25 - box.top) / Math.max(1, box.height - innerHeight * .5)));
    if (progress) progress.style.transform = `scaleX(${amount})`;
    const active = [...headings].reverse().find(heading => heading.getBoundingClientRect().top < 130) || headings[0];
    [...toc.children].forEach(link => {
      const selected = link.hash === `#${active?.id}`;
      if (selected) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current');
      link.classList.toggle('text-brand', selected); link.classList.toggle('border-brand', selected);
    });
  };
  let pending = false;
  document.addEventListener('scroll', () => {
    if (pending) return; pending = true;
    requestAnimationFrame(() => { update(); pending = false; });
  }, { passive: true });
  window.addEventListener('resize', update); update();
  article.querySelector('[data-copy-link]')?.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(location.href.split('#')[0]); notify('Article link copied.'); }
    catch { notify('Copy is unavailable. You can copy the address from your browser.', 'error'); }
  });

  article.querySelectorAll('[data-engagement]').forEach(button => {
    const endpoint = button.dataset.engagementUrl;
    const token = button.dataset.engagementToken;
    if (!endpoint || !token) return;
    const count = button.querySelector('[data-count]');
    button.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': token, Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));
        if (401 === response.status) {
          notify(data.error || 'Sign in to use this feature.');
          if (data.loginUrl) {
            const target = encodeURIComponent(location.pathname);
            setTimeout(() => { location.href = `${data.loginUrl}?_target_path=${target}`; }, 700);
          }
          return;
        }
        if (!response.ok) throw new Error(data.error || `Request failed (${response.status})`);
        button.setAttribute('aria-pressed', String(Boolean(data.active)));
        if (count && 'number' === typeof data.count) count.textContent = String(data.count);
        const label = button.dataset.engagement === 'like' ? 'Like' : 'Bookmark';
        notify(`${label} ${data.active ? 'saved' : 'removed'}.`);
      } catch (error) {
        notify(error.message || 'That action could not be completed.', 'error');
      } finally {
        button.disabled = false;
      }
    });
  });

  const form = article.querySelector('[data-comment-form]');
  if (!form) return;
  const parentInput = form.querySelector('[data-parent-input]');
  const context = form.querySelector('[data-reply-context]');
  const resetReply = () => { if (parentInput) parentInput.value = ''; if (context) context.hidden = true; };
  article.querySelectorAll('[data-reply-to]').forEach(button => button.addEventListener('click', () => {
    if (parentInput) parentInput.value = button.dataset.replyTo;
    if (context) { context.querySelector('[data-reply-name]').textContent = button.dataset.replyName; context.hidden = false; }
    form.querySelector('textarea')?.focus();
  }));
  form.querySelector('[data-cancel-reply]')?.addEventListener('click', resetReply);
}
