const webpackConfig = require('@nextcloud/webpack-vue-config')
const ESLintPlugin = require('eslint-webpack-plugin')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const path = require('path')

// Bundle the Files sidebar integration. The overview uses plain js/script.js.
webpackConfig.entry = {
	'files-init': { import: path.join(__dirname, 'src', 'files-init.js'), filename: 'files-init.js' },
}

// Keep hand-maintained assets when cleaning the output directory (default wipes ./js).
webpackConfig.output.clean = {
	keep: (asset) => asset === 'script.js',
}

webpackConfig.plugins.push(
	new ESLintPlugin({
		extensions: ['js', 'vue'],
		files: 'src',
	}),
)
webpackConfig.plugins.push(
	new StyleLintPlugin({
		files: 'src/**/*.{css,scss,vue}',
	}),
)

webpackConfig.module.rules.push({
	test: /\.svg$/i,
	type: 'asset/source',
})

module.exports = webpackConfig
