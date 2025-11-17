import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: '#fff5f0',
                    100: '#ffe8db',
                    200: '#ffd0b8',
                    300: '#ffb08a',
                    400: '#ff8a5c',
                    500: '#ff6b35',
                    600: '#f04e1a',
                    700: '#c73d10',
                    800: '#9e3310',
                    900: '#7f2d11',				
                },
                secondary: {
                    50: '#e6f2f9',
                    100: '#cce5f3',
                    200: '#99cbe7',
                    300: '#66b1db',
                    400: '#3397cf',
                    500: '#004e89',
                    600: '#003e6e',
                    700: '#002f53',
                    800: '#001f38',
                    900: '#00101d',
                },
                accent: {
                    50: '#fef9e6',
                    100: '#fdf3cc',
                    200: '#fbe799',
                    300: '#f9db66',
                    400: '#f7cf33',
                    500: '#f7b801',
                    600: '#c59301',
                    700: '#946e01',
                    800: '#624a00',
                    900: '#312500',
                },
                success: {
                    50: '#e8f8f0',
                    100: '#d1f1e1',
                    200: '#a3e3c3',
                    300: '#75d5a5',
                    400: '#47c787',
                    500: '#2ecc71',
                    600: '#25a35a',
                    700: '#1c7a44',
                    800: '#12522d',
                    900: '#092917',
                },
                warning: {
                    50: '#fef6e6',
                    100: '#fdedcc',
                    200: '#fbdb99',
                    300: '#f9c966',
                    400: '#f7b733',
                    500: '#f39c12',
                    600: '#c27d0e',
                    700: '#925e0b',
                    800: '#613e07',
                    900: '#311f04',
                },
                error: {
                    50: '#fee6e6',
                    100: '#fdcccc',
                    200: '#fb9999',
                    300: '#f96666',
                    400: '#f73333',
                    500: '#e74c3c',
                    600: '#b93d30',
                    700: '#8b2e24',
                    800: '#5c1e18',
                    900: '#2e0f0c',
                },
                neutral: {
                    50: '#f8f9fa',
                    100: '#f1f3f5',
                    200: '#e9ecef',
                    300: '#dee2e6',
                    400: '#ced4da',
                    500: '#adb5bd',
                    600: '#868e96',
                    700: '#495057',
                    800: '#343a40',
                    900: '#1a1a2e',
                },
            },
            boxShadow: {
                'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.15)',
                'glow': '0 0 20px rgba(255, 107, 53, 0.3)',
            },
            backdropBlur: {
                'xs': '2px',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-in',
                'slide-up': 'slideUp 0.3s ease-out',
                'scale-in': 'scaleIn 0.2s ease-out',
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.95)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
            },
        },
    },

    plugins: [forms],
};