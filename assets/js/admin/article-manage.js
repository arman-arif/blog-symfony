export default function articleManage(form) {
  const title = form.querySelector('#article-title');
  const slug = form.querySelector('#article-slug');
  const status = form.querySelector('#article-status');
  const schedule = form.querySelector('#article-scheduled-at');
  const seoTitle = form.querySelector('#article-seo-title');
  const description = form.querySelector('#article-seo-description');
  const excerpt = form.querySelector('#article-excerpt');
  const regenerateButton = form.querySelector('[data-regenerate-slug]');
  let autoSlug = form.dataset.newArticle === 'true';

  function regenerate() {
    if (!title || !slug) return;
    slug.value = title.value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }

  function updateSeo() {
    if (title && seoTitle) {
      const titleCount = form.querySelector('[data-seo-title-count]');
      const descriptionCount = form.querySelector('[data-seo-description-count]');
      const snippetTitle = form.querySelector('[data-snippet-title]');
      const snippetDescription = form.querySelector('[data-snippet-description]');
      const snippetUrl = form.querySelector('[data-snippet-url]');
      if (titleCount) titleCount.textContent = `${seoTitle.value.length} / 70 characters`;
      if (descriptionCount) descriptionCount.textContent = `${description.value.length} / 160 characters`;
      if (snippetTitle) snippetTitle.textContent = seoTitle.value || title.value || 'Your article title';
      if (snippetDescription) snippetDescription.textContent = description.value || (excerpt ? excerpt.value : '') || 'A short description of your story will appear here.';
      if (snippetUrl && slug) snippetUrl.textContent = `fieldnotes.example/blog/${slug.value}`;
    }
  }

  function updateSchedule() {
    if (!status || !schedule) return;
    const scheduled = status.value === 'scheduled';
    const panel = form.querySelector('[data-schedule-panel]');
    if (panel) panel.hidden = !scheduled;
    schedule.required = scheduled;
    schedule.disabled = !scheduled;
  }

  title?.addEventListener('input', () => { if (autoSlug) regenerate(); updateSeo(); });
  slug?.addEventListener('input', () => { autoSlug = false; updateSeo(); });
  regenerateButton?.addEventListener('click', regenerate);
  status?.addEventListener('change', updateSchedule);
  form.addEventListener('input', updateSeo);
  updateSeo();
  updateSchedule();
}
