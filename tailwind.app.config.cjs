/**
 * Tailwind v3 build for the offline PWA (public/app) and the Blade pages.
 * The PWA can't depend on the Tailwind CDN because it must render offline.
 * Build: npm run build:css
 */
module.exports = {
  content: [
    './public/app/index.html',
    './public/app/*.js',
    './resources/views/**/*.blade.php',
  ],
  theme: {
    extend: {
      fontFamily: { sans: ['var(--app-font)', 'system-ui', 'sans-serif'] },
      colors: {
        ink: '#0B2239',
        muted: '#5B7185',
        line: '#DCE6EE',
        canvas: '#EEF3F7',
        primary: '#0F6F8C',
        primaryd: '#0B5670',
        deep: '#0A2E45',
        mint: '#12946B',
        amber: '#C77A16',
        rose: '#C2434B',
      },
      boxShadow: {
        card: '0 1px 2px rgba(11,34,57,.04), 0 8px 24px -16px rgba(11,34,57,.25)',
        pop: '0 24px 60px -20px rgba(11,34,57,.35)',
      },
      borderRadius: { xl2: '14px' },
    },
  },
};
