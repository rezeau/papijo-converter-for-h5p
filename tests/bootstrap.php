<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	throw new RuntimeException( 'The characterization suite must run from the PHP CLI.' );
}

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'PAPIJO_TEST_TEMP_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'papijo-converter-characterization-' . getmypid() . DIRECTORY_SEPARATOR );

if ( ! is_dir( PAPIJO_TEST_TEMP_DIR ) && ! mkdir( PAPIJO_TEST_TEMP_DIR, 0777, true ) && ! is_dir( PAPIJO_TEST_TEMP_DIR ) ) {
	throw new RuntimeException( 'Could not create the test temporary directory.' );
}

function add_action( string $hook_name, $callback ): void {
	// Loading the plugin registers hooks; characterization tests call methods directly.
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return $text;
}

function wp_json_encode( $value, int $flags = 0, int $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function wp_generate_uuid4(): string {
	static $counter = 0;
	$counter++;
	return sprintf( '00000000-0000-4000-8000-%012d', $counter );
}

function get_temp_dir(): string {
	return PAPIJO_TEST_TEMP_DIR;
}

function wp_is_writable( string $path ): bool {
	return is_writable( $path );
}

function wp_unique_filename( string $directory, string $filename ): string {
	$candidate = $filename;
	$number    = 1;
	while ( file_exists( rtrim( $directory, '/\\' ) . DIRECTORY_SEPARATOR . $candidate ) ) {
		$path_info = pathinfo( $filename );
		$extension = isset( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';
		$candidate = $path_info['filename'] . '-' . $number . $extension;
		$number++;
	}
	return $candidate;
}

function trailingslashit( string $value ): string {
	return rtrim( $value, '/\\' ) . '/';
}

function wp_delete_file( string $path ): bool {
	return ! file_exists( $path ) || unlink( $path );
}

function sanitize_file_name( string $filename ): string {
	$filename = preg_replace( '/[^A-Za-z0-9._-]+/', '-', $filename );
	return trim( (string) $filename, '-' );
}

require_once dirname( __DIR__ ) . '/papijo-converter-for-h5p/papijo-converter-for-h5p.php';

final class PendingTest extends RuntimeException {}

final class CharacterizationSuite {
	private int $passed = 0;
	private int $failed = 0;
	private int $skipped = 0;

	public function test( string $name, callable $test ): void {
		try {
			$test();
			$this->passed++;
			echo "PASS  {$name}\n";
		} catch ( PendingTest $exception ) {
			$this->skipped++;
			echo "SKIP  {$name}: {$exception->getMessage()}\n";
		} catch ( Throwable $exception ) {
			$this->failed++;
			echo "FAIL  {$name}\n      {$exception->getMessage()}\n";
		}
	}

	public function pending( string $reason ): void {
		throw new PendingTest( $reason );
	}

	public function assertSame( $expected, $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			$details = $message !== '' ? $message . PHP_EOL : '';
			$details .= 'Expected: ' . var_export( $expected, true ) . PHP_EOL . 'Actual:   ' . var_export( $actual, true );
			throw new RuntimeException( $details );
		}
	}

	public function assertTrue( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	public function invoke( object $object, string $method, array $arguments = [] ) {
		$reflection = new ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $object, $arguments );
	}

	public function finish(): int {
		$total = $this->passed + $this->failed + $this->skipped;
		echo "\nTests: {$total}, Passed: {$this->passed}, Failed: {$this->failed}, Skipped: {$this->skipped}\n";
		return $this->failed === 0 ? 0 : 1;
	}
}

