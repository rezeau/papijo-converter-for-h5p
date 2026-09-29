<?php
declare(strict_types=1);

/** @var CharacterizationSuite $suite */
$converter = new Papi_Jo_H5P_Converter();
$class     = new ReflectionClass( Papi_Jo_H5P_Converter::class );

$suite->test(
	'current mapping targets and versions are captured',
	static function () use ( $suite, $class ): void {
		$libraries = $class->getConstant( 'LIBRARIES' );
		$actual    = array();
		foreach ( $libraries as $source => $library ) {
			$actual[ $source ] = array( $library['target'], $library['major'], $library['minor'] );
		}

		$suite->assertSame(
			array(
				'H5P.AdvancedBlanks'   => array( 'H5P.AdvancedBlanksPapiJo', 1, 4 ),
				'H5P.Dialogcards'      => array( 'H5P.DialogcardsPapiJo', 1, 17 ),
				'H5P.DragQuestion'     => array( 'H5P.DragQuestionPapiJo', 1, 14 ),
				'H5P.DragText'         => array( 'H5P.DragTextPapiJo', 1, 1 ),
				'H5P.MarkTheWords'     => array( 'H5P.MarkTheWordsPapiJo', 1, 1 ),
				'H5P.MultiMediaChoice' => array( 'H5P.MultiMediaChoicePapiJo', 0, 4 ),
				'H5P.QuestionSet'      => array( 'H5P.QuestionSetPapiJo', 1, 21 ),
				'H5P.Timeline'         => array( 'H5P.NDLATimelinePapiJo', 0, 2 ),
			),
			$actual
		);
	}
);

$suite->test(
	'current standalone DragText conversion leaves content unchanged',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'taskDescription' => 'Keep: punctuation outside.',
			'textField'       => 'First *answer:tip* and *second::existing* with \\+good\\-bad.',
		);
		$result = convert_characterization_package( $suite, $converter, 'H5P.DragText', 1, 10, $content );

		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['mainLibrary'] );
		$suite->assertSame( 1, $result['manifest']['preloadedDependencies'][0]['majorVersion'] );
		$suite->assertSame( 1, $result['manifest']['preloadedDependencies'][0]['minorVersion'] );
		$suite->assertSame( $content, $result['content'] );
		$suite->assertTrue( ! $result['has_library_files'], 'Bundled library directories should be removed from converted packages.' );
		$suite->assertSame( $result['source_hash_before'], $result['source_hash_after'], 'The source package must remain untouched.' );
	}
);

$suite->test(
	'current standalone MarkTheWords conversion preserves parameters without injecting defaults',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'textField' => 'Mark *these* words.',
			'behaviour' => array(
				'showScorePoints'   => true,
				'displayTicksMode'  => 'alwaysShow',
				'submitAnswerButton' => false,
			),
		);
		$result = convert_characterization_package( $suite, $converter, 'H5P.MarkTheWords', 1, 11, $content );

		$suite->assertSame( 'H5P.MarkTheWordsPapiJo', $result['manifest']['mainLibrary'] );
		$suite->assertSame( $content, $result['content'] );
		$suite->assertTrue( ! array_key_exists( 'scorePointsMode', $result['content']['behaviour'] ), 'No PapiJo defaults should be injected.' );
	}
);

$suite->test(
	'current QuestionSet content replacement is recursive and includes Dialogcards',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'questions' => array(
				array( 'library' => 'H5P.DragText 1.10', 'params' => array( 'textField' => '*answer:tip*' ) ),
				array( 'library' => 'H5P.Dialogcards 1.9', 'params' => array() ),
				array( 'library' => 'H5P.Timeline 1.1', 'params' => array() ),
			),
		);

		$suite->invoke( $converter, 'convert_question_set_content', array( &$content ) );

		$suite->assertSame( 'H5P.DragTextPapiJo 1.1', $content['questions'][0]['library'] );
		$suite->assertSame( '*answer:tip*', $content['questions'][0]['params']['textField'] );
		$suite->assertSame( 'H5P.DialogcardsPapiJo 1.17', $content['questions'][1]['library'] );
		$suite->assertSame( 'H5P.Timeline 1.1', $content['questions'][2]['library'] );
	}
);

$suite->test(
	'current dependency replacement changes only the first matching occurrence',
	static function () use ( $suite, $converter, $class ): void {
		$libraries = $class->getConstant( 'LIBRARIES' );
		$manifest  = array(
			'preloadedDependencies' => array( array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 10 ) ),
			'dynamicDependencies'   => array( array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 10 ) ),
			'editorDependencies'    => array( array( 'machineName' => 'H5P.Unrelated', 'majorVersion' => 1, 'minorVersion' => 0 ) ),
		);

		$replaced = $suite->invoke( $converter, 'replace_dependency', array( &$manifest, 'H5P.DragText', $libraries['H5P.DragText'] ) );

		$suite->assertSame( true, $replaced );
		$suite->assertSame( 'H5P.DragTextPapiJo', $manifest['preloadedDependencies'][0]['machineName'] );
		$suite->assertSame( 'H5P.DragText', $manifest['dynamicDependencies'][0]['machineName'] );
		$suite->assertSame( 'H5P.Unrelated', $manifest['editorDependencies'][0]['machineName'] );
	}
);

$suite->test(
	'current standalone Dialogcards migration wraps image and audio media',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'dialogs' => array(
				array(
					'image'        => array( 'path' => 'images/card.png' ),
					'imageAltText' => 'Card',
					'audio'        => array( array( 'path' => 'audio/card.mp3' ) ),
				),
			),
		);

		$suite->invoke( $converter, 'convert_dialog_cards_content', array( &$content ) );

		$suite->assertSame(
			array( 'image' => array( 'path' => 'images/card.png' ), 'imageAltText' => 'Card' ),
			$content['dialogs'][0]['imageMedia']
		);
		$suite->assertSame( array( 'audio' => array( array( 'path' => 'audio/card.mp3' ) ) ), $content['dialogs'][0]['audioMedia'] );
		$suite->assertTrue( ! isset( $content['dialogs'][0]['image'], $content['dialogs'][0]['audio'] ), 'Legacy media fields should be removed.' );
	}
);

$suite->test(
	'current Timeline conversion produces NDLA-style content',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'timeline' => array(
				'headline' => 'History',
				'text'     => '<div>Introduction</div>',
				'language' => 'fr',
				'date'     => array( array( 'headline' => 'Event', 'text' => 'Details', 'startDate' => '2020, 01, 02' ) ),
			),
		);

		$converted = $suite->invoke( $converter, 'convert_timeline_content', array( $content ) );

		$suite->assertSame( true, $converted['showTitleSlide'] );
		$suite->assertSame( 'fr', $converted['language'] );
		$suite->assertSame( '<p>Introduction</p>', $converted['titleSlide']['description']['params']['text'] );
		$suite->assertSame( '2020-01-02', $converted['timelineItems'][0]['startDate'] );
	}
);

$suite->test(
	'current converted filename behavior is captured',
	static function () use ( $suite, $converter ): void {
		$actual = $suite->invoke( $converter, 'build_converted_filename', array( 'My Lesson.h5p', 'H5P.DragTextPapiJo' ) );
		$suite->assertSame( 'My-Lesson-dragtext-papijo.h5p', $actual );
	}
);

foreach (
	array(
		'future DragText target 1.3 and textual-tip migration' => 'Pending converter synchronization; current target is 1.1 and content is unchanged.',
		'future MarkTheWords target 1.2 and behavior migration' => 'Pending converter synchronization; current target is 1.1 and content is unchanged.',
		'future QuestionSet target 1.23 and exact nested whitelist' => 'Pending converter synchronization; current target is 1.21 and nested Dialogcards is converted.',
		'future dependency rewrite across all sections for converted children only' => 'Pending converter synchronization; current replacement stops at the first match.',
		'future Timeline rejection without generated output' => 'Pending converter synchronization; Timeline currently generates converted content.',
	) as $name => $reason
) {
	$suite->test(
		$name,
		static function () use ( $suite, $reason ): void {
			$suite->pending( $reason );
		}
	);
}

function convert_characterization_package(
	CharacterizationSuite $suite,
	Papi_Jo_H5P_Converter $converter,
	string $source_library,
	int $major_version,
	int $minor_version,
	array $content
): array {
	$source_path = PAPIJO_TEST_TEMP_DIR . uniqid( 'source-', true ) . '.h5p';
	$zip         = new ZipArchive();
	if ( true !== $zip->open( $source_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'Could not create a source fixture.' );
	}

	$manifest = array(
		'title'                 => 'Characterization fixture',
		'mainLibrary'           => $source_library,
		'preloadedDependencies' => array(
			array( 'machineName' => $source_library, 'majorVersion' => $major_version, 'minorVersion' => $minor_version ),
		),
	);
	$library_dir = $source_library . '-' . $major_version . '.' . $minor_version;
	$zip->addFromString( 'h5p.json', json_encode( $manifest, JSON_UNESCAPED_SLASHES ) );
	$zip->addFromString( 'content/content.json', json_encode( $content, JSON_UNESCAPED_SLASHES ) );
	$zip->addFromString( 'content/images/keep.txt', 'content asset' );
	$zip->addFromString( $library_dir . '/library.json', json_encode( array( 'machineName' => $source_library, 'title' => 'Default library' ) ) );
	$zip->addFromString( $library_dir . '/library.js', 'bundled library code' );
	$zip->close();

	$source_hash_before = hash_file( 'sha256', $source_path );
	$warnings           = array();
	$file               = array( 'path' => $source_path, 'name' => basename( $source_path ) );
	$output_path        = $suite->invoke( $converter, 'convert_file', array( $file, &$warnings ) );

	try {
		$suite->assertSame( array(), $warnings );
		$suite->assertTrue( is_string( $output_path ) && is_file( $output_path ), 'Conversion should create an output package.' );

		$output_zip = new ZipArchive();
		$suite->assertSame( true, $output_zip->open( $output_path ) );
		$output_manifest = json_decode( (string) $output_zip->getFromName( 'h5p.json' ), true );
		$output_content  = json_decode( (string) $output_zip->getFromName( 'content/content.json' ), true );
		$has_library_files = false;
		for ( $index = 0; $index < $output_zip->numFiles; $index++ ) {
			$name = $output_zip->getNameIndex( $index );
			if ( is_string( $name ) && str_starts_with( $name, $library_dir . '/' ) ) {
				$has_library_files = true;
			}
		}
		$output_zip->close();

		return array(
			'manifest'          => $output_manifest,
			'content'           => $output_content,
			'has_library_files' => $has_library_files,
			'source_hash_before' => $source_hash_before,
			'source_hash_after' => hash_file( 'sha256', $source_path ),
		);
	} finally {
		if ( is_string( $output_path ) && is_file( $output_path ) ) {
			unlink( $output_path );
		}
		if ( is_file( $source_path ) ) {
			unlink( $source_path );
		}
	}
}

