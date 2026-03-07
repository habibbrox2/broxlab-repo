/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './app/Views/**/*.twig',
        './public_html/assets/js/**/*.js',
        './app/Controllers/**/*.php'
    ],
    prefix: 'tw-',
    corePlugins: {
        preflight: false
    },
    theme: {
        extend: {
            boxShadow: {
                glass: '0 10px 30px rgba(2, 6, 23, 0.12)'
            }
        }
    },
    plugins: []
};
