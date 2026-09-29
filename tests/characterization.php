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
				'H5P.DragText'         => array( 'H5P.DragTextPapiJo', 1, 3 ),
				'H5P.MarkTheWords'     => array( 'H5P.MarkTheWordsPapiJo', 1, 2 ),
				'H5P.MultiMediaChoice' => array( 'H5P.MultiMediaChoicePapiJo', 0, 4 ),
				'H5P.QuestionSet'      => array( 'H5P.QuestionSetPapiJo', 1, 23 ),
			),
			$actual
		);
	}
);

$suite->test(
	'standalone DragText conversion migrates textual tips without changing unrelated content',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'taskDescription' => 'Keep: punctuation outside.',
			'textField'       => 'Prompt: choose *browser:What type of program is Chrome?*, *engine::Already migrated*, and *plain*. Check *plus:Tip\\+Correct: yes* and *minus:Tip\\-Incorrect: no*.',
			'extra'           => array( 'preserve' => true ),
		);
		$result = convert_characterization_package( $suite, $converter, 'H5P.DragText', 1, 10, $content );

		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['mainLibrary'] );
		$suite->assertSame( 1, $result['manifest']['preloadedDependencies'][0]['majorVersion'] );
		$suite->assertSame( 3, $result['manifest']['preloadedDependencies'][0]['minorVersion'] );
		$suite->assertSame(
			'Prompt: choose *browser::What type of program is Chrome?*, *engine::Already migrated*, and *plain*. Check *plus::Tip\\+Correct: yes* and *minus::Tip\\-Incorrect: no*.',
			$result['content']['textField'],
			'Only single-colon textual tips inside answer expressions should be migrated.'
		);
		$suite->assertSame( $content['taskDescription'], $result['content']['taskDescription'], 'Ordinary outside colons must remain unchanged.' );
		$suite->assertSame( $content['extra'], $result['content']['extra'], 'Unrelated fields must remain unchanged.' );
		$suite->assertSame( array_keys( $content ), array_keys( $result['content'] ), 'No PapiJo defaults or unrelated fields should be injected.' );
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
		$suite->assertSame( 2, $result['manifest']['preloadedDependencies'][0]['minorVersion'] );
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

		$suite->assertSame( 'H5P.DragTextPapiJo 1.3', $content['questions'][0]['library'] );
		$suite->assertSame( '*answer::tip*', $content['questions'][0]['params']['textField'] );
		$suite->assertSame( 'H5P.DialogcardsPapiJo 1.17', $content['questions'][1]['library'] );
		$suite->assertSame( 'H5P.Timeline 1.1', $content['questions'][2]['library'] );
	}
);

$suite->test(
	'DragText textual-tip migration is idempotent and preserves escaped feedback',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'textField' => 'Outside: unchanged *first:tip\\+Good: yes\\-Bad: no* / *second::ready*.',
		);

		$suite->invoke( $converter, 'convert_drag_text_content', array( &$content ) );
		$once = $content;
		$suite->invoke( $converter, 'convert_drag_text_content', array( &$content ) );

		$suite->assertSame(
			'Outside: unchanged *first::tip\\+Good: yes\\-Bad: no* / *second::ready*.',
			$content['textField'],
			'Feedback markers and feedback colons should be preserved exactly.'
		);
		$suite->assertSame( $once, $content, 'Running the migration twice must not change already migrated text.' );
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
	'Timeline conversion helpers are removed',
	static function () use ( $suite, $class ): void {
		foreach (
			array(
				'convert_timeline_content',
				'convert_timeline_slide',
				'timeline_advanced_text',
				'normalize_timeline_date',
				'timeline_html_block',
			) as $method
		) {
			$suite->assertSame( false, $class->hasMethod( $method ), $method . ' should not remain in production code.' );
		}
	}
);

$suite->test(
	'Timeline packages are unsupported and do not generate converted output',
	static function () use ( $suite, $converter ): void {
		$source_path = create_characterization_source_package(
			'H5P.Timeline',
			1,
			1,
			array( 'timeline' => array( 'headline' => 'History' ) )
		);
		$warnings = array();
		$file     = array( 'path' => $source_path, 'name' => basename( $source_path ) );

		try {
			$supported = $suite->invoke( $converter, 'get_supported_source_type', array( $source_path ) );
			$output    = $suite->invoke( $converter, 'convert_file', array( $file, &$warnings ) );

			$suite->assertSame( array(), $supported );
			$suite->assertSame( '', $output );
			$suite->assertSame( array( basename( $source_path ) . ': not a supported source H5P package.' ), $warnings );
		} finally {
			if ( is_file( $source_path ) ) {
				unlink( $source_path );
			}
		}
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
		'future MarkTheWords behavior migration' => 'Target 1.2 is synchronized; content migration remains pending and content is unchanged.',
		'future QuestionSet exact nested whitelist' => 'Target 1.23 is synchronized; nested Dialogcards is still converted.',
		'future dependency rewrite across all sections for converted children only' => 'Pending converter synchronization; current replacement stops at the first match.',
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
	$source_path = create_characterization_source_package( $source_library, $major_version, $minor_version, $content );
	$source_hash_before = hash_file( 'sha256', $source_path );
	$warnings           = array();
	$file               = array( 'path' => $source_path, 'name' => basename( $source_path ) );
	$output_path        = $suite->invoke( $converter, 'convert_file', array( $file, &$warnings ) );
	$library_dir        = $source_library . '-' . $major_version . '.' . $minor_version;

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
			'manifest'           => $output_manifest,
			'content'            => $output_content,
			'has_library_files'  => $has_library_files,
			'source_hash_before' => $source_hash_before,
			'source_hash_after'  => hash_file( 'sha256', $source_path ),
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

function create_characterization_source_package(
	string $source_library,
	int $major_version,
	int $minor_version,
	array $content
): string {
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

	return $source_path;
}

