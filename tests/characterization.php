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
	'standalone MarkTheWords conversion migrates strict boolean score settings',
	static function () use ( $suite, $converter ): void {
		$true_content = array(
			'textField' => 'Mark *these* words.',
			'behaviour' => array(
				'showScorePoints'    => true,
				'submitAnswerButton' => false,
				'unrelated'          => 'preserve',
			),
			'metadata' => array( 'preserve' => true ),
		);
		$false_content = array(
			'textField' => 'Mark *those* words.',
			'behaviour' => array( 'showScorePoints' => false ),
		);
		$true_result  = convert_characterization_package( $suite, $converter, 'H5P.MarkTheWords', 1, 11, $true_content );
		$false_result = convert_characterization_package( $suite, $converter, 'H5P.MarkTheWords', 1, 11, $false_content );

		$suite->assertSame( 'H5P.MarkTheWordsPapiJo', $true_result['manifest']['mainLibrary'] );
		$suite->assertSame( 2, $true_result['manifest']['preloadedDependencies'][0]['minorVersion'] );
		$suite->assertSame( 'ticksAndScorepoints', $true_result['content']['behaviour']['displayTicksMode'] );
		$suite->assertTrue( ! array_key_exists( 'showScorePoints', $true_result['content']['behaviour'] ), 'Legacy true setting should be removed.' );
		$suite->assertSame( false, $true_result['content']['behaviour']['submitAnswerButton'], 'Existing submitAnswerButton should be preserved.' );
		$suite->assertSame( 'preserve', $true_result['content']['behaviour']['unrelated'], 'Unrelated behavior fields should be preserved.' );
		$suite->assertSame( $true_content['metadata'], $true_result['content']['metadata'], 'Unrelated content fields should be preserved.' );
		$suite->assertSame( 'ticksOnly', $false_result['content']['behaviour']['displayTicksMode'] );
		$suite->assertTrue( ! array_key_exists( 'showScorePoints', $false_result['content']['behaviour'] ), 'Legacy false setting should be removed.' );
		$suite->assertTrue( ! array_key_exists( 'submitAnswerButton', $false_result['content']['behaviour'] ), 'Absent submitAnswerButton should not be added.' );
		$suite->assertSame( array( 'displayTicksMode' ), array_keys( $false_result['content']['behaviour'] ), 'No other PapiJo defaults should be injected.' );
	}
);

$suite->test(
	'QuestionSet nested conversion follows the exact whitelist',
	static function () use ( $suite, $converter ): void {
		$dialog_params = array(
			'dialogs' => array(
				array(
					'image'        => array( 'path' => 'images/nested.png' ),
					'imageAltText' => 'Nested card',
					'audio'        => array( array( 'path' => 'audio/nested.mp3' ) ),
				),
			),
		);
		$content = array(
			'questions' => array(
				array( 'library' => 'H5P.AdvancedBlanks 1.2', 'params' => array( 'marker' => 'advanced' ) ),
				array( 'library' => 'H5P.DragQuestion 1.13', 'params' => array( 'marker' => 'drag-question' ) ),
				array( 'library' => 'H5P.DragText 1.10', 'params' => array( 'textField' => '*answer:tip*' ) ),
				array( 'library' => 'H5P.MarkTheWords 1.11', 'params' => array( 'textField' => 'Mark *this*.', 'behaviour' => array( 'showScorePoints' => false, 'unrelated' => 7 ) ) ),
				array( 'library' => 'H5P.MultiMediaChoice 0.3', 'params' => array( 'marker' => 'multimedia' ) ),
				array( 'library' => 'H5P.Dialogcards 1.9', 'params' => $dialog_params ),
				array( 'library' => 'H5P.Unrelated 2.4', 'params' => array( 'marker' => 'unrelated' ) ),
			),
		);

		$suite->invoke( $converter, 'convert_question_set_content', array( &$content ) );

		$suite->assertSame( 'H5P.AdvancedBlanksPapiJo 1.4', $content['questions'][0]['library'] );
		$suite->assertSame( array( 'marker' => 'advanced' ), $content['questions'][0]['params'] );
		$suite->assertSame( 'H5P.DragQuestionPapiJo 1.14', $content['questions'][1]['library'] );
		$suite->assertSame( array( 'marker' => 'drag-question' ), $content['questions'][1]['params'] );
		$suite->assertSame( 'H5P.DragTextPapiJo 1.3', $content['questions'][2]['library'] );
		$suite->assertSame( '*answer::tip*', $content['questions'][2]['params']['textField'] );
		$suite->assertSame( 'H5P.MarkTheWordsPapiJo 1.2', $content['questions'][3]['library'] );
		$suite->assertSame( 'Mark *this*.', $content['questions'][3]['params']['textField'], 'Nested unrelated params should remain unchanged.' );
		$suite->assertSame( array( 'unrelated' => 7, 'displayTicksMode' => 'ticksOnly' ), $content['questions'][3]['params']['behaviour'] );
		$suite->assertTrue( ! array_key_exists( 'submitAnswerButton', $content['questions'][3]['params']['behaviour'] ), 'Nested conversion should not add submitAnswerButton.' );
		$suite->assertSame( 'H5P.MultiMediaChoicePapiJo 0.4', $content['questions'][4]['library'] );
		$suite->assertSame( array( 'marker' => 'multimedia' ), $content['questions'][4]['params'] );
		$suite->assertSame( 'H5P.Dialogcards 1.9', $content['questions'][5]['library'] );
		$suite->assertSame( $dialog_params, $content['questions'][5]['params'], 'Nested Dialogcards params should remain untouched.' );
		$suite->assertSame( 'H5P.Unrelated 2.4', $content['questions'][6]['library'] );
		$suite->assertSame( array( 'marker' => 'unrelated' ), $content['questions'][6]['params'] );
	}
);

$suite->test(
	'QuestionSet nested whitelist is explicit and QuestionSet target remains 1.23',
	static function () use ( $suite, $class ): void {
		$suite->assertSame(
			array(
				'H5P.AdvancedBlanks',
				'H5P.DragQuestion',
				'H5P.DragText',
				'H5P.MarkTheWords',
				'H5P.MultiMediaChoice',
			),
			$class->getConstant( 'QUESTION_SET_CHILD_LIBRARIES' )
		);

		$libraries = $class->getConstant( 'LIBRARIES' );
		$suite->assertSame( 'H5P.QuestionSetPapiJo', $libraries['H5P.QuestionSet']['target'] );
		$suite->assertSame( 1, $libraries['H5P.QuestionSet']['major'] );
		$suite->assertSame( 23, $libraries['H5P.QuestionSet']['minor'] );
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
	'MarkTheWords migration preserves explicit modes and handles missing or invalid legacy values',
	static function () use ( $suite, $converter ): void {
		$existing_mode = array(
			'textField' => 'Keep content.',
			'behaviour' => array(
				'showScorePoints'    => true,
				'displayTicksMode'   => array( 'custom' => 'value' ),
				'submitAnswerButton' => 'keep-exactly',
			),
		);
		$non_boolean = array(
			'behaviour' => array( 'showScorePoints' => 1, 'unrelated' => 'preserve' ),
		);
		$missing = array(
			'behaviour' => array( 'unrelated' => 'unchanged' ),
			'outside'   => 'preserve',
		);
		$missing_with_mode = array(
			'behaviour' => array( 'displayTicksMode' => 'existing-without-legacy' ),
		);

		$suite->invoke( $converter, 'convert_mark_the_words_content', array( &$existing_mode ) );
		$once = $existing_mode;
		$suite->invoke( $converter, 'convert_mark_the_words_content', array( &$existing_mode ) );
		$suite->invoke( $converter, 'convert_mark_the_words_content', array( &$non_boolean ) );
		$suite->invoke( $converter, 'convert_mark_the_words_content', array( &$missing ) );
		$suite->invoke( $converter, 'convert_mark_the_words_content', array( &$missing_with_mode ) );

		$suite->assertSame( array( 'custom' => 'value' ), $existing_mode['behaviour']['displayTicksMode'], 'Existing displayTicksMode values should be preserved exactly.' );
		$suite->assertSame( 'keep-exactly', $existing_mode['behaviour']['submitAnswerButton'], 'Existing submitAnswerButton values should be preserved exactly.' );
		$suite->assertTrue( ! array_key_exists( 'showScorePoints', $existing_mode['behaviour'] ), 'Legacy setting should be removed when a mode already exists.' );
		$suite->assertSame( $once, $existing_mode, 'Repeated migration should be idempotent.' );
		$suite->assertSame( array( 'unrelated' => 'preserve' ), $non_boolean['behaviour'], 'Non-boolean legacy values should be removed without deriving a mode.' );
		$suite->assertSame( array( 'behaviour' => array( 'unrelated' => 'unchanged' ), 'outside' => 'preserve' ), $missing, 'Missing legacy settings should not inject fields.' );
		$suite->assertSame( array( 'behaviour' => array( 'displayTicksMode' => 'existing-without-legacy' ) ), $missing_with_mode, 'Existing modes should remain unchanged when the legacy setting is absent.' );
		$suite->assertTrue( ! array_key_exists( 'submitAnswerButton', $missing['behaviour'] ), 'Missing submitAnswerButton should remain absent.' );
	}
);

$suite->test(
	'dependency replacement updates every matching occurrence and preserves unrelated fields',
	static function () use ( $suite, $converter, $class ): void {
		$libraries = $class->getConstant( 'LIBRARIES' );
		$manifest  = array(
			'preloadedDependencies' => array(
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 10, 'custom' => 'first' ),
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 9, 'custom' => 'second' ),
			),
			'dynamicDependencies' => array(
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 8 ),
			),
			'editorDependencies' => array(
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 7 ),
				array( 'machineName' => 'H5P.Unrelated', 'majorVersion' => 1, 'minorVersion' => 0 ),
			),
		);

		$replaced = $suite->invoke( $converter, 'replace_dependency', array( &$manifest, 'H5P.DragText', $libraries['H5P.DragText'] ) );

		$suite->assertSame( true, $replaced );
		$suite->assertSame( 'H5P.DragTextPapiJo', $manifest['preloadedDependencies'][0]['machineName'] );
		$suite->assertSame( 'first', $manifest['preloadedDependencies'][0]['custom'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $manifest['preloadedDependencies'][1]['machineName'] );
		$suite->assertSame( 'second', $manifest['preloadedDependencies'][1]['custom'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $manifest['dynamicDependencies'][0]['machineName'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $manifest['editorDependencies'][0]['machineName'] );
		$suite->assertSame( 1, $manifest['editorDependencies'][0]['majorVersion'] );
		$suite->assertSame( 3, $manifest['editorDependencies'][0]['minorVersion'] );
		$suite->assertSame( 'H5P.Unrelated', $manifest['editorDependencies'][1]['machineName'] );
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

$suite->test(
	'QuestionSet rewrites dependencies only for child source types actually converted',
	static function () use ( $suite, $converter ): void {
		$content = array(
			'questions' => array(
				array( 'library' => 'H5P.DragText 1.10', 'params' => array( 'textField' => '*answer:tip*' ) ),
				array( 'library' => 'H5P.MarkTheWords 1.11', 'params' => array( 'behaviour' => array( 'showScorePoints' => true ) ) ),
				array( 'library' => 'H5P.MultiMediaChoice 0.3', 'params' => array( 'marker' => 'no-dependency-present' ) ),
				array( 'library' => 'H5P.Dialogcards 1.9', 'params' => array( 'marker' => 'dialog-unchanged' ) ),
				array( 'library' => 'H5P.Unrelated 2.4', 'params' => array( 'marker' => 'unrelated-child' ) ),
			),
		);
		$manifest = array(
			'title'                 => 'QuestionSet dependency fixture',
			'mainLibrary'           => 'H5P.QuestionSet',
			'preloadedDependencies' => array(
				array( 'machineName' => 'H5P.QuestionSet', 'majorVersion' => 1, 'minorVersion' => 20, 'topLevel' => 'preserve' ),
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 10, 'occurrence' => 'first' ),
				array( 'machineName' => 'H5P.AdvancedBlanks', 'majorVersion' => 1, 'minorVersion' => 2, 'unused' => true ),
				array( 'machineName' => 'H5P.Dialogcards', 'majorVersion' => 1, 'minorVersion' => 9, 'nestedEligible' => false ),
				array( 'machineName' => 'H5P.Unrelated', 'majorVersion' => 2, 'minorVersion' => 4 ),
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 9, 'occurrence' => 'second' ),
			),
			'dynamicDependencies' => array(
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 8 ),
				array( 'machineName' => 'H5P.MarkTheWords', 'majorVersion' => 1, 'minorVersion' => 11, 'custom' => 'dynamic' ),
			),
			'editorDependencies' => array(
				array( 'machineName' => 'H5P.DragText', 'majorVersion' => 1, 'minorVersion' => 7 ),
				array( 'machineName' => 'H5P.MarkTheWords', 'majorVersion' => 1, 'minorVersion' => 10, 'custom' => 'editor' ),
			),
		);
		$result = convert_characterization_package( $suite, $converter, 'H5P.QuestionSet', 1, 20, $content, $manifest );

		$suite->assertSame( 'H5P.QuestionSetPapiJo', $result['manifest']['mainLibrary'] );
		$suite->assertSame( 'H5P.QuestionSetPapiJo', $result['manifest']['preloadedDependencies'][0]['machineName'] );
		$suite->assertSame( 23, $result['manifest']['preloadedDependencies'][0]['minorVersion'] );
		$suite->assertSame( 'preserve', $result['manifest']['preloadedDependencies'][0]['topLevel'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['preloadedDependencies'][1]['machineName'] );
		$suite->assertSame( 3, $result['manifest']['preloadedDependencies'][1]['minorVersion'] );
		$suite->assertSame( 'first', $result['manifest']['preloadedDependencies'][1]['occurrence'] );
		$suite->assertSame( 'H5P.AdvancedBlanks', $result['manifest']['preloadedDependencies'][2]['machineName'], 'Unused whitelisted dependencies should remain unchanged.' );
		$suite->assertSame( 'H5P.Dialogcards', $result['manifest']['preloadedDependencies'][3]['machineName'], 'Nested-ineligible Dialogcards dependencies should remain unchanged.' );
		$suite->assertSame( 'H5P.Unrelated', $result['manifest']['preloadedDependencies'][4]['machineName'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['preloadedDependencies'][5]['machineName'] );
		$suite->assertSame( 'second', $result['manifest']['preloadedDependencies'][5]['occurrence'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['dynamicDependencies'][0]['machineName'] );
		$suite->assertSame( 'H5P.MarkTheWordsPapiJo', $result['manifest']['dynamicDependencies'][1]['machineName'] );
		$suite->assertSame( 2, $result['manifest']['dynamicDependencies'][1]['minorVersion'] );
		$suite->assertSame( 'dynamic', $result['manifest']['dynamicDependencies'][1]['custom'] );
		$suite->assertSame( 'H5P.DragTextPapiJo', $result['manifest']['editorDependencies'][0]['machineName'] );
		$suite->assertSame( 'H5P.MarkTheWordsPapiJo', $result['manifest']['editorDependencies'][1]['machineName'] );
		$suite->assertSame( 'editor', $result['manifest']['editorDependencies'][1]['custom'] );

		$all_dependencies = array_merge(
			$result['manifest']['preloadedDependencies'],
			$result['manifest']['dynamicDependencies'],
			$result['manifest']['editorDependencies']
		);
		$dependency_names = array_column( $all_dependencies, 'machineName' );
		$suite->assertTrue( ! in_array( 'H5P.MultiMediaChoice', $dependency_names, true ), 'Missing source dependencies should not be added.' );
		$suite->assertTrue( ! in_array( 'H5P.MultiMediaChoicePapiJo', $dependency_names, true ), 'Missing target dependencies should not be added.' );

		$suite->assertSame( 'H5P.DragTextPapiJo 1.3', $result['content']['questions'][0]['library'] );
		$suite->assertSame( '*answer::tip*', $result['content']['questions'][0]['params']['textField'] );
		$suite->assertSame( 'H5P.MarkTheWordsPapiJo 1.2', $result['content']['questions'][1]['library'] );
		$suite->assertSame( array( 'displayTicksMode' => 'ticksAndScorepoints' ), $result['content']['questions'][1]['params']['behaviour'] );
		$suite->assertSame( 'H5P.MultiMediaChoicePapiJo 0.4', $result['content']['questions'][2]['library'] );
		$suite->assertSame( 'H5P.Dialogcards 1.9', $result['content']['questions'][3]['library'] );
		$suite->assertSame( array( 'marker' => 'dialog-unchanged' ), $result['content']['questions'][3]['params'] );
		$suite->assertSame( 'H5P.Unrelated 2.4', $result['content']['questions'][4]['library'] );
	}
);

function convert_characterization_package(
	CharacterizationSuite $suite,
	Papi_Jo_H5P_Converter $converter,
	string $source_library,
	int $major_version,
	int $minor_version,
	array $content,
	?array $manifest = null
): array {
	$source_path = create_characterization_source_package( $source_library, $major_version, $minor_version, $content, $manifest );
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
	array $content,
	?array $manifest = null
): string {
	$source_path = PAPIJO_TEST_TEMP_DIR . uniqid( 'source-', true ) . '.h5p';
	$zip         = new ZipArchive();
	if ( true !== $zip->open( $source_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'Could not create a source fixture.' );
	}

	if ( null === $manifest ) {
		$manifest = array(
			'title'                 => 'Characterization fixture',
			'mainLibrary'           => $source_library,
			'preloadedDependencies' => array(
				array( 'machineName' => $source_library, 'majorVersion' => $major_version, 'minorVersion' => $minor_version ),
			),
		);
	}
	$library_dir = $source_library . '-' . $major_version . '.' . $minor_version;
	$zip->addFromString( 'h5p.json', json_encode( $manifest, JSON_UNESCAPED_SLASHES ) );
	$zip->addFromString( 'content/content.json', json_encode( $content, JSON_UNESCAPED_SLASHES ) );
	$zip->addFromString( 'content/images/keep.txt', 'content asset' );
	$zip->addFromString( $library_dir . '/library.json', json_encode( array( 'machineName' => $source_library, 'title' => 'Default library' ) ) );
	$zip->addFromString( $library_dir . '/library.js', 'bundled library code' );
	$zip->close();

	return $source_path;
}

