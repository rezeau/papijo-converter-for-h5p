<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$suite = new CharacterizationSuite();
require __DIR__ . '/characterization.php';

$exit_code = $suite->finish();

if ( is_dir( PAPIJO_TEST_TEMP_DIR ) ) {
	$remaining = array_diff( scandir( PAPIJO_TEST_TEMP_DIR ) ?: array(), array( '.', '..' ) );
	if ( empty( $remaining ) ) {
		rmdir( PAPIJO_TEST_TEMP_DIR );
	}
}

exit( $exit_code );
