const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'build/editor-block-styles': './src/index.js',
        'build/theme': './src/styles/theme.scss',
        'build/theme-masonry': './src/js/show-stats-masonry.js',
        'build/theme-archives': [
            './src/js/archive-show-sort.js',
            './src/js/archive-show-location-cascade.js',
        ],
    },
    output: {
        path: __dirname,
        filename: '[name].js',
    },
    optimization: {
        ...defaultConfig.optimization,
        splitChunks: {
            ...defaultConfig.optimization.splitChunks,
            cacheGroups: {
                ...defaultConfig.optimization.splitChunks.cacheGroups,
                style: {
                    ...defaultConfig.optimization.splitChunks.cacheGroups.style,
                    name(_, chunks, cacheGroupKey) {
                        const chunkName = chunks[0].name;
                        const dir = path.dirname(chunkName);
                        const base = path.basename(chunkName);
                        if (base === 'editor-block-styles') {
                            return `${dir}/block-styles`;
                        }
                        return `${dir}/${cacheGroupKey}-${base}`;
                    },
                },
            },
        },
    },
};
