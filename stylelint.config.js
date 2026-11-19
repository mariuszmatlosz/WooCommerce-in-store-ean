const config = {
	extends: [ '@10up/stylelint-config' ],
	customSyntax: 'postcss-scss',
	plugins: [ 'stylelint-scss' ],
	rules: {
		'selector-nested-pattern': null,
		'at-rule-no-unknown': null,
		'scss/at-rule-no-unknown': true,
	},
};

module.exports = config;
