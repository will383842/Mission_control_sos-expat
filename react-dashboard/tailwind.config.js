/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Semantic tokens bound to CSS variables → supports light/dark switching
        bg:       'rgb(var(--bg) / <alpha-value>)',
        surface:  'rgb(var(--surface) / <alpha-value>)',
        surface2: 'rgb(var(--surface2) / <alpha-value>)',
        border:   'rgb(var(--border) / <alpha-value>)',
        text:     'rgb(var(--text) / <alpha-value>)',
        'text-muted': 'rgb(var(--text-muted) / <alpha-value>)',

        // Brand colors (constant across themes)
        violet:   '#7c3aed',
        'violet-light': '#a78bfa',
        'violet-dark':  '#6d28d9',
        cyan:     '#06b6d4',
        amber:    '#f59e0b',
        success:  '#10b981',
        danger:   '#ef4444',
        warning:  '#f59e0b',
        info:     '#06b6d4',
        muted:    '#6b7280',
      },
      fontFamily: {
        sans:  ['DM Sans', 'sans-serif'],
        title: ['Syne', 'sans-serif'],
        mono:  ['DM Mono', 'monospace'],
      },
      spacing: {
        // 4px grid for consistency
        '4.5': '1.125rem',
        '18':  '4.5rem',
        '22':  '5.5rem',
      },
      borderRadius: {
        'xs': '2px',
        'sm': '4px',
        'md': '6px',
        'lg': '8px',
        'xl': '12px',
        '2xl': '16px',
      },
      boxShadow: {
        'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        'sm': '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        'md': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        'lg': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        'glow-violet': '0 0 20px rgb(124 58 237 / 0.3)',
      },
      animation: {
        'fade-in':       'fadeIn 200ms ease-out',
        'slide-up':      'slideUp 200ms ease-out',
        'slide-down':    'slideDown 200ms ease-out',
        'slide-in-right':'slideInRight 250ms cubic-bezier(0.22, 1, 0.36, 1)',
        'scale-in':      'scaleIn 180ms cubic-bezier(0.22, 1, 0.36, 1)',
        'pulse-slow':    'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn:       { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
        slideUp:      { '0%': { transform: 'translateY(10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
        slideDown:    { '0%': { transform: 'translateY(-10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
        slideInRight: { '0%': { transform: 'translateX(100%)', opacity: '0' }, '100%': { transform: 'translateX(0)', opacity: '1' } },
        scaleIn:      { '0%': { transform: 'scale(0.96)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
      },
      minHeight: {
        'touch': '44px', // WCAG AA touch target
      },
      minWidth: {
        'touch': '44px',
      },
    },
  },
  plugins: [],
}
