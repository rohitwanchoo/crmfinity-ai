import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // WowDash inspired color palette
                primary: {
                    50: '#EEF4FF',
                    100: '#D9E5FF',
                    200: '#BCCFFF',
                    300: '#8EB0FF',
                    400: '#5985FF',
                    500: '#487FFF',
                    600: '#1C52F6',
                    700: '#1A47E3',
                    800: '#1C3BB8',
                    900: '#1D3691',
                    950: '#162359',
                },
                secondary: {
                    50: '#F8FAFC',
                    100: '#F1F5F9',
                    200: '#E2E8F0',
                    300: '#CBD5E1',
                    400: '#94A3B8',
                    500: '#64748B',
                    600: '#475569',
                    700: '#334155',
                    800: '#1E293B',
                    900: '#0F172A',
                },
                accent: {
                    orange: '#FF9F29',
                    teal: '#02BCAF',
                    pink: '#F0437D',
                    cyan: '#43DCFF',
                    purple: '#8B5CF6',
                    green: '#22C55E',
                    red: '#EF4444',
                },
                sidebar: {
                    bg: '#1E293B',
                    hover: '#334155',
                    active: '#487FFF',
                    text: '#94A3B8',
                    'text-active': '#FFFFFF',
                },
                dashboard: {
                    bg: '#F1F5F9',
                    card: '#FFFFFF',
                    border: '#E2E8F0',
                },
            },
            boxShadow: {
                'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
                'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                'sidebar': '4px 0 10px rgba(0, 0, 0, 0.1)',
            },
            borderRadius: {
                'xl': '12px',
                '2xl': '16px',
            },
        },
    },

    plugins: [forms],
};
