export default function activityPreview(root) {
  const dialog = document.getElementById('activity-detail-dialog');
  root.querySelectorAll('[data-activity-detail]').forEach(button => {
    button.addEventListener('click', () => {
      const log = JSON.parse(button.dataset.activityDetail);
      dialog.querySelector('[data-activity-context]').textContent = JSON.stringify({ action: log.action, subjectType: log.subjectType, subjectId: log.subjectId, actor: log.user.displayName, context: log.context, createdAt: log.createdAt }, null, 2);
      dialog.showModal(); dialog.addEventListener('close', () => button.focus(), { once: true });
    });
    button.disabled = false;
  });
}
