import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test as setup } from '@playwright/test';

const authFile = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '.auth/admin.json' );

setup( 'log in to the admin', async ( { page } ) => {
	mkdirSync( path.dirname( authFile ), { recursive: true } );

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( process.env.WP_USERNAME || 'admin' );
	await page.locator( '#user_pass' ).fill( process.env.WP_PASSWORD || 'password' );
	await page.locator( '#wp-submit' ).click();

	await expect( page ).toHaveURL( /\/wp-admin\// );
	await page.context().storageState( { path: authFile } );
} );
