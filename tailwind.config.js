/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './templates/**/*.php',
        './src/**/*.php',
        './assets/js/src/**/*.js',
    ],
    // Use prefix to avoid conflicts with theme/other plugins
    prefix: 'wpss-',
    theme: {
        extend: {
            colors: {
                primary: {
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
                    950: '#082f49',
                },
                success: {
                    50: '#f0fdf4',
                    500: '#22c55e',
                    700: '#15803d',
                },
                warning: {
                    50: '#fffbeb',
                    500: '#f59e0b',
                    700: '#b45309',
                },
                danger: {
                    50: '#fef2f2',
                    500: '#ef4444',
                    700: '#b91c1c',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
    // Important to prevent conflicts
    important: '.wpss-wrapper',
    corePlugins: {
        preflight: false, // Disable base styles to avoid conflicts
    },
};
