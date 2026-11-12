import { initializeTheme } from '../components/theme-toggle.js';

initializeTheme();

const loaders = {
  dropdown: () => import('../components/dropdown.js'),
  modal: () => import('../components/modal.js'),
  drawer: () => import('../components/modal.js'),
  tabs: () => import('../components/tabs.js'),
  toast: () => import('../components/toast.js'),
  confirm: () => import('../components/confirm.js'),
  theme: () => import('../components/theme-toggle.js'),
  validate: () => import('../components/form-validate.js'),
  listing: () => import('../components/listing.js'),
  reader: () => import('../components/reader.js'),
  consent: () => import('../components/consent.js'),
  'admin-shell': () => import('../admin/shell.js'),
  'data-table': () => import('../components/data-table.js'),
  charts: () => import('../admin/charts.js'),
  editor: () => import('../admin/editor.js'),
  'article-form': () => import('../admin/article-form.js'),
  'media-picker': () => import('../admin/media-picker.js'),
  'content-form': () => import('../admin/content-form.js'),
  'media-library': () => import('../admin/media-library.js'),
  moderation: () => import('../admin/moderation.js'),
  'menu-builder': () => import('../admin/menu-builder.js'),
  'ad-form': () => import('../admin/ad-form.js'),
  'system-actions': () => import('../admin/system-actions.js'),
  'permission-matrix': () => import('../admin/permission-matrix.js'),
  'campaign-form': () => import('../admin/campaign-form.js'),
  'settings-preview': () => import('../admin/settings-preview.js'),
  'appearance-preview': () => import('../admin/appearance-preview.js'),
  'analytics-preview': () => import('../admin/analytics-preview.js'),
  'redirect-preview': () => import('../admin/redirect-preview.js'),
  'activity-preview': () => import('../admin/activity-preview.js'),
};
const initialized = new WeakMap();
export async function enhance(root) {
  const nodes = [...root.querySelectorAll('[data-component]')];
  if (root.matches?.('[data-component]')) nodes.unshift(root);
  await Promise.all(nodes.map(async (element) => {
    const ready = initialized.get(element) || new Set();
    initialized.set(element, ready);
    for (const name of element.dataset.component.split(/\s+/)) {
      if (ready.has(name) || !loaders[name]) continue;
      ready.add(name);
      try { (await loaders[name]()).default(element); }
      catch (error) { ready.delete(name); console.error(`Could not initialize ${name}`, error); }
    }
  }));
}
