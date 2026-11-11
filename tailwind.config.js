// Evaluated by the standalone Tailwind executable, not a JavaScript runtime.
module.exports = {
  content: ['./assets/**/*.js', './templates/**/*.html.twig'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: 'rgb(var(--color-primary) / <alpha-value>)',
        accent: 'rgb(var(--color-secondary) / <alpha-value>)',
        canvas: 'rgb(var(--color-canvas) / <alpha-value>)',
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        ink: 'rgb(var(--color-ink) / <alpha-value>)',
        muted: 'rgb(var(--color-muted) / <alpha-value>)',
        line: 'rgb(var(--color-line) / <alpha-value>)',
        success: '#166534', warning: '#92400e', danger: '#b91c1c', info: '#1e40af',
      },
      fontFamily: { sans: ['DM Sans', 'sans-serif'], display: ['Newsreader', 'Georgia', 'serif'] },
      fontSize: { display: ['clamp(2.75rem, 6vw, 5.5rem)', { lineHeight: '1.02', letterSpacing: '-0.04em' }] },
      spacing: { 18: '4.5rem', 22: '5.5rem' },
      borderRadius: { card: '1rem', control: '.5rem' },
      boxShadow: { card: '0 8px 30px rgb(20 30 25 / 0.05)', float: '0 18px 60px rgb(20 30 25 / 0.18)' },
      maxWidth: { editorial: '80rem' },
      typography: { DEFAULT: { css: { color: 'rgb(var(--color-ink))', '--tw-prose-headings': 'rgb(var(--color-ink))', '--tw-prose-links': 'rgb(var(--color-primary))', '--tw-prose-quotes': 'rgb(var(--color-ink))', '--tw-prose-code': 'rgb(var(--color-ink))', '--tw-prose-bold': 'rgb(var(--color-ink))' } } },
    },
  },
  plugins: [require('@tailwindcss/typography'), require('@tailwindcss/forms')],
};
