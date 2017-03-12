const autoprefixer = require('autoprefixer');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const CleanPlugin = require('clean-webpack-plugin');
const path = require('path');
const isProduction = true;

const config = {
  entry: './assets/src/index',
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: ['babel-loader']
      },
      {
        test: /\.scss$/,
        loader: ExtractTextPlugin.extract({
          fallbackLoader: 'style-loader',
          loader: [
            {
              loader: 'css-loader',
              query: {
                modules: false,
                importLoaders: 1,
                url: false
              },
            },
            {
              loader: 'sass-loader',
              query: {
                includePaths: [path.resolve(__dirname, './library/scss')]
              }
            },
          ],
        })
      }
    ]
  },
  output: {
    filename: '[name].js',
    path: path.join(__dirname, './assets/dist'),
    publicPath: '/wp-content/plugins/edd-mail-chimp/assets/dist/'
  },
  plugins: [
    // Removes the /dist folder on every rebuild
    new CleanPlugin([path.resolve(__dirname, 'assets/dist')], {
      verbose: false,
    }),
    new ExtractTextPlugin({
      filename: './css/[name].css',
      allChunks: true,
      disable: !isProduction,
    })
  ],
  resolve: {
    extensions: ['.js', '.scss'],
  }
}

module.exports = config;
