/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const webpack = require('webpack');
const merge = require('webpack-merge')
const path = require('path')

const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const ManifestPlugin = require('webpack-manifest-plugin');
const UglifyJsPlugin = require("uglifyjs-webpack-plugin");
const OptimizeCSSAssetsPlugin = require("optimize-css-assets-webpack-plugin");

const HOST = process.env.HOST ? process.env.HOST : 'localhost';
const PORT = process.env.PORT ? process.env.PORT : 8080;
const IS_DEV = process.env.NODE_ENV !== 'production';

const TARGET_NAME = 'build';
const TARGET = path.join(__dirname, TARGET_NAME);
const PUBLIC_PATH = IS_DEV ? `http://${HOST}:${PORT}/` : './admin/theme/build/';
const ASSETS_PUBLIC_PATH = IS_DEV ? `http://${HOST}:${PORT}/` : './';

const PATHS = {
    app: path.join(__dirname, 'src', 'js'),
    target: path.join(__dirname, 'build'),
}

module.exports = {
    devtool: 'source-map',

    entry: {
	app: PATHS.app
    },

    output: {
	filename: path.join('js', IS_DEV ? '[name].js' : '[name]-[hash].js'),
	path: PATHS.target,
	publicPath: PUBLIC_PATH,
    },

    optimization: {
	minimizer: [
	    new UglifyJsPlugin({
		cache: true,
		parallel: true,
		sourceMap: true // set to true if you want JS source maps
	    }),
	    new OptimizeCSSAssetsPlugin({})
	]
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
		    loader: 'babel-loader'
		},
	    },

	    {
                test: /\.scss$/,
                use: [
		    // fallback to style-loader in development
		    IS_DEV ? 'style-loader' : MiniCssExtractPlugin.loader,
		    'css-loader',
		    'postcss-loader',
		    'sass-loader'
                ]
	    },


            {
                test: /\.(png|jpg|jpeg|gif|ico)$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: 'images/[name].[ext]', // @TODO: find a way to inject [hash] in templates
                            publicPath: ASSETS_PUBLIC_PATH
                        }
                    }
                ]
            },

            {
                test: /\.(svg|ttf|otf|eot|woff(2)?)(\?[a-z0-9]+)?$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {
                            name: 'fonts/[name].[ext]', // @TODO: find a way to inject [hash:8] in templates
                            publicPath: ASSETS_PUBLIC_PATH
                        }
                    }
                ]
            }
	]
    },

    plugins: [
	new ManifestPlugin({
	    publicPath: PUBLIC_PATH,
	    writeToFileEmit: true,
	}),

	new webpack.ProvidePlugin({
	    $: 'jquery',
	    jQuery: 'jquery',
	    'window.jQuery': 'jquery',
	    'window.$': 'jquery',
	    Popper: ['popper.js', 'default'],
	}),

	new MiniCssExtractPlugin({
	    // Options similar to the same options in webpackOptions.output
	    // both options are optional
	    filename: IS_DEV ? '[name].css' : '[name].[hash].css',
	    chunkFilename: IS_DEV ? '[id].css' : '[id].[hash].css' // @TODO: find a way to inject [hash] in templates
        }),


	new webpack.HotModuleReplacementPlugin(),

        new CleanWebpackPlugin()
    ],

    devServer: {
	contentBase: path.target,
	disableHostCheck: true,
	port: PORT,
	hot: true,
        inline: true,
        overlay: true,
        headers: { 'Access-Control-Allow-Origin': '*' },
    }
};
