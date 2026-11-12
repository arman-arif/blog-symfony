export default function analyticsPreview(root) {
  const days = JSON.parse(root.dataset.analyticsDaily);
  const form = root.querySelector('form');
  const from = form.querySelector('[name="from"]');
  const to = form.querySelector('[name="to"]');
  const checkDates = () => to.setCustomValidity(from.value && to.value && to.value < from.value ? 'End date must not precede the start date.' : '');
  form.addEventListener('input', checkDates);
  const chart = (id, labels, values) => {
    const canvas = root.querySelector(`#${id}`);
    const section = canvas.closest('[data-component="charts"]');
    // Persist on the node too, so late-loaded charts use the current projection.
    section.dataset.chartLabels = JSON.stringify(labels);
    section.dataset.chartValues = JSON.stringify(values);
    section.dispatchEvent(new CustomEvent('chart:changed', { detail: { labels, values } }));
    const body = section.querySelector('tbody');
    body.replaceChildren(...labels.map((label, index) => {
      const row = document.createElement('tr'); row.className = 'border-t border-line';
      const heading = document.createElement('th'); heading.scope = 'row'; heading.className = 'py-2 font-normal'; heading.textContent = label;
      const value = document.createElement('td'); value.textContent = values[index].toLocaleString(); row.append(heading, value); return row;
    }));
  };
  const render = () => {
    const matching = days.filter(day => day.date >= from.value && day.date <= to.value);
    const views = matching.reduce((sum, day) => sum + day.views, 0);
    const engagements = matching.reduce((sum, day) => sum + day.engagements, 0);
    root.querySelector('[data-analytics-stat="views"] [data-stat-value]').textContent = views.toLocaleString();
    root.querySelector('[data-analytics-stat="engagements"] [data-stat-value]').textContent = engagements.toLocaleString();
    root.querySelector('[data-analytics-summary]').textContent = `${matching.length} fictional days · ${from.value} to ${to.value} · no traffic collected`;
    root.querySelector('[data-analytics-empty]').hidden = matching.length !== 0;
    chart('analytics-views-chart', matching.map(day => day.date), matching.map(day => day.views));
    const months = {};
    matching.forEach(day => { const month = day.date.slice(0, 7); months[month] = (months[month] || 0) + day.views; });
    chart('analytics-monthly-chart', Object.keys(months), Object.values(months));
    root.querySelectorAll('[data-range-share]').forEach(cell => { cell.textContent = Math.round(views * Number(cell.dataset.rangeShare) / 100).toLocaleString(); });
  };
  form.addEventListener('preview:valid', render);
  form.querySelector('[data-analytics-reset]').addEventListener('click', () => { form.reset(); checkDates(); render(); });
  checkDates(); render();
}
