const path = require('path');

module.exports = {
  productionSourceMap: process.env.NODE_ENV != 'production',
  configureWebpack: {
    resolve: {
      alias: {
        "~": path.resolve(__dirname, 'src/')
      }
    }
  }
};
