const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'build/index': './src/index.js',
    },
    output: {
        path: __dirname,
        filename: '[name].js',
    },
};
