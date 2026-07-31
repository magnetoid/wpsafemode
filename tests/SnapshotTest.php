<?php
/**
 * Snapshot capture and restore integrity.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tests;

use PHPUnit\Framework\TestCase;
use WPSM\Snapshots\FileSnapshot;
use WPSM\Snapshots\Store;

/**
 * A rollback that restores the wrong bytes is worse than no rollback, so these
 * check the round trip exactly rather than approximately.
 */
final class SnapshotTest extends TestCase {

	private Store $store;
	private FileSnapshot $snapshot;
	private string $target;

	protected function setUp(): void {
		$this->store    = new Store();
		$this->snapshot = new FileSnapshot( $this->store );
		$this->target   = WP_CONTENT_DIR . '/themes/demo-theme/snapshot-target.php';
	}

	protected function tearDown(): void {
		@unlink( $this->target );
	}

	public function test_captures_contents_and_checksum(): void {
		$original = "<?php\n// original contents\n";
		file_put_contents( $this->target, $original );

		$result = $this->snapshot->capture( 'chg_test_capture', [ $this->target ] );
		$file   = $result['files'][0];

		$this->assertTrue( $file['existed'] );
		$this->assertSame( hash( 'sha256', $original ), $file['sha256'] );
		$this->assertSame( strlen( $original ), $file['bytes'] );
	}

	public function test_snapshot_payload_matches_the_original_byte_for_byte(): void {
		// Binary-ish content with null bytes and CRLF, because a snapshot that
		// only survives clean UTF-8 is not a snapshot.
		$original = "<?php\r\n\$x = \"\x00\x01\x02\";\r\n// ünïcödé\n";
		file_put_contents( $this->target, $original );

		$this->snapshot->capture( 'chg_test_bytes', [ $this->target ] );

		$blob = $this->store->snapshot_dir( 'chg_test_bytes' ) . '/files/0.bin';

		$this->assertFileExists( $blob );
		$this->assertSame( $original, file_get_contents( $blob ) );
	}

	public function test_records_a_file_that_does_not_exist_yet(): void {
		// The inverse of "create this file" is "delete it", which only works if
		// the snapshot recorded that it was absent.
		$this->assertFileDoesNotExist( $this->target );

		$result = $this->snapshot->capture( 'chg_test_absent', [ $this->target ] );
		$file   = $result['files'][0];

		$this->assertFalse( $file['existed'] );
		$this->assertNull( $file['sha256'] );
	}

	public function test_writes_a_readable_manifest(): void {
		file_put_contents( $this->target, "<?php\n// manifest test\n" );
		$this->snapshot->capture( 'chg_test_manifest', [ $this->target ] );

		$path = $this->store->snapshot_dir( 'chg_test_manifest' ) . '/manifest.json';

		$this->assertFileExists( $path );

		$manifest = json_decode( (string) file_get_contents( $path ), true );

		// The fatal-error handler parses this with plain json_decode and no
		// WordPress loaded, so the shape is a contract.
		$this->assertIsArray( $manifest );
		$this->assertSame( 'chg_test_manifest', $manifest['change_id'] );
		$this->assertIsArray( $manifest['files'] );
		$this->assertArrayHasKey( 'path', $manifest['files'][0] );
		$this->assertArrayHasKey( 'existed', $manifest['files'][0] );
		$this->assertArrayHasKey( 'index', $manifest['files'][0] );
		$this->assertArrayHasKey( 'sha256', $manifest['files'][0] );
	}

	public function test_a_manifest_restores_the_original_exactly(): void {
		$original = "<?php\n// the good version\n";
		file_put_contents( $this->target, $original );

		$this->snapshot->capture( 'chg_test_roundtrip', [ $this->target ] );

		// Simulate the change, then the recovery the drop-in performs.
		file_put_contents( $this->target, "<?php\n// the broken version\n{{{" );

		$dir      = $this->store->snapshot_dir( 'chg_test_roundtrip' );
		$manifest = json_decode( (string) file_get_contents( $dir . '/manifest.json' ), true );

		foreach ( $manifest['files'] as $file ) {
			$bytes = file_get_contents( $dir . '/files/' . $file['index'] . '.bin' );

			$this->assertSame( $file['sha256'], hash( 'sha256', (string) $bytes ) );
			file_put_contents( $file['path'], $bytes );
		}

		$this->assertSame( $original, file_get_contents( $this->target ) );
	}

	public function test_storage_directory_is_protected_from_the_web(): void {
		$root = $this->store->root();

		// Snapshots hold pre-edit copies of arbitrary theme and plugin files.
		// Serving those over HTTP would be a disclosure bug.
		$this->assertFileExists( $root . '/.htaccess' );
		$this->assertFileExists( $root . '/index.php' );
		$this->assertStringContainsString( 'denied', file_get_contents( $root . '/.htaccess' ) );
	}

	public function test_storage_directory_name_is_unguessable(): void {
		$this->assertMatchesRegularExpression( '#/wpsm-[a-zA-Z0-9]{12}$#', $this->store->root() );
	}

	public function test_forget_removes_a_snapshot(): void {
		file_put_contents( $this->target, "<?php\n// disposable\n" );
		$this->snapshot->capture( 'chg_test_forget', [ $this->target ] );

		$dir = $this->store->snapshot_dir( 'chg_test_forget' );
		$this->assertDirectoryExists( $dir );

		$this->store->forget( 'chg_test_forget' );

		$this->assertDirectoryDoesNotExist( $dir );
	}

	public function test_forget_refuses_to_escape_the_snapshot_tree(): void {
		$victim = WP_CONTENT_DIR . '/themes/demo-theme';

		// A traversal in a change id must not turn cleanup into a recursive
		// delete of something real.
		$this->store->forget( '../../../themes/demo-theme' );

		$this->assertDirectoryExists( $victim );
	}

	public function test_pending_mirror_round_trips(): void {
		$entry = [
			'chg_mirror' => [
				'change_id' => 'chg_mirror',
				'opened_at' => time(),
				'files'     => [ [ 'index' => 0, 'path' => $this->target, 'existed' => true ] ],
			],
		];

		$this->store->write_pending( $entry );

		$this->assertSame( $entry, $this->store->read_pending() );

		$this->store->write_pending( [] );
		$this->assertSame( [], $this->store->read_pending() );
	}
}
