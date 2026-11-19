module.exports = {
	extends: [ '@10up/eslint-config/wordpress' ],
	overrides: [
		{
			files: [ 'tests/js/**/*.js' ],
			extends: [ '@10up/eslint-config/jest' ],
		},
	],
};
