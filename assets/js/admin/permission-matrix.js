export default function permissionMatrix(form) {
  const boxes = [...form.querySelectorAll('[data-permission-role]')];
  const update = () => { form.querySelector('[data-permission-summary]').textContent = `${boxes.filter(box => box.checked).length} local permission selections · no access changed`; };
  boxes.forEach(box => box.addEventListener('change', update));
  form.querySelectorAll('[data-select-permission-role]').forEach(button => button.addEventListener('click', () => { const column = boxes.filter(box => box.dataset.permissionRole === button.dataset.selectPermissionRole); const checked = !column.every(box => box.checked); column.forEach(box => { box.checked = checked; }); update(); }));
  update();
}
