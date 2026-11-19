import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const repoRoot = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const plugin = path.basename( repoRoot );

test.beforeEach( () => {
	execFileSync(
		'pnpm',
		[
			'exec',
			'wp-env',
			'run',
			'cli',
			'wp',
			'eval-file',
			`wp-content/plugins/${ plugin }/tests/e2e/seed.php`,
		],
		{
			cwd: repoRoot,
			stdio: 'inherit',
		}
	);
} );

test( 'register lists the sale operation', async ( { admin, page } ) => {
	await admin.visitAdminPage( 'admin.php', 'page=ean-to-pos' );

	await expect( page.getByRole( 'heading', { name: 'EAN to PoS', level: 1 } ) ).toBeVisible();
	await expect( page.getByRole( 'link', { name: 'Add sale' } ) ).toBeVisible();
} );

test( 'an unknown code is reported on review', async ( { admin, page } ) => {
	await admin.visitAdminPage( 'admin.php', 'page=ean-to-pos&operation=sale' );
	await page.getByLabel( 'Scan EANs or SKUs' ).fill( '0000000000000' );
	await page.getByRole( 'button', { name: 'Review scans' } ).click();

	await expect( page.getByText( 'No active simple product or variation matches 0000000000000.' ) ).toBeVisible();
} );

test( 'the next document number replaces the suggestion', async ( { admin, page } ) => {
	await admin.visitAdminPage( 'admin.php', 'page=ean-to-pos&operation=sale' );
	const number = page.getByLabel( 'Document number' );

	await expect( number ).toHaveValue( '1' );
	await page.getByRole( 'button', { name: 'Use next number' } ).click();
	await expect( number ).toHaveValue( '2' );
} );

test( 'a scanned SKU can be reviewed and recorded', async ( { admin, page } ) => {
	await admin.visitAdminPage( 'admin.php', 'page=ean-to-pos&operation=sale' );
	await page.getByLabel( 'Scan EANs or SKUs' ).fill( 'EANTEST1\nEANTEST1' );
	await page.getByRole( 'button', { name: 'Review scans' } ).click();

	await expect( page.getByRole( 'rowheader', { name: 'Barcode product' } ) ).toBeVisible();
	await expect( page.getByRole( 'cell', { name: '2', exact: true } ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Record paid sale' } ).click();
	await expect( page.getByText( 'Sale recorded.' ) ).toBeVisible();
} );
