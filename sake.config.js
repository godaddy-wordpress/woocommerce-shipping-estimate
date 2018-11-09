module.exports = {
	deploy: {
		type: 'local'
	},
	framework: false,
	multiPluginRepo: false,
	paths: {
		src: '.',
		exclude: [
			'vendor',
			'build'
		]
	}
}
