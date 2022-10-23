/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const path = require('path')
const webpack = require('webpack')
const WebpackAssetsManifest = require('webpack-assets-manifest')

const HOST = process.env.HOST ? process.env.HOST : 'localhost'
const PORT = process.env.PORT ? process.env.PORT : 8080
const IS_DEV = process.env.NODE_ENV !== 'production'

const TARGET_NAME = 'build'
const PUBLIC_PATH = IS_DEV ? `http://${HOST}:${PORT}/` : './admin/theme/build/'

const PATHS = {
  app: path.join(__dirname, 'src', 'js'),
  install: path.join(__dirname, 'src', 'js', 'install'),
  api: path.join(__dirname, 'src', 'js', 'api'),
  target: path.join(
    __dirname,
    '..',
    '..',
    'public',
    'admin',
    'theme',
    TARGET_NAME
  ),
}

module.exports = {
  entry: {
    app: PATHS.app,
    install: PATHS.install,
    api: PATHS.api,
  },

  output: {
    filename: path.join('js', IS_DEV ? '[name].js' : '[name]-[fullhash].js'),
    path: PATHS.target,
    publicPath: PUBLIC_PATH,
    clean: true,
  },

  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          options: {
            cacheDirectory: true,
            presets: [['@babel/preset-env', { modules: false }]],
          },
          loader: 'babel-loader',
        },
      },

      {
        test: /\.scss$/,
        exclude: /node_modules/,
        use: [
          IS_DEV
            ? 'style-loader'
            : {
                loader: MiniCssExtractPlugin.loader,
                options: {
                  publicPath: '../',
                },
              },
          'css-loader',
          {
            loader: 'sass-loader',
            options: {
              implementation: require('dart-sass'),
            },
          },
        ],
      },

      {
        test: /\.(png|jpg|jpeg|gif|ico)$/,
        type: 'asset/resource',
        generator: {
          filename: 'images/[hash][ext]',
        },
      },

      {
        test: /\.(woff|woff2|eot|ttf|otf)$/i,
        type: 'asset/resource',
        generator: {
          filename: 'fonts/[hash][ext]',
        },
      },
    ],
  },

  plugins: [
    new WebpackAssetsManifest({
      output: 'manifest.json',
      publicPath: PUBLIC_PATH,
      writeToDisk: true,
    }),

    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
      'window.$': 'jquery',
      Popper: ['popper.js', 'default'],
    }),

    new MiniCssExtractPlugin({
      filename: IS_DEV ? 'css/[name].css' : 'css/[name].[fullhash].css',
      chunkFilename: IS_DEV ? 'css/[id].css' : 'css/[id].[fullhash].css',
    }),
  ],

  devServer: {
    port: PORT,
    hot: true,
    allowedHosts: 'all',
    headers: { 'Access-Control-Allow-Origin': '*' },
  },
}
