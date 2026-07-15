<?php
/**
 * Plugin Name: Papijo Package Converter for H5P
 * Description: Converts supported H5P packages into their matching Papi Jo content types. Use it from Tools > Papijo Package Converter for H5P.
 * Version: 2.3.0
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * Author: Joseph Rézeau (Papi Jo)
 * Author URI: https://www.rezeau.org/
 * License: GPL-2.0-or-later
 * Text Domain: papijo-converter-for-h5p
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Papi_Jo_H5P_Converter {
	private const VERSION      = '2.3.0';
	private const MENU_SLUG    = 'papijo-converter-for-h5p';
	private const ACTION       = 'papi_jo_h5p_converter_convert';
	private const NONCE_ACTION = 'papi_jo_h5p_converter_convert';
	private const DOWNLOAD_COOKIE = 'papi_jo_h5p_converter_download_ready';

	private const LIBRARIES = array(
		'H5P.AdvancedBlanks'  => array(
			'label'        => 'Complex fill the blanks',
			'target'       => 'H5P.AdvancedBlanksPapiJo',
			'target_label' => 'AdvancedBlanks Papi Jo',
			'major'        => 1,
			'minor'        => 4,
		),
		'H5P.Dialogcards'     => array(
			'label'        => 'Dialog Cards',
			'target'       => 'H5P.DialogcardsPapiJo',
			'target_label' => 'DialogCards Papi Jo',
			'major'        => 1,
			'minor'        => 17,
		),
		'H5P.DragQuestion'    => array(
			'label'        => 'Drag and Drop',
			'target'       => 'H5P.DragQuestionPapiJo',
			'target_label' => 'DragQuestion Papi Jo',
			'major'        => 1,
			'minor'        => 14,
		),
		'H5P.DragText'        => array(
			'label'        => 'Drag the Words',
			'target'       => 'H5P.DragTextPapiJo',
			'target_label' => 'DragText Papi Jo',
			'major'        => 1,
			'minor'        => 1,
		),
		'H5P.MarkTheWords'    => array(
			'label'        => 'Mark the Words',
			'target'       => 'H5P.MarkTheWordsPapiJo',
			'target_label' => 'MarkTheWords Papi Jo',
			'major'        => 1,
			'minor'        => 1,
		),
		'H5P.MultiMediaChoice' => array(
			'label'        => 'Multimedia Choice',
			'target'       => 'H5P.MultiMediaChoicePapiJo',
			'target_label' => 'MultiMediaChoice Papi Jo',
			'major'        => 0,
			'minor'        => 4,
		),
		'H5P.QuestionSet'     => array(
			'label'        => 'Question Set',
			'target'       => 'H5P.QuestionSetPapiJo',
			'target_label' => 'QuestionSet Papi Jo',
			'major'        => 1,
			'minor'        => 21,
		),
		'H5P.Timeline'        => array(
			'label'        => 'Timeline',
			'target'       => 'H5P.NDLATimelinePapiJo',
			'target_label' => 'NDLA Timeline Papi Jo',
			'major'        => 0,
			'minor'        => 2,
			'dependency'   => 'TimelineJS',
		),
	);

	/**
	 * @var string
	 */
	private $page_hook = '';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_conversion_download' ) );
	}

	public function register_admin_page(): void {
		$this->page_hook = add_management_page(
			esc_html__( 'Papijo Package Converter for H5P', 'papijo-converter-for-h5p' ),
			esc_html__( 'Papijo Package Converter for H5P', 'papijo-converter-for-h5p' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_register_style( 'papijo-converter-for-h5p-admin', false, array(), self::VERSION );
		wp_enqueue_style( 'papijo-converter-for-h5p-admin' );
		wp_add_inline_style(
			'papijo-converter-for-h5p-admin',
			'.h5p-exporter-status{max-width:900px}.h5p-exporter-actions{display:flex;gap:8px;align-items:center;margin:16px 0}.h5p-exporter-progress{display:none;max-width:900px;margin:10px 0}.h5p-exporter-progress progress{width:100%;height:16px}.h5p-exporter-table td,.h5p-exporter-table th{vertical-align:middle}.h5p-exporter-muted{color:#646970}.h5p-exporter-badge{display:inline-block;padding:2px 7px;border:1px solid #c3c4c7;border-radius:999px;background:#fff;font-size:12px}.h5p-exporter-empty{padding:24px;background:#fff;border:1px solid #c3c4c7;max-width:900px}.h5p-exporter-path{font-family:Consolas,Monaco,monospace}.h5p-exporter-warning{max-width:900px}'
		);

		wp_register_script( 'papijo-converter-for-h5p-admin', false, array(), self::VERSION, true );
		wp_enqueue_script( 'papijo-converter-for-h5p-admin' );
		wp_add_inline_script(
			'papijo-converter-for-h5p-admin',
			<<<'JS'
(function(){
	var all = document.getElementById('h5p-exporter-select-all');
	var form = document.querySelector('.h5p-exporter-form');

	if (all) {
		all.addEventListener('change', function(){
			document.querySelectorAll('.h5p-exporter-file-check').forEach(function(box){
				box.checked = all.checked;
			});
		});
	}

	if (!form) {
		return;
	}

	function cookieValue(name) {
		var escaped = name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&');
		var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function clearCookie(name) {
		document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
	}

	function setButtonsDisabled(disabled) {
		form.querySelectorAll('button[type="submit"]').forEach(function(button){
			button.disabled = disabled;
		});
	}

	form.addEventListener('submit', function(event){
		form.querySelectorAll('.h5p-exporter-runtime-field').forEach(function(field){
			field.remove();
		});

		if (event.submitter && event.submitter.name) {
			var clicked = document.createElement('input');
			clicked.type = 'hidden';
			clicked.name = event.submitter.name;
			clicked.value = event.submitter.value || '1';
			clicked.className = 'h5p-exporter-runtime-field';
			form.appendChild(clicked);
		}

		var token = String(Date.now()) + '-' + Math.random().toString(36).slice(2);
		var tokenField = document.createElement('input');
		tokenField.type = 'hidden';
		tokenField.name = 'h5p_download_token';
		tokenField.value = token;
		tokenField.className = 'h5p-exporter-runtime-field';
		form.appendChild(tokenField);

		var progress = document.getElementById('h5p-exporter-progress');
		if (progress) {
			progress.style.display = 'block';
		}
		setButtonsDisabled(true);

		var checks = 0;
		var timer = window.setInterval(function(){
			checks++;
			if (cookieValue('papi_jo_h5p_converter_download_ready') === token) {
				window.clearInterval(timer);
				clearCookie('papi_jo_h5p_converter_download_ready');
				if (progress) {
					progress.style.display = 'none';
				}
				setButtonsDisabled(false);
			} else if (checks > 1200) {
				window.clearInterval(timer);
				setButtonsDisabled(false);
			}
		}, 500);
	});
}());
JS
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to convert H5P content.', 'papijo-converter-for-h5p' ) );
		}

		$exports_dir = $this->get_exports_directory();
		$files       = $this->get_export_files();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Papijo Package Converter for H5P', 'papijo-converter-for-h5p' ); ?></h1>
			<p class="description h5p-exporter-status">
				<?php esc_html_e( 'Extract supported default H5P packages from this WordPress site and download converted Papi Jo packages. Original H5P files are not changed.', 'papijo-converter-for-h5p' ); ?>
			</p>
			<p class="h5p-exporter-muted">
				<?php esc_html_e( 'Scanning:', 'papijo-converter-for-h5p' ); ?>
				<span class="h5p-exporter-path"><?php echo esc_html( $exports_dir ); ?></span>
			</p>

			<?php if ( ! is_dir( $exports_dir ) ) : ?>
				<div class="notice notice-warning h5p-exporter-warning">
					<p>
						<?php
						printf(
							/* translators: %s: exports directory path. */
							esc_html__( 'The H5P exports folder does not exist yet: %s', 'papijo-converter-for-h5p' ),
							esc_html( $exports_dir )
						);
						?>
					</p>
				</div>
			<?php elseif ( ! is_readable( $exports_dir ) ) : ?>
				<div class="notice notice-error h5p-exporter-warning">
					<p>
						<?php
						printf(
							/* translators: %s: exports directory path. */
							esc_html__( 'The H5P exports folder is not readable: %s', 'papijo-converter-for-h5p' ),
							esc_html( $exports_dir )
						);
						?>
					</p>
				</div>
			<?php elseif ( ! class_exists( 'ZipArchive' ) ) : ?>
				<div class="notice notice-error h5p-exporter-warning">
					<p><?php esc_html_e( 'The PHP Zip extension is required to inspect and convert .h5p packages.', 'papijo-converter-for-h5p' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $files ) ) : ?>
				<div class="h5p-exporter-empty">
					<p><?php esc_html_e( 'No supported default .h5p export packages were found in the H5P exports folder.', 'papijo-converter-for-h5p' ); ?></p>
					<p class="h5p-exporter-muted"><?php esc_html_e( 'Supported source types: Complex fill the blanks, Dialog Cards, Drag and Drop, Drag the Words, Mark the Words, Multimedia Choice, Question Set, and Timeline.', 'papijo-converter-for-h5p' ); ?></p>
				</div>
			<?php else : ?>
				<form class="h5p-exporter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>

					<div class="h5p-exporter-actions">
						<button type="submit" class="button button-primary" name="convert_selected" value="1">
							<?php esc_html_e( 'Convert Selected to ZIP', 'papijo-converter-for-h5p' ); ?>
						</button>
						<button type="submit" class="button" name="convert_all" value="1">
							<?php esc_html_e( 'Convert All to ZIP', 'papijo-converter-for-h5p' ); ?>
						</button>
						<span class="h5p-exporter-muted">
							<?php
							printf(
								/* translators: %d: number of supported H5P export packages. */
								esc_html( _n( '%d source package available', '%d source packages available', count( $files ), 'papijo-converter-for-h5p' ) ),
								count( $files )
							);
							?>
						</span>
					</div>
					<div id="h5p-exporter-progress" class="h5p-exporter-progress" aria-live="polite">
						<progress></progress>
						<p class="description"><?php esc_html_e( 'Converting selected H5P packages. Keep this tab open until the ZIP download starts.', 'papijo-converter-for-h5p' ); ?></p>
					</div>

					<table class="widefat striped h5p-exporter-table">
						<thead>
							<tr>
								<td class="manage-column check-column">
									<input id="h5p-exporter-select-all" type="checkbox" aria-label="<?php esc_attr_e( 'Select all H5P packages', 'papijo-converter-for-h5p' ); ?>">
								</td>
								<th scope="col"><?php esc_html_e( 'Source Package', 'papijo-converter-for-h5p' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Source H5P Type', 'papijo-converter-for-h5p' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Converted Output', 'papijo-converter-for-h5p' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Size', 'papijo-converter-for-h5p' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Modified', 'papijo-converter-for-h5p' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $files as $file ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input class="h5p-exporter-file-check" type="checkbox" name="file_tokens[]" value="<?php echo esc_attr( $file['token'] ); ?>" aria-label="<?php echo esc_attr( sprintf(
											/* translators: %s: H5P package filename. */
											esc_attr__( 'Select %s', 'papijo-converter-for-h5p' ),
											$file['name']
										) ); ?>">
									</th>
									<td><strong><?php echo esc_html( $file['name'] ); ?></strong></td>
									<td><?php echo esc_html( $file['source_label'] ); ?></td>
									<td><span class="h5p-exporter-badge"><?php echo esc_html( $file['target_label'] ); ?></span></td>
									<td><?php echo esc_html( size_format( $file['size'], 2 ) ); ?></td>
									<td><?php echo esc_html( $this->format_modified_time( $file['modified'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_conversion_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to convert H5P content.', 'papijo-converter-for-h5p' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'The PHP Zip extension is required to convert .h5p packages.', 'papijo-converter-for-h5p' ) );
		}

		$available_files = $this->get_export_files();
		if ( empty( $available_files ) ) {
			wp_die( esc_html__( 'No supported default .h5p export packages were found.', 'papijo-converter-for-h5p' ) );
		}

		if ( isset( $_POST['convert_all'] ) ) {
			$selected_files = $available_files;
		} else {
			$tokens = isset( $_POST['file_tokens'] ) && is_array( $_POST['file_tokens'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['file_tokens'] ) )
				: array();

			$selected_files = $this->get_files_by_tokens( $tokens, $available_files );
		}

		if ( empty( $selected_files ) ) {
			wp_die( esc_html__( 'Choose at least one source H5P package to convert.', 'papijo-converter-for-h5p' ) );
		}

		$download_token = isset( $_POST['h5p_download_token'] ) ? sanitize_text_field( wp_unslash( $_POST['h5p_download_token'] ) ) : '';

		$temp_files = array();
		$warnings   = array();
		$outputs    = array();

		try {
			foreach ( $selected_files as $file ) {
				$converted_path = $this->convert_file( $file, $warnings );
				if ( '' !== $converted_path ) {
					$temp_files[] = $converted_path;
					$outputs[]    = array(
						'path' => $converted_path,
						'name' => $this->build_converted_filename( $file['name'], $file['target'] ),
					);
				}
			}

			if ( empty( $outputs ) ) {
				throw new RuntimeException( implode( ' ', array_unique( $warnings ) ) );
			}

			$archive_path = $this->create_temp_file( 'h5p-papijo-converted-', '.zip' );
			$temp_files[] = $archive_path;

			$archive = new ZipArchive();
			if ( true !== $archive->open( $archive_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				throw new RuntimeException( esc_html__( 'Could not create the converted download ZIP file.', 'papijo-converter-for-h5p' ) );
			}

			foreach ( $outputs as $output ) {
				$archive->addFile( $output['path'], $output['name'] );
			}

			if ( ! empty( $warnings ) ) {
				$archive->addFromString( 'conversion-notes.txt', implode( PHP_EOL, array_unique( $warnings ) ) . PHP_EOL );
			}

			$archive->close();

			$this->stream_file(
				$archive_path,
				'h5p-papijo-converted-' . gmdate( 'Ymd-His' ) . '.zip',
				'application/zip',
				$temp_files,
				$download_token
			);
		} catch ( Throwable $exception ) {
			$this->delete_temp_files( $temp_files );
			wp_die( esc_html( $exception->getMessage() ) );
		}
	}

	private function get_export_files(): array {
		$exports_dir = $this->get_exports_directory();
		if ( ! is_dir( $exports_dir ) || ! is_readable( $exports_dir ) || ! class_exists( 'ZipArchive' ) ) {
			return array();
		}

		$files = glob( trailingslashit( $exports_dir ) . '*.h5p' );
		if ( false === $files ) {
			return array();
		}

		$exports_dir_real = realpath( $exports_dir );
		$items            = array();

		foreach ( $files as $path ) {
			$real_path = realpath( $path );
			if ( false === $real_path || false === $exports_dir_real || 0 !== strpos( wp_normalize_path( $real_path ), trailingslashit( wp_normalize_path( $exports_dir_real ) ) ) ) {
				continue;
			}

			if ( ! is_file( $real_path ) || ! is_readable( $real_path ) || 'h5p' !== strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			$content_type = $this->get_supported_source_type( $real_path );
			if ( empty( $content_type ) ) {
				continue;
			}

			$name    = basename( $real_path );
			$items[] = array(
				'token'        => $this->build_file_token( $name ),
				'name'         => $name,
				'path'         => $real_path,
				'size'         => (int) filesize( $real_path ),
				'modified'     => (int) filemtime( $real_path ),
				'source'       => $content_type['source'],
				'source_label' => $content_type['source_label'],
				'target'       => $content_type['target'],
				'target_label' => $content_type['target_label'],
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $items;
	}

	private function get_supported_source_type( string $path ): array {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array();
		}

		$manifest_json = $zip->getFromName( 'h5p.json' );
		if ( false === $manifest_json ) {
			$zip->close();
			return array();
		}

		$manifest = json_decode( $manifest_json, true );
		if ( ! is_array( $manifest ) || empty( $manifest['mainLibrary'] ) || ! is_string( $manifest['mainLibrary'] ) ) {
			$zip->close();
			return array();
		}

		$source = $manifest['mainLibrary'];
		if ( empty( self::LIBRARIES[ $source ] ) ) {
			$zip->close();
			return array();
		}

		$library_metadata = $this->get_main_library_metadata( $zip, $source );
		$zip->close();

		if ( $this->is_papi_jo_library( $source, $library_metadata ) ) {
			return array();
		}

		$library = self::LIBRARIES[ $source ];
		return array(
			'source'       => $source,
			'source_label' => $library['label'],
			'target'       => $library['target'],
			'target_label' => $library['target_label'],
		);
	}

	private function convert_file( array $file, array &$warnings ): string {
		$source_zip = new ZipArchive();
		if ( true !== $source_zip->open( $file['path'] ) ) {
			$warnings[] = $file['name'] . ': ' . esc_html__( 'could not read the H5P archive.', 'papijo-converter-for-h5p' );
			return '';
		}

		$manifest_json = $source_zip->getFromName( 'h5p.json' );
		$manifest      = false !== $manifest_json ? json_decode( $manifest_json, true ) : null;
		if ( ! is_array( $manifest ) || empty( $manifest['mainLibrary'] ) || empty( self::LIBRARIES[ $manifest['mainLibrary'] ] ) ) {
			$source_zip->close();
			$warnings[] = $file['name'] . ': ' . esc_html__( 'not a supported source H5P package.', 'papijo-converter-for-h5p' );
			return '';
		}

		$source_machine = $manifest['mainLibrary'];
		$library        = self::LIBRARIES[ $source_machine ];

		if ( $this->is_papi_jo_library( $source_machine, $this->get_main_library_metadata( $source_zip, $source_machine ) ) ) {
			$source_zip->close();
			$warnings[] = $file['name'] . ': ' . esc_html__( 'already appears to be a Papi Jo package.', 'papijo-converter-for-h5p' );
			return '';
		}

		if ( ! $this->replace_dependency( $manifest, $source_machine, $library ) ) {
			$source_zip->close();
			$warnings[] = $file['name'] . ': ' . esc_html__( 'could not find the source library dependency in h5p.json.', 'papijo-converter-for-h5p' );
			return '';
		}

		$manifest['mainLibrary'] = $library['target'];

		$content_json = $source_zip->getFromName( 'content/content.json' );
		$content      = false !== $content_json ? json_decode( $content_json, true ) : null;
		if ( 'H5P.QuestionSet' === $source_machine && is_array( $content ) ) {
			$this->convert_question_set_content( $content );
		}
		if ( 'H5P.Dialogcards' === $source_machine && is_array( $content ) ) {
			$this->convert_dialog_cards_content( $content );
		}
		if ( 'H5P.Timeline' === $source_machine ) {
			if ( ! is_array( $content ) || ! isset( $content['timeline'] ) || ! is_array( $content['timeline'] ) ) {
				$source_zip->close();
				$warnings[] = $file['name'] . ': ' . esc_html__( 'could not convert Timeline content/content.json.', 'papijo-converter-for-h5p' );
				return '';
			}
			$content = $this->convert_timeline_content( $content );
		}

		$output_path = $this->create_temp_file( 'h5p-papijo-package-', '.h5p' );
		$output_zip  = new ZipArchive();
		if ( true !== $output_zip->open( $output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$source_zip->close();
			wp_delete_file( $output_path );
			throw new RuntimeException( esc_html__( 'Could not create a converted .h5p package.', 'papijo-converter-for-h5p' ) );
		}

		$library_dirs = $this->get_archive_library_dirs( $source_zip );
		for ( $index = 0; $index < $source_zip->numFiles; $index++ ) {
			$name = $source_zip->getNameIndex( $index );
			if ( ! is_string( $name ) || '' === $name || '/' === substr( $name, -1 ) || $this->is_archive_library_file( $name, $library_dirs ) ) {
				continue;
			}

			$data = $source_zip->getFromIndex( $index );
			if ( false === $data ) {
				continue;
			}

			if ( 'h5p.json' === $name ) {
				$data = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			} elseif ( 'content/content.json' === $name && is_array( $content ) ) {
				$data = wp_json_encode( $content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
			}

			$output_zip->addFromString( $name, $data );
		}

		$output_zip->close();
		$source_zip->close();

		return $output_path;
	}

	private function replace_dependency( array &$manifest, string $source_machine, array $library ): bool {
		$dependency_machine = isset( $library['dependency'] ) ? $library['dependency'] : $source_machine;
		foreach ( array( 'preloadedDependencies', 'dynamicDependencies', 'editorDependencies' ) as $key ) {
			if ( empty( $manifest[ $key ] ) || ! is_array( $manifest[ $key ] ) ) {
				continue;
			}

			foreach ( $manifest[ $key ] as &$dependency ) {
				if ( ! is_array( $dependency ) || ( $dependency['machineName'] ?? '' ) !== $dependency_machine ) {
					continue;
				}

				$dependency['machineName']  = $library['target'];
				$dependency['majorVersion'] = (int) $library['major'];
				$dependency['minorVersion'] = (int) $library['minor'];
				return true;
			}
		}

		return false;
	}

	private function convert_question_set_content( array &$content ): void {
		$map = array();
		foreach ( self::LIBRARIES as $source => $library ) {
			if ( in_array( $source, array( 'H5P.QuestionSet', 'H5P.Timeline' ), true ) ) {
				continue;
			}

			$map[ $source ] = $library['target'] . ' ' . $library['major'] . '.' . $library['minor'];
		}

		$this->replace_library_references( $content, $map );
	}

	private function replace_library_references( &$value, array $map ): void {
		if ( ! is_array( $value ) ) {
			return;
		}

		foreach ( $value as $key => &$child ) {
			if ( 'library' === $key && is_string( $child ) ) {
				$parts = explode( ' ', trim( $child ), 2 );
				if ( isset( $map[ $parts[0] ] ) ) {
					$child = $map[ $parts[0] ];
					continue;
				}
			}

			$this->replace_library_references( $child, $map );
		}
	}

	private function convert_dialog_cards_content( array &$content ): void {
		$this->wrap_dialog_media( $content );
	}

	private function convert_timeline_content( array $content ): array {
		$timeline = $content['timeline'];
		$converted = array(
			'showTitleSlide' => true,
			'titleSlide'     => $this->convert_timeline_slide( $timeline, true ),
			'timelineItems'  => array(),
			'behaviour'      => array(
				'scalingMode'     => 'human',
				'initialZoom'     => 2,
				'timenavPosition' => 'bottom',
				'startatend'      => false,
				'startatslide'    => 1,
			),
			'language'       => ! empty( $timeline['language'] ) ? $timeline['language'] : 'en',
		);

		foreach ( isset( $timeline['date'] ) && is_array( $timeline['date'] ) ? $timeline['date'] : array() as $date ) {
			if ( is_array( $date ) ) {
				$converted['timelineItems'][] = $this->convert_timeline_slide( $date, false );
			}
		}

		$eras = array();
		foreach ( isset( $timeline['era'] ) && is_array( $timeline['era'] ) ? $timeline['era'] : array() as $era ) {
			if ( ! is_array( $era ) ) {
				continue;
			}
			$item = array();
			if ( isset( $era['headline'] ) ) {
				$item['name'] = (string) $era['headline'];
			}
			foreach ( array( 'startDate', 'endDate' ) as $date_key ) {
				if ( ! empty( $era[ $date_key ] ) ) {
					$item[ $date_key ] = $this->normalize_timeline_date( $era[ $date_key ] );
				}
			}
			if ( ! empty( $item ) ) {
				$eras[] = $item;
			}
		}
		if ( ! empty( $eras ) ) {
			$converted['eras'] = $eras;
		}

		return $converted;
	}

	private function convert_timeline_slide( array $slide, bool $is_title ): array {
		$asset = isset( $slide['asset'] ) && is_array( $slide['asset'] ) ? $slide['asset'] : array();
		$item = array(
			'slideType'   => $is_title ? 'title' : 'regular',
			'description' => $this->timeline_advanced_text( $slide['text'] ?? '' ),
			'layout'      => 'right',
			'mediaType'   => 'custom',
			'appearance'  => array( 'backgroundType' => 'none', 'backgroundColor' => '#757575' ),
		);
		if ( ! $is_title ) {
			$item['TextOrImage'] = 'text';
			$item['customQuote'] = array(
				'params' => array(), 'library' => 'H5P.SimpleTextareaPapiJo 1.0',
				'subContentId' => wp_generate_uuid4(),
				'metadata' => array( 'contentType' => 'Simple Textarea Papi Jo', 'license' => 'U' ),
			);
		}
		if ( isset( $slide['headline'] ) ) {
			$item['title'] = (string) $slide['headline'];
		}
		foreach ( array( 'startDate', 'endDate' ) as $date_key ) {
			if ( ! empty( $slide[ $date_key ] ) ) {
				$item[ $date_key ] = $this->normalize_timeline_date( $slide[ $date_key ] );
			}
		}
		if ( ! empty( $asset['media'] ) ) {
			$item['customMedia'] = (string) $asset['media'];
		}
		if ( ! empty( $asset['credit'] ) || ! empty( $asset['caption'] ) ) {
			$item['info'] = array(
				'credit' => $this->timeline_html_block( $asset['credit'] ?? '' ),
				'caption' => $this->timeline_html_block( $asset['caption'] ?? '' ),
			);
		}
		return $item;
	}

	private function timeline_advanced_text( $html ): array {
		$html = trim( (string) $html );
		$html = preg_replace( '/<div\b([^>]*)>/i', '<p$1>', $html );
		$html = preg_replace( '/<\/div>/i', '</p>', $html );
		$html = preg_replace( '/\s*\r?\n+\s*/', '', $html );
		return array(
			'params' => array( 'text' => trim( $html ) ),
			'library' => 'H5P.AdvancedTextPapiJo 1.1',
			'subContentId' => wp_generate_uuid4(),
			'metadata' => array( 'contentType' => 'Text Papi Jo', 'license' => 'U', 'title' => 'Untitled Text Papi Jo' ),
		);
	}

	private function normalize_timeline_date( $date ): string {
		return preg_replace( '/\s*[-,]\s*/', '-', trim( (string) $date ) );
	}

	private function timeline_html_block( $value ): string {
		$value = trim( (string) $value );
		return '' === $value || preg_match( '/^\s*<(div|p|blockquote|ul|ol|figure|table|h[1-6])\b/i', $value ) ? $value : '<div>' . $value . '</div>';
	}

	private function wrap_dialog_media( &$value ): void {
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( isset( $value['dialogs'] ) && is_array( $value['dialogs'] ) ) {
			foreach ( $value['dialogs'] as &$dialog ) {
				if ( ! is_array( $dialog ) ) {
					continue;
				}

				if ( isset( $dialog['image'] ) && ! isset( $dialog['imageMedia'] ) ) {
					$dialog['imageMedia'] = array( 'image' => $dialog['image'] );
					if ( isset( $dialog['imageAltText'] ) ) {
						$dialog['imageMedia']['imageAltText'] = $dialog['imageAltText'];
						unset( $dialog['imageAltText'] );
					}
					unset( $dialog['image'] );
				}

				if ( isset( $dialog['audio'] ) && ! isset( $dialog['audioMedia'] ) ) {
					$dialog['audioMedia'] = array( 'audio' => $dialog['audio'] );
					unset( $dialog['audio'] );
				}
			}
		}

		foreach ( $value as &$child ) {
			$this->wrap_dialog_media( $child );
		}
	}

	private function get_archive_library_dirs( ZipArchive $zip ): array {
		$library_dirs = array();

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );
			if ( ! is_string( $name ) || 1 !== substr_count( $name, '/' ) || 'library.json' !== basename( $name ) ) {
				continue;
			}

			$library_dirs[ dirname( $name ) ] = true;
		}

		return $library_dirs;
	}

	private function is_archive_library_file( string $name, array $library_dirs ): bool {
		$top_level_dir = strtok( $name, '/' );
		return $top_level_dir && isset( $library_dirs[ $top_level_dir ] );
	}

	private function get_main_library_metadata( ZipArchive $zip, string $main_library ): array {
		$pattern = '/^' . preg_quote( $main_library, '/' ) . '-[0-9]+\\.[0-9]+(?:\\.[0-9]+)?\\/library\\.json$/i';

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name = $zip->getNameIndex( $index );
			if ( ! is_string( $name ) || ! preg_match( $pattern, $name ) ) {
				continue;
			}

			$library_json = $zip->getFromName( $name );
			if ( false === $library_json ) {
				continue;
			}

			$metadata = json_decode( $library_json, true );
			return is_array( $metadata ) ? $metadata : array();
		}

		return array();
	}

	private function is_papi_jo_library( string $main_library, array $library_metadata ): bool {
		$library_title = isset( $library_metadata['title'] ) && is_string( $library_metadata['title'] ) ? $library_metadata['title'] : '';
		$library_name  = isset( $library_metadata['machineName'] ) && is_string( $library_metadata['machineName'] ) ? $library_metadata['machineName'] : '';
		$combined      = strtolower( $main_library . ' ' . $library_title . ' ' . $library_name );

		return false !== strpos( $combined, 'papijo' ) || false !== strpos( $combined, 'papi jo' );
	}

	private function get_files_by_tokens( array $tokens, array $available_files ): array {
		$tokens = array_flip( array_unique( array_filter( $tokens ) ) );
		if ( empty( $tokens ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$available_files,
				static function ( array $file ) use ( $tokens ): bool {
					return isset( $tokens[ $file['token'] ] );
				}
			)
		);
	}

	private function build_file_token( string $filename ): string {
		return hash_hmac( 'sha256', $filename, wp_salt( 'nonce' ) );
	}

	private function get_exports_directory(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'h5p/exports';
	}

	private function format_modified_time( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return esc_html__( 'Unknown', 'papijo-converter-for-h5p' );
		}

		return sprintf(
			/* translators: 1: date, 2: time. */
			esc_html__( '%1$s at %2$s', 'papijo-converter-for-h5p' ),
			wp_date( get_option( 'date_format' ), $timestamp ),
			wp_date( get_option( 'time_format' ), $timestamp )
		);
	}

	private function build_converted_filename( string $source_filename, string $target_library ): string {
		$base = pathinfo( $source_filename, PATHINFO_FILENAME );
		$suffix = strtolower( str_replace( array( 'H5P.', 'PapiJo' ), array( '', '-PapiJo' ), $target_library ) );
		return sanitize_file_name( $base . '-' . $suffix . '.h5p' );
	}

	private function create_temp_file( string $prefix, string $extension ): string {
		$temp_dir = get_temp_dir();
		if ( ! is_dir( $temp_dir ) || ! wp_is_writable( $temp_dir ) ) {
			throw new RuntimeException( esc_html__( 'Could not create a temporary conversion file.', 'papijo-converter-for-h5p' ) );
		}

		$filename = wp_unique_filename( $temp_dir, uniqid( $prefix, true ) . $extension );
		if ( ! is_string( $filename ) || '' === $filename ) {
			throw new RuntimeException( esc_html__( 'Could not prepare a temporary conversion file.', 'papijo-converter-for-h5p' ) );
		}

		return trailingslashit( $temp_dir ) . $filename;
	}

	private function stream_file( string $path, string $filename, string $content_type, array $temp_files, string $download_token = '' ): void {
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( esc_html__( 'The converted file could not be read.', 'papijo-converter-for-h5p' ) );
		}

		if ( ! empty( $temp_files ) ) {
			register_shutdown_function( array( $this, 'delete_temp_files' ), $temp_files );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		if ( '' !== $download_token ) {
			setcookie(
				self::DOWNLOAD_COOKIE,
				$download_token,
				array(
					'expires'  => time() + 300,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		}
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		$this->output_file_contents( $path );
		exit;
	}

	private function output_file_contents( string $path ): void {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary ZIP stream must be emitted unchanged.
			echo $wp_filesystem->get_contents( $path );
		}
	}

	public function delete_temp_files( array $temp_files ): void {
		foreach ( array_unique( $temp_files ) as $temp_file ) {
			if ( is_string( $temp_file ) && is_file( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
		}
	}
}

( new Papi_Jo_H5P_Converter() )->init();
