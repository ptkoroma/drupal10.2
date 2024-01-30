const path = require('path');
const merge = require('webpack-merge');
const webpackConfig = require('./webpack.config');

module.exports = merge(webpackConfig, {
  devtool: 'source-map',
  output: {
    pathinfo: true,
    publicPath: '/',
    filename: '[name].js',
  },
  devServer: {
    host: 'opignod8',
    contentBase: path.join(__dirname, '/dist'),
    port: 8080,
  },
});
