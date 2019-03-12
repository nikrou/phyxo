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
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const ManifestPlugin = require('webpack-manifest-plugin');
const UglifyJSPlugin = require('uglifyjs-webpack-plugin');
const CleanupPlugin = require('clean-webpack-plugin')

const IS_PROD = process.env.NODE_ENV === 'production';
const PUBLIC_PATH = IS_PROD ? './admin/theme/build/' : 'http://localhost:8080/build/';

const PATHS = {
    app: path.join(__dirname, 'src', 'js'),
    target: path.join(__dirname, 'build'),
}

const STYLE_LOADER = {
    loader: 'style-loader',
    options: {
	sourceMap: true
    }
}

const CSS_LOADER = {
    loader: 'css-loader',
    options: {
	sourceMap: true,
	minimize: IS_PROD
    }
}

const SASS_LOADER = {
    loader: 'sass-loader',
    options: {
	sourceMap: true
    }
}

const MAIN_CONFIG = merge([
    {
	devtool: 'cheap-module-source-map',

	entry: {
	    app: PATHS.app
	},

	output: {
	    filename: path.join('js', IS_PROD ? '[name]-[hash:8].js' : '[name].js'),
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
			loader: 'babel-loader'
		    },
		},

		{
                    test: /\.(svg|png|jpg|jpeg|gif|ico)$/,
                    use: [
			{
			    loader: 'file-loader',
			    options: {
				name: 'images/[name]-[hash:8].[ext]',
				publicPath: IS_PROD ? '../' : PUBLIC_PATH,
			    }
			}
                    ]
		},

		{
		    test: /\.(ttf|otf|eot|woff(2)?)(\?[a-z0-9]+)?$/,
		    use: [
			{
			    loader: 'file-loader',
			    options: {
				name: 'fonts/[name]-[hash:8].[ext]',
				publicPath: IS_PROD ? '../' : PUBLIC_PATH,
			    }
			}
		    ]
		},
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
	    new webpack.DefinePlugin({
		'process.env': { NODE_ENV: JSON.stringify(process.env.NODE_ENV) }
	    }),
	],
    }
]);

const devConfig = () => merge([
    MAIN_CONFIG,

    {
	plugins: [
	    new webpack.HotModuleReplacementPlugin(),
	    new webpack.NamedModulesPlugin(),
	    new webpack.DefinePlugin({
		__DEVTOOLS__: true,
	    }),
	],
    },

    {
	module: {
	    rules: [
		{
		    test: /\.scss$/,
		    use: [
			STYLE_LOADER,
			CSS_LOADER,
			SASS_LOADER,
		    ]
		}
	    ]
	}
    },

    {
	devServer: {
	    contentBase: PATHS.target,
	    disableHostCheck: true,
	    hot: true,
	    inline: true,
	    overlay: true,
	    headers: { 'Access-Control-Allow-Origin': '*' },
	},
    }
]);

const prodConfig = () => merge([
    MAIN_CONFIG,

    {
	module: {
	    rules: [
		{
		    test: /\.scss$/,
		    use: ExtractTextPlugin.extract({
			use: [
			    CSS_LOADER,
			    SASS_LOADER,
			],
			fallback: STYLE_LOADER
		    })
		}
	    ]
	}
    },

    {
	plugins: [
	    new webpack.HashedModuleIdsPlugin(),
	    new ExtractTextPlugin({
		filename: 'css/[name].[contenthash:8].css',
	    }),
	    new CleanupPlugin(PATHS.target),
	]
    }
]);

module.exports = (env = process.env.NODE_ENV) => env === 'production' ? prodConfig() : devConfig();
