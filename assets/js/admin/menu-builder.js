import { enhance } from '../core/components.js';
import { httpUrl } from './preview-dom.js';
import { notify } from '../components/toast.js';

export default function menuBuilder(form) {
  const root = form.querySelector('[data-menu-root]'); const status = form.querySelector('[data-menu-status]'); let dragged; let nextId = 1;
  const validTarget = value => /^\/(?!\/)/.test(value) || !!httpUrl(value);
  const children = list => [...list.children].filter(item => item.matches('[data-menu-item]'));
  function depth(item) { let count = 0; for (let list = item.parentElement; list && list !== root; list = list.parentElement.closest('[data-menu-list]')) count++; return count; }
  function branchDepth(item) { return Math.max(0, ...[...item.querySelectorAll('[data-menu-item]')].map(child => depth(child) - depth(item))); }
  function update(message = 'Local menu structure updated · nothing saved') {
    const records = [];
    function walk(list, parent = null) { children(list).forEach((item, position) => {
      const label = item.querySelector('[data-menu-label]').value; const target = item.querySelector('[data-menu-target]'); target.setCustomValidity(validTarget(target.value) ? '' : 'Use a relative path or absolute HTTP(S) URL.');
      item.querySelector('[data-menu-item-caption]').textContent = label || 'Untitled link';
      records.push({id: item.dataset.id, label, type: item.querySelector('[data-menu-type]').value, target: target.value, parent: parent ? {id: parent} : null, position});
      const previous = item.previousElementSibling;
      item.querySelector('[data-menu-move=up]').disabled = !previous;
      item.querySelector('[data-menu-move=down]').disabled = !item.nextElementSibling;
      item.querySelector('[data-menu-move=indent]').disabled = !previous || depth(item) + branchDepth(item) >= 2;
      item.querySelector('[data-menu-move=outdent]').disabled = item.parentElement === root;
      walk(item.querySelector('[data-menu-list]'), item.dataset.id);
    }); }
    walk(root); form.querySelector('[data-menu-output]').value = JSON.stringify(records); status.textContent = `${records.length} links · ${message}`;
  }
  function bind(item) {
    const own = selector => [...item.querySelectorAll(selector)].filter(control => control.closest('[data-menu-item]') === item);
    own('input,select').forEach(field => field.addEventListener('input', () => update()));
    own('[data-menu-move]').forEach(button => button.addEventListener('click', () => {
      const list = item.parentElement; const previous = item.previousElementSibling;
      switch (button.dataset.menuMove) {
        case 'up': if (previous) list.insertBefore(item, previous); break;
        case 'down': if (item.nextElementSibling) list.insertBefore(item.nextElementSibling, item); break;
        case 'indent': if (previous && depth(item) + branchDepth(item) < 2) previous.querySelector('[data-menu-list]').append(item); break;
        case 'outdent': if (list !== root) { const parent = list.closest('[data-menu-item]'); parent.after(item); } break;
      }
      update(); item.querySelector('[data-menu-label]').focus();
    }));
    item.querySelector('[data-menu-remove]').addEventListener('preview:confirmed', () => { item.remove(); update(); form.querySelector('#menu-name').focus(); });
    item.addEventListener('dragstart', event => { if (event.target !== item) return; dragged = item; event.dataTransfer.setData('text/plain', item.dataset.id); event.dataTransfer.effectAllowed = 'move'; });
    item.addEventListener('dragend', () => { dragged = null; });
  }
  function bindList(list) {
    list.addEventListener('dragover', event => { event.preventDefault(); event.stopPropagation(); });
    list.addEventListener('drop', event => {
      event.preventDefault(); event.stopPropagation(); if (!dragged || dragged.contains(list)) return;
      const parent = list.closest('[data-menu-item]'); const newDepth = list === root ? 0 : depth(parent) + 1;
      if (newDepth + branchDepth(dragged) > 2) { notify('This preview supports at most three menu levels.'); return; }
      const target = event.target.closest('[data-menu-item]');
      if (target && target.parentElement === list && target !== dragged) list.insertBefore(dragged, target); else list.append(dragged);
      update();
    });
  }
  form.querySelectorAll('[data-menu-item]').forEach(bind); form.querySelectorAll('[data-menu-list]').forEach(bindList);
  const label = form.querySelector('[data-add-menu-label]'); const target = form.querySelector('[data-add-menu-target]');
  for (const field of [label,target]) field.addEventListener('input', () => field.setCustomValidity(''));
  form.querySelector('[data-add-menu-item]').addEventListener('click', () => {
    label.setCustomValidity(label.value.trim() ? '' : 'Enter a link label.'); target.setCustomValidity(validTarget(target.value) ? '' : 'Use a relative path or absolute HTTP(S) URL.');
    if (!label.reportValidity() || !target.reportValidity()) return;
    const item = form.querySelector('template').content.firstElementChild.cloneNode(true); item.dataset.id = `local-${nextId++}`;
    item.querySelector('[data-menu-label]').value = label.value.trim(); item.querySelector('[data-menu-target]').value = target.value;
    item.querySelector('[data-menu-type]').value = form.querySelector('[data-add-menu-type]').value; root.append(item); bind(item); bindList(item.querySelector('[data-menu-list]')); enhance(item);
    label.value = ''; target.value = ''; update(); item.querySelector('[data-menu-label]').focus();
  });
  update('preview ready · nothing saved');
}
