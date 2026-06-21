/**
 * Tailwind CSS Configuration v2.0
 * Utility-first CSS framework — BroxLab Design System
 * 
 * Maps CSS custom properties from 1-variables.css and admin.css
 * into Tailwind utility classes for incremental migration.
 */

module.exports = {
  content: [
    './public_html/**/*.php',
    './app/Views/**/*.{php,html,twig}',
    './public_html/assets/**/*.{js,mjs}',
  ],
  // Dark mode handled via @custom-variant dark in tailwind-input.css
  theme: {
    extend: {
      // ── Typography ──
      fontFamily: {
        sans: ['"Noto Sans Bengali"', '"Inter"', '"Segoe UI"', '-apple-system', 'BlinkMacSystemFont', 'Roboto', 'sans-serif'],
        heading: ['"Noto Sans Bengali"', '"Inter"', '"Segoe UI"', '-apple-system', 'BlinkMacSystemFont', 'Roboto', 'sans-serif'],
        mono: ['"JetBrains Mono"', '"Fira Code"', '"Cascadia Code"', '"Consolas"', 'monospace'],
      },
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.625rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1.1' }],
      },

      // ── Color Palette (from 1-variables.css) ──
      colors: {
        // Primary (Warm Blue / Indigo)
        primary: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          DEFAULT: '#6366f1',
        },
        // Neutral (Slate)
        neutral: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          DEFAULT: '#64748b',
        },
        secondary: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          DEFAULT: '#64748b',
        },
        // Semantic colors (mapped from CSS vars)
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
        },
        danger: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
          800: '#991b1b',
          900: '#7f1d1d',
        },
        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
        },
        info: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
        },
        // Surface colors (light mode backgrounds + cards)
        surface: {
          primary: '#f8fafc',
          secondary: '#f1f5f9',
          tertiary: '#e9edf4',
          card: '#ffffff',
        },
        // Admin-specific (from admin.css)
        admin: {
          primary: '#667eea',
          'primary-dark': '#5568d3',
          secondary: '#764ba2',
          accent: '#3b82f6',
        },
        // Card and border tokens for component macros
        card: {
          DEFAULT: '#ffffff',
          border: '#e2e8f0',
          'border-color': '#e2e8f0',
        },

      },



      // ── Border Radius (from 1-variables.css) ──
      borderRadius: {
        sm: '0.375rem',
        DEFAULT: '0.5rem',
        md: '0.5rem',
        lg: '0.75rem',
        xl: '1rem',
        '2xl': '1.25rem',
        '3xl': '1.5rem',
        '4xl': '1.75rem',
        full: '9999px',
      },

      // ── Box Shadow (from 1-variables.css) ──
      boxShadow: {
        'xs': '0 1px 2px rgba(15, 23, 42, 0.05)',
        'sm': '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
        'md': '0 4px 6px rgba(15, 23, 42, 0.08), 0 2px 4px rgba(15, 23, 42, 0.04)',
        'lg': '0 10px 15px rgba(15, 23, 42, 0.08), 0 4px 6px rgba(15, 23, 42, 0.04)',
        'xl': '0 20px 25px rgba(15, 23, 42, 0.10), 0 10px 10px rgba(15, 23, 42, 0.04)',
        'ring': '0 0 0 3px rgba(99, 102, 241, 0.15)',
        'surface': '0 12px 34px rgba(15, 23, 42, 0.08)',
        'surface-hover': '0 18px 46px rgba(102, 126, 234, 0.12)',
      },

      // ── Spacing (from --space-* vars) ──
      spacing: {
        '1': '0.25rem',
        '2': '0.5rem',
        '3': '0.75rem',
        '4': '1rem',
        '5': '1.25rem',
        '6': '1.5rem',
        '8': '2rem',
        '10': '2.5rem',
        '12': '3rem',
        '16': '4rem',
        '20': '5rem',
        '128': '32rem',
        '144': '36rem',
      },

      // ── Z-Index (from 1-variables.css) ──
      zIndex: {
        dropdown: '1000',
        sticky: '1020',
        fixed: '1030',
        'modal-backdrop': '1040',
        modal: '1050',
        popover: '1060',
        tooltip: '1070',
        toast: '1080',
      },

      // ── Max Width (container sizes) ──
      maxWidth: {
        'container-sm': '640px',
        'container-md': '768px',
        'container-lg': '1024px',
        'container-xl': '1200px',
      },

      // ── Transitions ──
      transitionDuration: {
        'fast': '150ms',
        'base': '250ms',
        'slow': '350ms',
      },
      transitionTimingFunction: {
        'material': 'cubic-bezier(0.4, 0, 0.2, 1)',
        'in-material': 'cubic-bezier(0.4, 0, 1, 1)',
        'out-material': 'cubic-bezier(0, 0, 0.2, 1)',
      },

      // ── Gradients ──
      backgroundImage: {
        'gradient-primary': 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
        'gradient-success': 'linear-gradient(135deg, #10b981 0%, #06b6d4 100%)',
        'gradient-warm': 'linear-gradient(135deg, #f59e0b 0%, #f97316 100%)',
        'gradient-cool': 'linear-gradient(135deg, #6366f1 0%, #0ea5e9 100%)',
        'gradient-admin': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        // AI Assistant gradients
        'gradient-ai-fab': 'linear-gradient(135deg, #06b6d4 0%, #6366f1 100%)',
        'gradient-ai-user': 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
        'gradient-ai-send': 'linear-gradient(135deg, #22d3ee 0%, #6366f1 55%, #8b5cf6 100%)',
      },

      // ── Box Shadows with color (AI Assistant) ──
      boxShadow: {
        'xs': '0 1px 2px rgba(15, 23, 42, 0.05)',
        'sm': '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
        'md': '0 4px 6px rgba(15, 23, 42, 0.08), 0 2px 4px rgba(15, 23, 42, 0.04)',
        'lg': '0 10px 15px rgba(15, 23, 42, 0.08), 0 4px 6px rgba(15, 23, 42, 0.04)',
        'xl': '0 20px 25px rgba(15, 23, 42, 0.10), 0 10px 10px rgba(15, 23, 42, 0.04)',
        'ring': '0 0 0 3px rgba(99, 102, 241, 0.15)',
        'surface': '0 12px 34px rgba(15, 23, 42, 0.08)',
        'surface-hover': '0 18px 46px rgba(102, 126, 234, 0.12)',
        // AI Assistant specific shadows
        'ai-fab': '0 12px 30px rgba(99, 102, 241, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.18)',
        'ai-fab-hover': '0 16px 36px rgba(99, 102, 241, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.2)',
        'ai-fab-float': '0 12px 30px rgba(99, 102, 241, 0.35)',
        'ai-shell': '0 14px 40px rgba(15, 23, 42, 0.18), 0 2px 0 rgba(255, 255, 255, 0.45) inset',
        'ai-user-msg': '0 10px 24px rgba(99, 102, 241, 0.22)',
        'ai-assist-msg': '0 8px 18px rgba(15, 23, 42, 0.08)',
        'ai-modal': '0 20px 50px rgba(2, 6, 23, 0.28)',
        'ai-modal-lg': '0 30px 80px rgba(2, 6, 23, 0.45)',
      },

      // ── Animations ──
      keyframes: {
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'fade-in-up': {
          '0%': { opacity: '0', transform: 'translateY(12px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in-down': {
          '0%': { opacity: '0', transform: 'translateY(-12px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'slide-in-right': {
          '0%': { transform: 'translateX(400px)', opacity: '0' },
          '100%': { transform: 'translateX(0)', opacity: '1' },
        },
        'slide-in-up': {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'spin': {
          'to': { transform: 'rotate(360deg)' },
        },
        'pulse': {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.5' },
        },
        // AI Assistant animations
        'brox-ai-float': {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-2px)' },
        },
        'brox-ai-msg-in': {
          'from': { opacity: '0', transform: 'translateY(8px)' },
          'to': { opacity: '1', transform: 'translateY(0)' },
        },
        'brox-ai-panel-in': {
          'from': { opacity: '0', transform: 'translateY(16px) scale(0.97)' },
          'to': { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
        'cursor-pulse': {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.2' },
        },
      },
      animation: {
        'fade-in': 'fade-in 0.25s ease-out',
        'fade-in-up': 'fade-in-up 0.3s ease-out',
        'fade-in-down': 'fade-in-down 0.3s ease-out',
        'slide-in-right': 'slide-in-right 0.3s ease-out',
        'slide-in-up': 'slide-in-up 0.3s ease-out',
        'spin': 'spin 0.75s linear infinite',
        'pulse': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        // AI Assistant animations
        'brox-float': 'brox-ai-float 3.2s ease-in-out infinite',
        'brox-msg-in': 'brox-ai-msg-in 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'brox-panel-in': 'brox-ai-panel-in 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'cursor-blink': 'cursor-pulse 0.8s ease-in-out infinite',
      },
    },
  },
  safelist: [
    // Dynamically constructed color classes (common in Twig/PHP)
    { pattern: /^bg-(primary|success|danger|warning|info|neutral)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^text-(primary|success|danger|warning|info|neutral)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^border-(primary|success|danger|warning|info|neutral)-(50|100|200|300|400|500|600|700|800|900)$/ },
    // Extended color palette for dynamically constructed classes
    // Used in dashboard feature cards, status badges, alerts, and icons
    { pattern: /^bg-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^text-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^border-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^from-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)$/ },
    { pattern: /^to-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)$/ },
    // Opacity modifier variants (used in feature cards and overlays)
    { pattern: /^bg-(teal|green|blue|orange|indigo|purple|cyan|rose|amber|emerald|sky|violet|pink|red|gray|slate)-(50|100|200|300|400|500|600|700|800|900)\/(5|10|20|30|40|50|60|70|80|90)$/ },
    // Stat card gradient variants (use theme color names)
    'from-primary-DEFAULT', 'to-primary-700',
    'from-success-DEFAULT', 'to-success-700',
    'from-warning-DEFAULT', 'to-warning-700',
    'from-danger-DEFAULT', 'to-danger-700',
    'from-info-DEFAULT', 'to-info-700',
  ],
  plugins: [],
};
