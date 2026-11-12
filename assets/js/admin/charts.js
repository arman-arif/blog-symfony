import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default function charts(root) {
  const type = root.dataset.chartType;
  const palette = () => document.documentElement.classList.contains('dark')
    ? ['#88cba4', '#f79b76', '#97b6e0', '#e6c16b', '#dca0bd', '#b5c79b']
    : ['#1c5b45', '#bb4527', '#526f95', '#947020', '#945b7c', '#526f56'];
  const isLine = type === 'line';
  const textColor = () => getComputedStyle(document.documentElement).getPropertyValue('--color-muted').trim();
  const surfaceColor = () => `rgb(${getComputedStyle(document.documentElement).getPropertyValue('--color-surface').trim()})`;
  const chart = new Chart(root.querySelector('canvas'), {
    type,
    data: {
      labels: JSON.parse(root.dataset.chartLabels),
      datasets: [{ label: isLine ? 'Views' : 'Reader share (%)', data: JSON.parse(root.dataset.chartValues),
        borderColor: isLine ? palette()[0] : surfaceColor(), backgroundColor: isLine ? `${palette()[0]}1a` : palette(),
        borderWidth: 2, fill: isLine, tension: .3, pointRadius: 3 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: false,
      plugins: { legend: { display: !isLine, position: 'bottom', labels: { boxWidth: 10, color: `rgb(${textColor()})`, font: { size: 11 } } } },
      ...(isLine ? { scales: { y: { beginAtZero: true, grid: { color: '#82958722' }, ticks: { color: `rgb(${textColor()})` } }, x: { grid: { display: false }, ticks: { color: `rgb(${textColor()})` } } } } : { cutout: '72%' }),
    },
  });
  root.addEventListener('chart:changed', event => {
    chart.data.labels = event.detail.labels;
    chart.data.datasets[0].data = event.detail.values;
    chart.update('none');
  });
  new MutationObserver(() => {
    const color = `rgb(${textColor()})`;
    chart.data.datasets[0].borderColor = isLine ? palette()[0] : surfaceColor();
    chart.data.datasets[0].backgroundColor = isLine ? `${palette()[0]}1a` : palette();
    chart.options.plugins.legend.labels.color = color;
    if (isLine) { chart.options.scales.x.ticks.color = color; chart.options.scales.y.ticks.color = color; }
    chart.update('none');
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
