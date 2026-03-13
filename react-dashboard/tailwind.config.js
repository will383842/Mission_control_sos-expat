/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        bg:       '#090d12',
        surface:  '#101419',
        surface2: '#171c23',
        border:   '#1e2530',
        violet:   '#7c3aed',
        'violet-light': '#a78bfa',
        cyan:     '#06b6d4',
        amber:    '#f59e0b',
        success:  '#10b981',
        danger:   '#ef4444',
        muted:    '#6b7280',
      },
      fontFamily: {
        sans:  ['DM Sans', 'sans-serif'],
        title: ['Syne', 'sans-serif'],
        mono:  ['DM Mono', 'monospace'],
      },
    },
  },
  plugins: [],
}
