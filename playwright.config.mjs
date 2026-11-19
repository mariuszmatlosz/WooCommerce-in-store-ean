import { defineConfig, devices } from '@playwright/test';

const storageState = 'tests/e2e/.auth/admin.json';

export default defineConfig( {
	testDir: 'tests/e2e',
	fullyParallel: false,
	workers: 1,
	timeout: 120000,
	expect: {
		timeout: 20000,
	},
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		...devices[ 'Desktop Chrome' ],
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'setup',
			testMatch: 'auth.setup.mjs',
		},
		{
			name: 'e2e',
			testMatch: '**/*.spec.mjs',
			dependencies: [ 'setup' ],
			use: {
				storageState,
			},
		},
	],
	webServer: {
		command: 'pnpm wp-env start',
		url: 'http://localhost:8888/wp-login.php',
		reuseExistingServer: true,
		timeout: 300000,
	},
} );
