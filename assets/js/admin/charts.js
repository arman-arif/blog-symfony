import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default function charts(root) {
  const type = root.dataset.chartType;
  const palette = ['#1c5b45', '#bb4527', '#647c9b', '#d1a54d', '#aa7290', '#6d8d71'];
  const isLine = type === 'line';
  const textColor = () => getComputedStyle(document.documentElement).getPropertyValue('--color-muted').trim();
  const chart = new Chart(root.querySelector('canvas'), {
    type,
    data: {
      labels: JSON.parse(root.dataset.chartLabels),
      datasets: [{ label: isLine ? 'Views' : 'Reader share (%)', data: JSON.parse(root.dataset.chartValues),
        borderColor: isLine ? palette[0] : 'transparent', backgroundColor: isLine ? '#1c5b451a' : palette,
        borderWidth: isLine ? 2 : 0, fill: isLine, tension: .3, pointRadius: 3 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, animation: false,
      plugins: { legend: { display: !isLine, position: 'bottom', labels: { boxWidth: 10, color: `rgb(${textColor()})`, font: { size: 11 } } } },
      ...(isLine ? { scales: { y: { beginAtZero: true, grid: { color: '#82958722' }, ticks: { color: `rgb(${textColor()})` } }, x: { grid: { display: false }, ticks: { color: `rgb(${textColor()})` } } } } : { cutout: '72%' }),
    },
  });
  new MutationObserver(() => {
    const color = `rgb(${textColor()})`;
    chart.options.plugins.legend.labels.color = color;
    if (isLine) { chart.options.scales.x.ticks.color = color; chart.options.scales.y.ticks.color = color; }
    chart.update('none');
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
