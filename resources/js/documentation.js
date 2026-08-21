import.meta.glob('../../docs/user-documentation/**/*.{png,jpg,jpeg,gif,svg,webp}', {
    eager: true,
    query: '?url',
    import: 'default',
})
