export default function campaignForm(form) {
  const status = form.querySelector('#campaign-status'); const schedule = form.querySelector('#campaign-schedule');
  const update = () => { schedule.required = status.value === 'scheduled'; schedule.disabled = status.value !== 'scheduled'; };
  status.addEventListener('change', update); update();
}
