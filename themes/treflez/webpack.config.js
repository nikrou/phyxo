/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const path = require('path')
const webpack = require('webpack')

const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const { CleanWebpackPlugin } = require('clean-webpack-plugin')
const WebpackAssetsManifest = require('webpack-assets-manifest')

const HOST = process.env.HOST ? process.env.HOST : 'localhost'
const PORT = process.env.PORT ? process.env.PORT : 8080
const IS_DEV = process.env.NODE_ENV !== 'production'

/**
 * PATH_PREFIX is used to add a prefix to phyxo urls.
 * For example if you install Phyxo under /photos rebuild theme with:
 * PATH_PREFIX=/photos/ npm run build
 *
 */
const PATH_PREFIX = process.env.PATH_PREFIX ? process.env.PATH_PREFIX : ''

const TARGET_NAME = 'build'
const PUBLIC_PATH = IS_DEV
  ? `http://${HOST}:${PORT}/`
  : `${PATH_PREFIX}themes/treflez/${TARGET_NAME}/`
const ASSETS_PUBLIC_PATH = IS_DEV ? `http://${HOST}:${PORT}/` : './'

const PATHS = {
  theme: path.join(__dirname, 'src', 'js'),
  admin: path.join(__dirname, 'src', 'admin', 'js'),
  target: path.join(
    __dirname,
    '..',
    '..',
    'public',
    'themes',
    'treflez',
    TARGET_NAME
  ),
}

/**
 * bootswatch themes available
 *
 * cerulean, cosmo, cyborg, darkly, flatly, journal, litera, lumen, lux, materia, minty, pulse, sandstone,
 * simplex, sketchy, slate, solar, spacelab, superhero, united, yeti
 *
 */

module.exports = {
  devtool: 'source-map',

  entry: {
    theme: PATHS.theme,
    admin: PATHS.admin,
  },

  output: {
    filename: path.join('js', IS_DEV ? '[name].js' : '[name]-[fullhash].js'),
    path: PATHS.target,
    publicPath: PUBLIC_PATH,
  },

  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          options: {
            cacheDirectory: true,
            presets: [['env', { modules: false }]],
          },
          loader: 'babel-loader',
        },
      },

      {
        test: /\.scss$/,
        use: [
          // fallback to style-loader in development
          IS_DEV ? 'style-loader' : MiniCssExtractPlugin.loader,
          'css-loader',
          'sass-loader',
        ],
      },

      {
        test: /\.(png|jpg|jpeg|gif|ico)$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: 'images/[name].[ext]', // @TODO: find a way to inject [hash] in templates
              publicPath: ASSETS_PUBLIC_PATH,
            },
          },
        ],
      },

      {
        test: /\.(svg|ttf|otf|eot|woff(2)?)(\?[a-z0-9]+)?$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: 'fonts/[name].[ext]', // @TODO: find a way to inject [hash:8] in templates
              publicPath: ASSETS_PUBLIC_PATH,
            },
          },
        ],
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
      'jquery-migrate': 'jquery-migrate',
    }),

    new MiniCssExtractPlugin({
      // Options similar to the same options in webpackOptions.output
      // both options are optional
      filename: IS_DEV ? '[name].css' : '[name].[fullhash].css',
      chunkFilename: IS_DEV ? '[id].css' : '[id].[fullhash].css', // @TODO: find a way to inject [hash] in templates
    }),

    new webpack.HotModuleReplacementPlugin(),

    new CleanWebpackPlugin(),
  ],

  devServer: {
    contentBase: path.target,
    disableHostCheck: true,
    hot: true,
    port: PORT,
    inline: true,
    overlay: true,
    headers: { 'Access-Control-Allow-Origin': '*' },
  },
}
