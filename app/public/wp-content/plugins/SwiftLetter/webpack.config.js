const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		dashboard: path.resolve( __dirname, 'src/dashboard/index.js' ),
		'article-sidebar': path.resolve(
			__dirname,
			'src/article-sidebar/index.js'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
