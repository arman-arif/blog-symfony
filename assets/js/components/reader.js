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
    const key = `fieldnotes-preview-${article.dataset.articleId}-${button.dataset.engagement}`;
    const count = button.querySelector('[data-count]'); const base = Number(count?.textContent || 0);
    let active = false;
    try { active = localStorage.getItem(key) === '1'; } catch { /* Optional local preview preference. */ }
    const apply = () => { button.setAttribute('aria-pressed', String(active)); if (count) count.textContent = String(base + Number(active)); };
    apply(); button.addEventListener('click', () => {
      active = !active; apply();
      try { localStorage.setItem(key, active ? '1' : '0'); } catch { /* Keep the visual state. */ }
      notify(`${button.dataset.engagement === 'like' ? 'Like' : 'Bookmark'} ${active ? 'added' : 'removed'} in this browser preview only.`);
    });
  });
  const form = article.querySelector('[data-comment-form]');
  if (!form) return;
  const context = form.querySelector('[data-reply-context]');
  const resetReply = () => { form.dataset.parent = ''; context.hidden = true; };
  article.querySelectorAll('[data-reply-to]').forEach(button => button.addEventListener('click', () => {
    form.dataset.parent = button.dataset.replyTo;
    context.querySelector('[data-reply-name]').textContent = button.dataset.replyName;
    context.hidden = false; form.querySelector('textarea').focus();
  }));
  form.querySelector('[data-cancel-reply]').addEventListener('click', resetReply);
  form.addEventListener('preview:valid', () => {
    const item = document.createElement('div'); item.className = 'card p-5';
    const name = document.createElement('p'); name.className = 'text-sm font-semibold'; name.textContent = `${form.elements.namedItem('comment-name').value} · Preview only`;
    const body = document.createElement('p'); body.className = 'mt-3 whitespace-pre-wrap text-sm text-muted'; body.textContent = form.elements.namedItem('comment-content').value;
    item.append(name, body);
    const parent = article.querySelector(`[data-comment-id="${form.dataset.parent || 'none'}"] [data-replies]`);
    (parent || article.querySelector('[data-comments]')).append(item);
    form.reset(); resetReply();
  });
}
