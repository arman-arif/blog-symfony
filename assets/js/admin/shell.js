export default function shell(root) {
  const button = root.querySelector('[data-sidebar-collapse]');
  button?.addEventListener('click', () => {
    const collapsed = root.classList.toggle('is-collapsed');
    button.setAttribute('aria-expanded', String(!collapsed));
    button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
  });
  document.querySelectorAll('#admin-mobile-navigation a').forEach(link => {
    link.addEventListener('click', () => document.getElementById('admin-mobile-navigation').close());
  });
}
