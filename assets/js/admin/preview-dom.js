// Copy only presentational editor nodes. Never inject user-authored HTML strings.
const tags = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'STRONG', 'EM', 'U', 'S', 'OL', 'UL', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE', 'DIV', 'SPAN', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'FIGURE', 'FIGCAPTION', 'IMG', 'A', 'HR', 'BR']);
export function httpUrl(value) {
  try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url : null; } catch { return null; }
}
export function localImage(value) {
  try { const url = new URL(value, location.href); return url.origin === location.origin && url.pathname.startsWith('/assets/') ? url.href : null; } catch { return null; }
}
export function videoUrl(value) {
  const url = httpUrl(value);
  if (!url || url.protocol !== 'https:') return null;
  if (['www.youtube.com', 'youtube.com'].includes(url.hostname) && url.pathname === '/watch' && /^[\w-]{11}$/.test(url.searchParams.get('v') || '')) return `https://www.youtube-nocookie.com/embed/${url.searchParams.get('v')}`;
  if (url.hostname === 'youtu.be' && /^\/[\w-]{11}$/.test(url.pathname)) return `https://www.youtube-nocookie.com/embed${url.pathname}`;
  if (['vimeo.com', 'www.vimeo.com'].includes(url.hostname) && /^\/\d+$/.test(url.pathname)) return `https://player.vimeo.com/video${url.pathname}`;
  return null;
}
export function copyPreview(source, target) {
  function copy(node) {
    if (node.nodeType === Node.TEXT_NODE) return document.createTextNode(node.textContent);
    if (node.nodeType !== Node.ELEMENT_NODE || !tags.has(node.tagName)) return document.createDocumentFragment();
    if (node.tagName === 'IMG' && !localImage(node.getAttribute('src'))) return document.createDocumentFragment();
    if (node.classList.contains('ql-code-block-container')) {
      const pre = document.createElement('pre'); const code = document.createElement('code');
      code.textContent = [...node.children].map(line => line.textContent).join('\n'); pre.append(code); return pre;
    }
    if (node.tagName === 'OL' && node.querySelector('li[data-list]')) {
      const fragment = document.createDocumentFragment(); let list; let previous;
      for (const item of node.children) {
        const type = item.dataset.list === 'bullet' ? 'ul' : 'ol';
        if (previous !== type) { list = document.createElement(type); fragment.append(list); previous = type; }
        list.append(copy(item));
      }
      return fragment;
    }
    if (node.classList.contains('ql-ui')) return document.createDocumentFragment();
    const result = document.createElement(node.tagName.toLowerCase());
    if (node.tagName === 'A') {
      const url = httpUrl(node.getAttribute('href'));
      if (url) { result.href = url.href; result.rel = 'noopener noreferrer'; }
    }
    if (node.tagName === 'IMG') {
      result.src = localImage(node.getAttribute('src')); result.alt = node.getAttribute('alt') || ''; result.loading = 'lazy';
      for (const attr of ['width', 'height']) if (/^\d+$/.test(node.getAttribute(attr) || '')) result.setAttribute(attr, node.getAttribute(attr));
    }
    for (const name of ['ql-align-center', 'ql-align-right', 'ql-align-justify']) if (node.classList.contains(name)) result.classList.add(name);
    for (const child of node.childNodes) result.append(copy(child));
    return result;
  }
  target.replaceChildren(...[...source.childNodes].map(copy));
}
