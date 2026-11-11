const loaders = {
  dropdown: () => import('../components/dropdown.js'),
  modal: () => import('../components/modal.js'),
  drawer: () => import('../components/modal.js'),
  tabs: () => import('../components/tabs.js'),
  toast: () => import('../components/toast.js'),
  confirm: () => import('../components/confirm.js'),
  theme: () => import('../components/theme-toggle.js'),
  validate: () => import('../components/form-validate.js'),
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
