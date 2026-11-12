import { notify } from './toast.js';
export default function dataTable(root) {
  const body = root.querySelector('tbody');
  const rows = [...body.querySelectorAll('[data-row]')];
  const selected = new Set();
  const removed = new Set();
  const controls = [...root.querySelectorAll('[data-filter]')];
  const perPage = root.querySelector('[data-per-page]');
  const selectAll = root.querySelector('[data-select-all]');
  const filterForm = root.querySelector('[data-table-filters]');
  let page = 1;
  let sort = '';
  let direction = 'ascending';
  let state = root.dataset.tableState;
  let visible = [];
  let tab = 'all';
  const trashEnabled = root.dataset.trashEnabled === 'true';
  const tabs = [...root.querySelectorAll('[data-table-tab]')];

  function fromUrl() {
    const params = new URLSearchParams(location.search);
    controls.forEach(control => { control.value = params.get(control.dataset.filter) || ''; });
    perPage.value = ['10', '20', '50'].includes(params.get('perPage')) ? params.get('perPage') : '10';
    page = Math.max(1, Number.parseInt(params.get('page'), 10) || 1);
    sort = [...root.querySelectorAll('[data-sort]')].some(button => button.dataset.sort === params.get('sort')) ? params.get('sort') : '';
    direction = params.get('direction') === 'descending' ? 'descending' : 'ascending';
    state = params.get('state') || '';
    tab = tabs.some(button => button.dataset.tableTab === params.get('tab')) ? params.get('tab') : 'all';
  }
  function updateUrl() {
    const params = new URLSearchParams(location.search);
    controls.forEach(control => { const key = control.dataset.filter; control.value ? params.set(key, control.value) : params.delete(key); });
    perPage.value === '10' ? params.delete('perPage') : params.set('perPage', perPage.value);
    page === 1 ? params.delete('page') : params.set('page', String(page));
    sort ? params.set('sort', sort) : params.delete('sort');
    sort ? params.set('direction', direction) : params.delete('direction');
    tab === 'all' ? params.delete('tab') : params.set('tab', tab);
    history.replaceState(null, '', `${location.pathname}${params.size ? `?${params}` : ''}${location.hash}`);
  }
  function render(syncUrl = true) {
    const activeFilters = controls.filter(control => control.value);
    const records = rows.filter(row => !removed.has(row.dataset.id) && (!trashEnabled || (tab === 'trash' ? row.dataset.trash === 'true' : row.dataset.trash !== 'true')) && (['all', 'trash'].includes(tab) || row.dataset.status === tab));
    const matching = records.filter(row => activeFilters.every(control => {
      const key = control.dataset.filter;
      return key === 'q' ? row.dataset.search.toLocaleLowerCase().includes(control.value.toLocaleLowerCase().trim()) : (row.dataset[key] || '').split(' ').includes(control.value);
    }));
    if (sort) matching.sort((a, b) => {
      const av = a.dataset[sort] || ''; const bv = b.dataset[sort] || '';
      const comparison = ['views', 'updated'].includes(sort) ? Number(av) - Number(bv) : av.localeCompare(bv);
      return direction === 'ascending' ? comparison : -comparison;
    });
    const pages = Math.max(1, Math.ceil(matching.length / Number(perPage.value)));
    page = Math.min(page, pages);
    visible = matching.slice((page - 1) * Number(perPage.value), page * Number(perPage.value));
    const suspended = ['loading', 'empty', 'error'].includes(state);
    root.setAttribute('aria-busy', String(state === 'loading'));
    rows.forEach(row => {
      row.hidden = suspended || !visible.includes(row); row.querySelector('[data-select-row]').checked = selected.has(row.dataset.id);
      if (trashEnabled) { const trashed = row.dataset.trash === 'true'; row.querySelector('[data-row-remove]').hidden = trashed; row.querySelector('[data-row-restore]').hidden = !trashed; row.querySelector('[data-trash-label]').hidden = !trashed; }
    });
    matching.forEach(row => body.append(row));
    root.querySelector('[data-table-content]').hidden = suspended || matching.length === 0;
    root.querySelector('[data-table-loading]').hidden = state !== 'loading';
    root.querySelector('[data-table-error]').hidden = state !== 'error';
    root.querySelector('[data-table-empty]').hidden = state !== 'empty' && records.length !== 0;
    root.querySelector('[data-table-no-results]').hidden = suspended || records.length === 0 || matching.length !== 0;
    root.querySelector('[data-table-pagination]').hidden = suspended || matching.length === 0;
    root.querySelector('[data-page-prev]').disabled = page <= 1;
    root.querySelector('[data-page-next]').disabled = page >= pages;
    root.querySelector('[data-page-label]').textContent = `${page} / ${pages}`;
    root.querySelector('[data-table-summary]').textContent = matching.length ? `Showing ${(page - 1) * Number(perPage.value) + 1}–${Math.min(page * Number(perPage.value), matching.length)} of ${matching.length} records` : 'No matching records';
    root.querySelector('[data-bulk-bar]').hidden = selected.size === 0 || suspended;
    root.querySelector('[data-selected-count]').textContent = selected.size;
    if (trashEnabled) { root.querySelector('[data-bulk-remove]').hidden = tab === 'trash'; root.querySelector('[data-bulk-restore]').hidden = tab !== 'trash'; root.querySelector('[data-apply-bulk-status]').hidden = tab === 'trash'; root.querySelector('[data-bulk-status]').hidden = tab === 'trash'; }
    tabs.forEach(button => { if (button.dataset.tableTab === tab) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current'); });
    selectAll.checked = visible.length > 0 && visible.every(row => selected.has(row.dataset.id));
    selectAll.indeterminate = visible.some(row => selected.has(row.dataset.id)) && !selectAll.checked;
    selectAll.disabled = visible.length === 0 || suspended;
    root.querySelectorAll('[data-sort]').forEach(button => {
      button.closest('th').setAttribute('aria-sort', button.dataset.sort === sort ? direction : 'none');
      button.querySelector('[data-sort-indicator]').textContent = button.dataset.sort === sort ? (direction === 'ascending' ? '↑' : '↓') : '↕';
    });
    if (syncUrl) updateUrl();
  }
  function reset() {
    controls.forEach(control => { control.value = ''; }); perPage.value = '10';
    page = 1; sort = ''; tab = 'all'; selected.clear(); render();
  }
  filterForm.addEventListener('submit', event => { event.preventDefault(); page = 1; selected.clear(); render(); });
  filterForm.addEventListener('reset', event => { event.preventDefault(); reset(); });
  controls.forEach(control => control.addEventListener(control.type === 'search' ? 'input' : 'change', () => { page = 1; selected.clear(); render(); }));
  perPage.addEventListener('change', () => { page = 1; selected.clear(); render(); });
  root.querySelector('[data-reset-filters]').addEventListener('click', reset);
  root.querySelector('[data-table-retry]').addEventListener('click', () => { state = ''; const params = new URLSearchParams(location.search); params.delete('state'); history.replaceState(null, '', `${location.pathname}${params.size ? `?${params}` : ''}`); render(); });
  root.querySelectorAll('[data-sort]').forEach(button => {
    button.disabled = false;
    button.addEventListener('click', () => { direction = sort === button.dataset.sort && direction === 'ascending' ? 'descending' : 'ascending'; sort = button.dataset.sort; page = 1; render(); });
  });
  rows.forEach(row => {
    const checkbox = row.querySelector('[data-select-row]'); checkbox.disabled = false;
    checkbox.addEventListener('change', () => { checkbox.checked ? selected.add(row.dataset.id) : selected.delete(row.dataset.id); render(false); });
    row.querySelector('[data-row-remove]')?.addEventListener('preview:confirmed', () => { trashEnabled ? row.dataset.trash = 'true' : removed.add(row.dataset.id); selected.delete(row.dataset.id); render(); root.querySelector('[data-filter="q"]').focus(); });
    row.querySelector('[data-row-restore]')?.addEventListener('click', () => { row.dataset.trash = 'false'; selected.delete(row.dataset.id); render(); notify('Restored locally. No server data was changed.'); root.querySelector('[data-filter="q"]').focus(); });
  });
  selectAll.addEventListener('change', () => { visible.forEach(row => selectAll.checked ? selected.add(row.dataset.id) : selected.delete(row.dataset.id)); render(false); });
  root.querySelector('[data-clear-selection]').addEventListener('click', () => { selected.clear(); render(false); });
  root.querySelector('[data-bulk-remove]').addEventListener('preview:confirmed', () => { selected.forEach(id => { const row = rows.find(item => item.dataset.id === id); trashEnabled ? row.dataset.trash = 'true' : removed.add(id); }); selected.clear(); render(); root.querySelector('[data-filter="q"]').focus(); });
  root.querySelector('[data-bulk-restore]')?.addEventListener('click', () => { rows.filter(row => selected.has(row.dataset.id)).forEach(row => { row.dataset.trash = 'false'; }); selected.clear(); render(); notify('Selected rows restored locally. No server data was changed.'); });
  root.querySelector('[data-apply-bulk-status]')?.addEventListener('click', () => {
    const value = root.querySelector('[data-bulk-status]').value;
    rows.filter(row => selected.has(row.dataset.id)).forEach(row => { row.dataset.status = value; const badge = row.querySelector('[data-status-badge]'); badge.textContent = value.replaceAll('_', ' '); badge.className = `badge ${value === 'published' ? 'badge-success' : (value === 'pending_review' ? 'badge-warning' : 'badge-neutral')}`; });
    selected.clear(); render(); notify('Status changed locally. Nothing was published or persisted.');
  });
  tabs.forEach(button => button.addEventListener('click', event => { event.preventDefault(); tab = button.dataset.tableTab; controls.forEach(control => { if (control.dataset.filter === 'status') control.value = ''; }); page = 1; selected.clear(); render(); }));
  root.querySelector('[data-page-prev]').addEventListener('click', () => { page--; selected.clear(); render(); });
  root.querySelector('[data-page-next]').addEventListener('click', () => { page++; selected.clear(); render(); });
  window.addEventListener('popstate', () => { fromUrl(); selected.clear(); render(false); });
  fromUrl(); render();
}
