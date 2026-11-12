const root = document.querySelector('[data-menu-sortable]');
if (root) {
    let dragged = null;
    const items = () => [...root.querySelectorAll('[data-menu-item]')];
    root.addEventListener('dragstart', (event) => {
        dragged = event.target.closest('[data-menu-item]');
        if (dragged) dragged.classList.add('opacity-50');
    });
    root.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('opacity-50');
        dragged = null;
    });
    root.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('[data-menu-item]');
        if (!target || !dragged || target === dragged || target.parentElement !== dragged.parentElement) return;
        const rect = target.getBoundingClientRect();
        target.parentElement.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
    });
    root.addEventListener('drop', async (event) => {
        event.preventDefault();
        const status = root.querySelector('[data-menu-status]');
        const payload = { items: items().map((item) => {
            const siblings = [...item.parentElement.children].filter((node) => node.matches('[data-menu-item]'));
            return { id: Number(item.dataset.id), parentId: item.dataset.parentId ? Number(item.dataset.parentId) : null, position: siblings.indexOf(item) };
        }), _token: root.dataset.csrfToken };
        try {
            const response = await fetch(root.dataset.reorderUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            if (!response.ok) throw new Error('Reorder failed');
            if (status) status.textContent = 'Order saved';
        } catch (error) {
            if (status) status.textContent = 'Could not save order';
        }
    });
}
