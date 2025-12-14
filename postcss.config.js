module.exports = (ctx) => ({
    plugins: {
        'postcss-import': {},
        'tailwindcss': {},
        'autoprefixer': {},
        ...(ctx.env === 'production' ? { 'cssnano': {} } : {}),
    },
});
