/**
 * Extends the 10up Toolkit Jest preset and keeps its Babel transform.
 * A `jest` key in package.json would replace that preset entirely.
 */
const config = require( '10up-toolkit/config/jest-unit.config.js' );

module.exports = {
	...config,
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
	fakeTimers: {
		enableGlobally: false,
	},
};
