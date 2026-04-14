<?php
/**
 * Plugin Name: WPSL CSV Importer
 * Plugin URI:  https://github.com/enzomazzariol/wpsl-csv-importer
 * Description: Import stores from CSV files directly into WP Store Locator — no file manager needed.
 * Version:     1.2.0
 * Author:      Enzo Paolo Mazzariol Saba
 * Author URI:  https://enzomazzariol.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpsl-csv-importer
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSL_CSV_VERSION', '1.2.0' );
define( 'WPSL_CSV_OPTION_MAPPING', 'wpsl_csv_last_mapping' );
define( 'WPSL_CSV_CHUNK_SIZE', 50 );

// ─────────────────────────────────────────────────────────────────────────────
// UNINSTALL HOOK
// ─────────────────────────────────────────────────────────────────────────────

register_uninstall_hook( __FILE__, 'wpsl_csv_importer_uninstall' );

function wpsl_csv_importer_uninstall() {
	delete_option( WPSL_CSV_OPTION_MAPPING );
}

// ─────────────────────────────────────────────────────────────────────────────
// i18n
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'wpsl_csv_importer_load_textdomain' );

function wpsl_csv_importer_load_textdomain() {
	load_plugin_textdomain( 'wpsl-csv-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// ─────────────────────────────────────────────────────────────────────────────
// DEPENDENCY NOTICE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_notices', 'wpsl_csv_importer_dependency_notice' );

function wpsl_csv_importer_dependency_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( post_type_exists( 'wpsl_stores' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	printf(
		/* translators: %s: link to plugin install page */
		esc_html__( 'WPSL CSV Importer requires WP Store Locator to be installed and active. %s', 'wpsl-csv-importer' ),
		'<a href="' . esc_url( admin_url( 'plugin-install.php?s=wp+store+locator&tab=search&type=term' ) ) . '">' . esc_html__( 'Install it now', 'wpsl-csv-importer' ) . '</a>'
	);
	echo '</p></div>';
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN MENU
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'wpsl_csv_importer_register_menu', 99 );

function wpsl_csv_importer_register_menu() {
	global $menu;

	$wpsl_exists = false;
	if ( is_array( $menu ) ) {
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $item[2] === 'wpsl' ) {
				$wpsl_exists = true;
				break;
			}
		}
	}

	if ( $wpsl_exists ) {
		add_submenu_page(
			'wpsl',
			__( 'CSV Importer', 'wpsl-csv-importer' ),
			__( 'CSV Importer', 'wpsl-csv-importer' ),
			'manage_options',
			'wpsl-csv-importer',
			'wpsl_csv_importer_page'
		);
	} else {
		add_menu_page(
			__( 'WPSL CSV Importer', 'wpsl-csv-importer' ),
			__( 'WPSL CSV Importer', 'wpsl-csv-importer' ),
			'manage_options',
			'wpsl-csv-importer',
			'wpsl_csv_importer_page',
			'dashicons-location-alt',
			30
		);
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN SCRIPTS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'wpsl_csv_importer_enqueue_scripts' );

function wpsl_csv_importer_enqueue_scripts( $hook ) {
	if ( strpos( $hook, 'wpsl-csv-importer' ) === false ) {
		return;
	}

	wp_register_script( 'wpsl-csv-importer', false, array( 'jquery' ), WPSL_CSV_VERSION, true );
	wp_enqueue_script( 'wpsl-csv-importer' );

	wp_localize_script(
		'wpsl-csv-importer',
		'wpslCsvAjax',
		array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'wpsl_csv_ajax' ),
			'chunk_size' => WPSL_CSV_CHUNK_SIZE,
			'i18n'       => array(
				'uploading'        => __( 'Uploading file\u2026', 'wpsl-csv-importer' ),
				'processing'       => __( 'Processing', 'wpsl-csv-importer' ),
				'done'             => __( 'Import complete.', 'wpsl-csv-importer' ),
				'error'            => __( 'An error occurred. Please try again.', 'wpsl-csv-importer' ),
				'import_now'       => __( 'Import now', 'wpsl-csv-importer' ),
				'inserted'         => __( 'store(s) inserted', 'wpsl-csv-importer' ),
				'updated'          => __( 'store(s) updated', 'wpsl-csv-importer' ),
				'skipped'          => __( 'skipped', 'wpsl-csv-importer' ),
				'no_changes'       => __( 'No changes', 'wpsl-csv-importer' ),
				'errors_label'     => __( 'Errors', 'wpsl-csv-importer' ),
				'detected_columns' => __( 'Detected columns:', 'wpsl-csv-importer' ),
			),
		)
	);

	wp_add_inline_script( 'wpsl-csv-importer', wpsl_csv_get_inline_js() );
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORT HANDLER  (must run before headers are sent)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_init', 'wpsl_csv_handle_export' );

function wpsl_csv_handle_export() {
	if ( empty( $_POST['wpsl_do_export'] ) ) {
		return;
	}
	if (
		empty( $_POST['wpsl_csv_export_nonce'] ) ||
		! wp_verify_nonce( $_POST['wpsl_csv_export_nonce'], 'wpsl_csv_export' )
	) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'wpsl-csv-importer' ) );
	}

	$stores = get_posts(
		array(
			'post_type'      => 'wpsl_stores',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);

	$meta_keys = array(
		'wpsl_address',
		'wpsl_address2',
		'wpsl_city',
		'wpsl_state',
		'wpsl_zip',
		'wpsl_country',
		'wpsl_phone',
		'wpsl_fax',
		'wpsl_email',
		'wpsl_url',
		'wpsl_lat',
		'wpsl_lng',
	);
	$csv_headers = array( 'Name', 'Address', 'Address2', 'City', 'State', 'ZipCode', 'Country', 'Phone', 'Fax', 'Email', 'Website', 'Lat', 'Lng', 'Category' );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="wpsl-stores-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel compatibility
	fputcsv( $output, $csv_headers );

	foreach ( $stores as $store ) {
		$row = array( $store->post_title );
		foreach ( $meta_keys as $key ) {
			$row[] = get_post_meta( $store->ID, $key, true );
		}
		// Categories
		$terms = wp_get_post_terms( $store->ID, 'wpsl_store_category', array( 'fields' => 'names' ) );
		$row[] = is_array( $terms ) ? implode( '|', $terms ) : '';
		fputcsv( $output, $row );
	}

	fclose( $output );
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN PAGE
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wpsl-csv-importer' ) );
	}

	$cleanup_result = null;

	if (
		isset( $_POST['wpsl_cleanup_nonce'] ) &&
		wp_verify_nonce( $_POST['wpsl_cleanup_nonce'], 'wpsl_cleanup' )
	) {
		$cleanup_result = wpsl_csv_cleanup_corrupt_zips();
	}

	$saved   = get_option( WPSL_CSV_OPTION_MAPPING, array() );
	$corrupt = wpsl_csv_count_corrupt_zips();
	$fields  = wpsl_csv_get_field_definitions();

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WPSL CSV Importer', 'wpsl-csv-importer' ); ?></h1>
		<p><?php esc_html_e( 'Upload a .csv file to import locations into WP Store Locator.', 'wpsl-csv-importer' ); ?></p>

		<?php if ( $cleanup_result ) : ?>
			<div class="notice notice-<?php echo esc_attr( $cleanup_result['type'] ); ?> is-dismissible">
				<p><?php echo wp_kses_post( $cleanup_result['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $corrupt > 0 ) : ?>
			<div class="notice notice-warning" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
				<p style="margin:0;">
					<?php
					printf(
						/* translators: %d: number of corrupt stores */
						esc_html__( '%d store(s) have "ZipCode" saved as zip code — corrupt data from a previous import. Delete and re-import them.', 'wpsl-csv-importer' ),
						intval( $corrupt )
					);
					?>
				</p>
				<form method="post" style="margin:0;">
					<?php wp_nonce_field( 'wpsl_cleanup', 'wpsl_cleanup_nonce' ); ?>
					<button type="submit" class="button button-secondary"
						onclick="return confirm('<?php echo esc_js( sprintf( __( 'Delete %d corrupt store(s)? This cannot be undone.', 'wpsl-csv-importer' ), intval( $corrupt ) ) ); ?>');">
						<?php printf( esc_html__( 'Delete %d corrupt record(s)', 'wpsl-csv-importer' ), intval( $corrupt ) ); ?>
					</button>
				</form>
			</div>
		<?php endif; ?>

		<!-- Progress bar -->
		<div id="wpsl-progress-wrap" style="display:none;margin:16px 0;background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;">
			<p id="wpsl-progress-status" style="margin:0 0 10px;font-weight:500;"></p>
			<div style="background:#e0e0e0;border-radius:4px;height:20px;overflow:hidden;">
				<div id="wpsl-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width .25s ease;"></div>
			</div>
		</div>

		<div id="wpsl-import-result" style="display:none;"></div>

		<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start;margin-top:16px;">

			<!-- ── IMPORT FORM ──────────────────────────────────────────────── -->
			<div style="background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:4px;min-width:420px;flex:1;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import CSV', 'wpsl-csv-importer' ); ?></h2>

				<form id="wpsl-import-form" method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'wpsl_csv_import', 'wpsl_csv_import_nonce' ); ?>
					<table class="form-table" role="presentation">

						<!-- File -->
						<tr>
							<th><label for="wpsl_csv_file"><?php esc_html_e( 'CSV File', 'wpsl-csv-importer' ); ?></label></th>
							<td>
								<input type="file" id="wpsl_csv_file" name="wpsl_csv_file" accept=".csv,text/csv" required>
								<p class="description">
									<?php printf( esc_html__( 'Maximum %s. UTF-8 encoding recommended.', 'wpsl-csv-importer' ), esc_html( ini_get( 'upload_max_filesize' ) ) ); ?>
								</p>
							</td>
						</tr>

						<!-- Column mapping fields -->
						<?php foreach ( $fields as $key => $f ) :
							$val = isset( $saved[ 'col_' . $f['input_name'] ] ) ? $saved[ 'col_' . $f['input_name'] ] : $f['default'];
						?>
						<tr>
							<th>
								<label for="col_<?php echo esc_attr( $f['input_name'] ); ?>">
									<?php echo esc_html( $f['label'] ); ?>
									<?php if ( ! $f['required'] ) : ?>
										<small>(<?php esc_html_e( 'optional', 'wpsl-csv-importer' ); ?>)</small>
									<?php endif; ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="col_<?php echo esc_attr( $f['input_name'] ); ?>"
									name="col_<?php echo esc_attr( $f['input_name'] ); ?>"
									class="regular-text"
									value="<?php echo esc_attr( $val ); ?>"
									placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>">
								<?php if ( ! empty( $f['description'] ) ) : ?>
									<p class="description"><?php echo wp_kses_post( $f['description'] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>

						<!-- Duplicate mode -->
						<tr>
							<th><?php esc_html_e( 'Existing stores', 'wpsl-csv-importer' ); ?></th>
							<td>
								<fieldset>
									<?php
									$dup_saved = $saved['duplicate_mode'] ?? 'skip';
									$dup_opts  = array(
										'skip'   => __( 'Skip — do not modify existing stores', 'wpsl-csv-importer' ),
										'update' => __( 'Update — overwrite data of existing stores', 'wpsl-csv-importer' ),
										'insert' => __( 'Always insert — ignore duplicates', 'wpsl-csv-importer' ),
									);
									foreach ( $dup_opts as $val => $label ) :
									?>
										<label style="display:block;margin-bottom:6px;">
											<input type="radio" name="duplicate_mode" value="<?php echo esc_attr( $val ); ?>"
												<?php checked( $dup_saved, $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Duplicates are detected by exact store name + address.', 'wpsl-csv-importer' ); ?></p>
							</td>
						</tr>

						<!-- Capitalization -->
						<tr>
							<th><?php esc_html_e( 'Capitalization', 'wpsl-csv-importer' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="normalize_case" value="1"
										<?php checked( ! empty( $saved['normalize_case'] ) ); ?>>
									<?php esc_html_e( 'Normalize capitalization in name, city, and state', 'wpsl-csv-importer' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Converts KANSAS CITY → Kansas City. Useful when the CSV is all caps.', 'wpsl-csv-importer' ); ?>
								</p>
							</td>
						</tr>

					</table>

					<button type="submit" class="button button-primary button-large wpsl-import-submit">
						<?php esc_html_e( 'Import now', 'wpsl-csv-importer' ); ?>
					</button>
				</form>

				<!-- Export -->
				<hr style="margin:28px 0 20px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Export stores to CSV', 'wpsl-csv-importer' ); ?></h3>
				<p style="font-size:13px;color:#50575e;">
					<?php esc_html_e( 'Download all WP Store Locator stores as a CSV file compatible with this importer.', 'wpsl-csv-importer' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'wpsl_csv_export', 'wpsl_csv_export_nonce' ); ?>
					<button type="submit" name="wpsl_do_export" value="1" class="button button-secondary">
						<?php esc_html_e( 'Export stores to CSV', 'wpsl-csv-importer' ); ?>
					</button>
				</form>
			</div>

			<!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
			<div style="max-width:340px;flex-shrink:0;">

				<!-- How it works -->
				<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'How it works', 'wpsl-csv-importer' ); ?></h3>
					<ol style="margin:0;padding-left:1.2em;line-height:1.8;">
						<li><?php esc_html_e( 'Prepare your CSV with headers in the first row.', 'wpsl-csv-importer' ); ?></li>
						<li><?php esc_html_e( 'Upload the file using the form on the left.', 'wpsl-csv-importer' ); ?></li>
						<li><?php esc_html_e( 'Map your column names to WPSL fields.', 'wpsl-csv-importer' ); ?></li>
						<li><?php esc_html_e( 'Click "Import now" and watch the progress bar.', 'wpsl-csv-importer' ); ?></li>
						<li><?php esc_html_e( 'Stores appear under WP Store Locator → Stores.', 'wpsl-csv-importer' ); ?></li>
					</ol>
				</div>

				<!-- Preview columns -->
				<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-bottom:20px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Preview CSV columns', 'wpsl-csv-importer' ); ?></h3>
					<p style="font-size:13px;margin-bottom:12px;">
						<?php esc_html_e( 'Upload your CSV to see its exact column names before importing.', 'wpsl-csv-importer' ); ?>
					</p>
					<form id="wpsl-preview-form" enctype="multipart/form-data">
						<input type="file" id="wpsl_csv_preview_file" name="wpsl_csv_preview" accept=".csv,text/csv" style="margin-bottom:8px;display:block;">
						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'View columns', 'wpsl-csv-importer' ); ?>
						</button>
					</form>
					<div id="wpsl-preview-result"></div>
				</div>

				<!-- CSV format reference -->
				<div style="background:#fff3cd;padding:20px;border:1px solid #ffc107;border-radius:4px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'CSV Format', 'wpsl-csv-importer' ); ?></h3>
					<p style="margin-bottom:8px;"><?php esc_html_e( 'Example headers:', 'wpsl-csv-importer' ); ?></p>
					<code style="display:block;font-size:11px;background:#fff;padding:8px;border-radius:3px;">Name,Address,City,State,ZipCode,Phone,Email,Website,Category</code>
					<p style="margin-top:12px;margin-bottom:0;font-size:13px;">
						&bull; <?php esc_html_e( 'Comma or semicolon separated.', 'wpsl-csv-importer' ); ?><br>
						&bull; <?php esc_html_e( 'UTF-8 encoding recommended.', 'wpsl-csv-importer' ); ?><br>
						&bull; <?php esc_html_e( 'NULL values are imported as empty.', 'wpsl-csv-importer' ); ?><br>
						&bull; <?php esc_html_e( 'Multiple categories: separate with |', 'wpsl-csv-importer' ); ?>
					</p>
				</div>

			</div>
		</div><!-- /flex -->
	</div><!-- /wrap -->
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// FIELD DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_field_definitions() {
	return array(
		'name'     => array(
			'label'      => __( 'Column → Name', 'wpsl-csv-importer' ),
			'input_name' => 'name',
			'default'    => 'Name',
			'required'   => true,
			'placeholder'=> 'Name',
		),
		'address'  => array(
			'label'      => __( 'Column → Address', 'wpsl-csv-importer' ),
			'input_name' => 'address',
			'default'    => 'Address',
			'required'   => true,
			'placeholder'=> 'Address',
		),
		'address2' => array(
			'label'      => __( 'Column → Address 2', 'wpsl-csv-importer' ),
			'input_name' => 'address2',
			'default'    => '',
			'required'   => false,
			'placeholder'=> 'Address2',
		),
		'city'     => array(
			'label'      => __( 'Column → City', 'wpsl-csv-importer' ),
			'input_name' => 'city',
			'default'    => 'City',
			'required'   => true,
			'placeholder'=> 'City',
		),
		'state'    => array(
			'label'      => __( 'Column → State / Province', 'wpsl-csv-importer' ),
			'input_name' => 'state',
			'default'    => 'State',
			'required'   => true,
			'placeholder'=> 'State',
		),
		'zip'      => array(
			'label'      => __( 'Column → Zip Code', 'wpsl-csv-importer' ),
			'input_name' => 'zip',
			'default'    => 'ZipCode',
			'required'   => true,
			'placeholder'=> 'ZipCode',
		),
		'country'  => array(
			'label'       => __( 'Column → Country (or fixed value)', 'wpsl-csv-importer' ),
			'input_name'  => 'country',
			'default'     => 'United States',
			'required'    => false,
			'placeholder' => 'Country',
			'description' => __( 'If your CSV has no country column, type the value directly (e.g. <em>United States</em>).', 'wpsl-csv-importer' ),
		),
		'phone'    => array(
			'label'      => __( 'Column → Phone', 'wpsl-csv-importer' ),
			'input_name' => 'phone',
			'default'    => 'Phone',
			'required'   => false,
			'placeholder'=> 'Phone',
		),
		'fax'      => array(
			'label'      => __( 'Column → Fax', 'wpsl-csv-importer' ),
			'input_name' => 'fax',
			'default'    => '',
			'required'   => false,
			'placeholder'=> 'Fax',
		),
		'email'    => array(
			'label'      => __( 'Column → Email', 'wpsl-csv-importer' ),
			'input_name' => 'email',
			'default'    => 'Email',
			'required'   => false,
			'placeholder'=> 'Email',
		),
		'url'      => array(
			'label'      => __( 'Column → URL / Website', 'wpsl-csv-importer' ),
			'input_name' => 'url',
			'default'    => 'Website',
			'required'   => false,
			'placeholder'=> 'Website',
		),
		'lat'      => array(
			'label'       => __( 'Column → Latitude', 'wpsl-csv-importer' ),
			'input_name'  => 'lat',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Lat',
			'description' => __( 'Leave empty to let WPSL geocode the address automatically.', 'wpsl-csv-importer' ),
		),
		'lng'      => array(
			'label'      => __( 'Column → Longitude', 'wpsl-csv-importer' ),
			'input_name' => 'lng',
			'default'    => '',
			'required'   => false,
			'placeholder'=> 'Lng',
		),
		'category' => array(
			'label'       => __( 'Column → Category', 'wpsl-csv-importer' ),
			'input_name'  => 'category',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Category',
			'description' => __( 'Assigns a WPSL store category. Category is created if it does not exist. Separate multiple with |', 'wpsl-csv-importer' ),
		),
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: MAP + VALIDATION
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_build_map( $post_data ) {
	return array(
		'post_title'    => sanitize_text_field( $post_data['col_name']     ?? 'Name' ),
		'wpsl_address'  => sanitize_text_field( $post_data['col_address']  ?? 'Address' ),
		'wpsl_address2' => sanitize_text_field( $post_data['col_address2'] ?? '' ),
		'wpsl_city'     => sanitize_text_field( $post_data['col_city']     ?? 'City' ),
		'wpsl_state'    => sanitize_text_field( $post_data['col_state']    ?? 'State' ),
		'wpsl_zip'      => sanitize_text_field( $post_data['col_zip']      ?? 'ZipCode' ),
		'wpsl_country'  => sanitize_text_field( $post_data['col_country']  ?? 'United States' ),
		'wpsl_phone'    => sanitize_text_field( $post_data['col_phone']    ?? 'Phone' ),
		'wpsl_fax'      => sanitize_text_field( $post_data['col_fax']      ?? '' ),
		'wpsl_email'    => sanitize_text_field( $post_data['col_email']    ?? 'Email' ),
		'wpsl_url'      => sanitize_text_field( $post_data['col_url']      ?? 'Website' ),
		'wpsl_lat'      => sanitize_text_field( $post_data['col_lat']      ?? '' ),
		'wpsl_lng'      => sanitize_text_field( $post_data['col_lng']      ?? '' ),
		'_category'     => sanitize_text_field( $post_data['col_category'] ?? '' ),
	);
}

/**
 * Returns WP_Error on failure, true on success.
 */
function wpsl_csv_validate_columns( array $headers, array $map ) {
	$required = array( 'post_title', 'wpsl_address', 'wpsl_city', 'wpsl_state', 'wpsl_zip' );
	$missing  = array();

	foreach ( $required as $key ) {
		$col = $map[ $key ] ?? '';
		if ( $col !== '' && ! in_array( $col, $headers, true ) ) {
			$missing[] = esc_html( $col );
		}
	}

	if ( ! empty( $missing ) ) {
		return new WP_Error(
			'missing_columns',
			'<strong>' . esc_html__( 'Required columns not found in CSV:', 'wpsl-csv-importer' ) . '</strong> '
			. implode( ', ', $missing )
			. '<br>'
			. esc_html__( 'CSV headers found:', 'wpsl-csv-importer' ) . ' '
			. implode( ', ', array_map( 'esc_html', $headers ) )
		);
	}

	return true;
}

/**
 * Clear optional map keys whose column name doesn't exist in the CSV headers.
 */
function wpsl_csv_clean_optional_map( array $headers, array $map ) {
	$optional = array( 'wpsl_address2', 'wpsl_phone', 'wpsl_fax', 'wpsl_email', 'wpsl_url', 'wpsl_lat', 'wpsl_lng', '_category' );
	foreach ( $optional as $key ) {
		$col = $map[ $key ] ?? '';
		if ( $col !== '' && ! in_array( $col, $headers, true ) ) {
			$map[ $key ] = '';
		}
	}
	return $map;
}

function wpsl_csv_save_mapping( array $post_data ) {
	$mapping = array(
		'col_name'       => sanitize_text_field( $post_data['col_name']     ?? '' ),
		'col_address'    => sanitize_text_field( $post_data['col_address']  ?? '' ),
		'col_address2'   => sanitize_text_field( $post_data['col_address2'] ?? '' ),
		'col_city'       => sanitize_text_field( $post_data['col_city']     ?? '' ),
		'col_state'      => sanitize_text_field( $post_data['col_state']    ?? '' ),
		'col_zip'        => sanitize_text_field( $post_data['col_zip']      ?? '' ),
		'col_country'    => sanitize_text_field( $post_data['col_country']  ?? '' ),
		'col_phone'      => sanitize_text_field( $post_data['col_phone']    ?? '' ),
		'col_fax'        => sanitize_text_field( $post_data['col_fax']      ?? '' ),
		'col_email'      => sanitize_text_field( $post_data['col_email']    ?? '' ),
		'col_url'        => sanitize_text_field( $post_data['col_url']      ?? '' ),
		'col_lat'        => sanitize_text_field( $post_data['col_lat']      ?? '' ),
		'col_lng'        => sanitize_text_field( $post_data['col_lng']      ?? '' ),
		'col_category'   => sanitize_text_field( $post_data['col_category'] ?? '' ),
		'duplicate_mode' => in_array( $post_data['duplicate_mode'] ?? 'skip', array( 'skip', 'update', 'insert' ), true )
			? $post_data['duplicate_mode'] : 'skip',
		'normalize_case' => ! empty( $post_data['normalize_case'] ) ? 1 : 0,
	);
	update_option( WPSL_CSV_OPTION_MAPPING, $mapping, false );
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: TEMP DIRECTORY
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_tmp_dir() {
	// Try OS temp dir first (not web-accessible)
	$tmp = trailingslashit( get_temp_dir() ) . 'wpsl-csv/';
	if ( ! file_exists( $tmp ) ) {
		@mkdir( $tmp, 0700, true );
	}
	if ( is_writable( $tmp ) ) {
		return $tmp;
	}

	// Fallback: WP uploads (protected by .htaccess)
	$upload_dir = wp_upload_dir();
	$tmp        = trailingslashit( $upload_dir['basedir'] ) . 'wpsl-csv-tmp/';
	if ( ! file_exists( $tmp ) ) {
		wp_mkdir_p( $tmp );
		file_put_contents( $tmp . '.htaccess', 'Deny from all' . PHP_EOL );
		file_put_contents( $tmp . 'index.php', '<?php // Silence is golden.' );
	}

	if ( ! is_writable( $tmp ) ) {
		return new WP_Error( 'not_writable', __( 'Temporary directory is not writable. Check server permissions.', 'wpsl-csv-importer' ) );
	}

	return $tmp;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: STORE LOOKUP (name + address to handle chains)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Find an existing store by name AND address.
 * Handles chains like "Price Chopper" that have multiple locations.
 *
 * @return int Post ID, or 0 if not found.
 */
function wpsl_csv_find_store( $title, $address ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'wpsl_stores',
			'title'                  => $title,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		)
	);

	if ( empty( $query->posts ) ) {
		return 0;
	}

	// If address is empty, fall back to name-only match
	if ( empty( $address ) ) {
		return (int) $query->posts[0];
	}

	foreach ( $query->posts as $post_id ) {
		$stored = get_post_meta( (int) $post_id, 'wpsl_address', true );
		if ( strtolower( trim( $stored ) ) === strtolower( trim( $address ) ) ) {
			return (int) $post_id;
		}
	}

	return 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: NORMALIZATION
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_normalize_case( array $fields ) {
	$keys = array( 'post_title', 'wpsl_city', 'wpsl_state', 'wpsl_country' );
	foreach ( $keys as $key ) {
		if ( ! empty( $fields[ $key ] ) ) {
			$fields[ $key ] = ucwords( strtolower( $fields[ $key ] ) );
		}
	}
	return $fields;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: PROCESS SINGLE ROW
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @return array { inserted: int, updated: int, skipped: int, error: string }
 */
function wpsl_csv_process_row( array $fields, $duplicate_mode ) {
	$result = array(
		'inserted' => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'error'    => '',
	);

	// Extract category before meta loop — not a real meta key
	$category_value = $fields['_category'] ?? '';
	unset( $fields['_category'] );

	$existing_id = wpsl_csv_find_store( $fields['post_title'], $fields['wpsl_address'] ?? '' );

	// SKIP
	if ( $duplicate_mode === 'skip' && $existing_id ) {
		$result['skipped']++;
		return $result;
	}

	// UPDATE
	if ( $duplicate_mode === 'update' && $existing_id ) {
		// Capture old address BEFORE update to detect change
		$old_address     = get_post_meta( $existing_id, 'wpsl_address', true );
		$address_changed = ( $old_address !== ( $fields['wpsl_address'] ?? '' ) );

		$update = wp_update_post(
			array(
				'ID'          => $existing_id,
				'post_title'  => sanitize_text_field( $fields['post_title'] ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $update ) ) {
			$result['error'] = 'Error updating "' . esc_html( $fields['post_title'] ) . '": ' . $update->get_error_message();
			return $result;
		}

		foreach ( $fields as $meta_key => $meta_val ) {
			if ( $meta_key === 'post_title' ) {
				continue;
			}
			update_post_meta( $existing_id, $meta_key, sanitize_text_field( $meta_val ) );
		}

		// Force WPSL re-geocoding when address changed and no explicit coords provided
		if ( $address_changed && empty( $fields['wpsl_lat'] ) ) {
			delete_post_meta( $existing_id, 'wpsl_lat' );
			delete_post_meta( $existing_id, 'wpsl_lng' );
		}

		if ( $category_value !== '' ) {
			wpsl_csv_assign_category( $existing_id, $category_value );
		}

		$result['updated']++;
		return $result;
	}

	// INSERT (new or "always insert")
	$post_id = wp_insert_post(
		array(
			'post_title'   => sanitize_text_field( $fields['post_title'] ),
			'post_content' => '',
			'post_type'    => 'wpsl_stores',
			'post_status'  => 'draft',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		$result['error'] = 'Error inserting "' . esc_html( $fields['post_title'] ) . '": ' . $post_id->get_error_message();
		return $result;
	}

	foreach ( $fields as $meta_key => $meta_val ) {
		if ( $meta_key === 'post_title' ) {
			continue;
		}
		update_post_meta( $post_id, $meta_key, sanitize_text_field( $meta_val ) );
	}

	if ( $category_value !== '' ) {
		wpsl_csv_assign_category( $post_id, $category_value );
	}

	// Publishing triggers WPSL's save_post hook → geocoding
	wp_publish_post( $post_id );
	$result['inserted']++;
	return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: CATEGORY ASSIGNMENT
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_assign_category( $post_id, $input ) {
	$categories = array_filter( array_map( 'trim', explode( '|', $input ) ) );
	if ( empty( $categories ) ) {
		return;
	}

	$term_ids = array();
	foreach ( $categories as $name ) {
		$term = term_exists( $name, 'wpsl_store_category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'wpsl_store_category' );
		}
		if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
			$term_ids[] = (int) $term['term_id'];
		}
	}

	if ( ! empty( $term_ids ) ) {
		wp_set_object_terms( $post_id, $term_ids, 'wpsl_store_category', false );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: INIT IMPORT  (upload file, validate, return batch_id + total)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_import_init', 'wpsl_csv_ajax_import_init' );

function wpsl_csv_ajax_import_init() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpsl-csv-importer' ) ) );
	}

	if ( empty( $_FILES['wpsl_csv_file']['tmp_name'] ) || $_FILES['wpsl_csv_file']['error'] !== UPLOAD_ERR_OK ) {
		$code = (int) ( $_FILES['wpsl_csv_file']['error'] ?? 0 );
		wp_send_json_error( array( 'message' => sprintf( __( 'Upload error (code %d).', 'wpsl-csv-importer' ), $code ) ) );
	}

	$file = $_FILES['wpsl_csv_file'];
	$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( $ext !== 'csv' ) {
		wp_send_json_error( array( 'message' => __( 'File must be a .csv.', 'wpsl-csv-importer' ) ) );
	}

	// Move to temp dir
	$tmp_dir = wpsl_csv_get_tmp_dir();
	if ( is_wp_error( $tmp_dir ) ) {
		wp_send_json_error( array( 'message' => $tmp_dir->get_error_message() ) );
	}

	// Remove stale temp files (> 2 hours old)
	foreach ( glob( $tmp_dir . '*.csv' ) ?: array() as $stale ) {
		if ( filemtime( $stale ) < ( time() - 2 * HOUR_IN_SECONDS ) ) {
			@unlink( $stale );
		}
	}

	$batch_id = sanitize_key( wp_generate_uuid4() );
	$dest     = $tmp_dir . $batch_id . '.csv';

	if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not save uploaded file.', 'wpsl-csv-importer' ) ) );
	}

	// Detect delimiter
	$fh         = fopen( $dest, 'r' );
	$first_line = fgets( $fh );
	fclose( $fh );
	$delimiter = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

	// Open + handle BOM
	$fh  = fopen( $dest, 'r' );
	$bom = fread( $fh, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $fh );
	}

	$headers = fgetcsv( $fh, 0, $delimiter );
	if ( ! $headers ) {
		fclose( $fh );
		@unlink( $dest );
		wp_send_json_error( array( 'message' => __( 'Could not read CSV headers.', 'wpsl-csv-importer' ) ) );
	}
	$headers          = array_map( 'trim', $headers );
	$data_start_byte  = ftell( $fh );

	// Count rows
	$total = 0;
	while ( fgetcsv( $fh, 0, $delimiter ) !== false ) {
		$total++;
	}
	fclose( $fh );

	// Build and validate map
	$map        = wpsl_csv_build_map( $_POST );
	$validation = wpsl_csv_validate_columns( $headers, $map );
	if ( is_wp_error( $validation ) ) {
		@unlink( $dest );
		wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
	}
	$map = wpsl_csv_clean_optional_map( $headers, $map );

	$duplicate_mode = in_array( $_POST['duplicate_mode'] ?? 'skip', array( 'skip', 'update', 'insert' ), true )
		? $_POST['duplicate_mode'] : 'skip';

	// Store batch config in transient
	set_transient(
		'wpsl_csv_batch_' . $batch_id,
		array(
			'file'           => $dest,
			'delimiter'      => $delimiter,
			'headers'        => $headers,
			'data_offset'    => $data_start_byte,
			'map'            => $map,
			'duplicate_mode' => $duplicate_mode,
			'normalize_case' => ! empty( $_POST['normalize_case'] ),
			'user_id'        => get_current_user_id(),
		),
		HOUR_IN_SECONDS
	);

	// Persist column mapping for next time
	wpsl_csv_save_mapping( $_POST );

	wp_send_json_success(
		array(
			'batch_id' => $batch_id,
			'total'    => $total,
		)
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: PROCESS CHUNK
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_import_chunk', 'wpsl_csv_ajax_import_chunk' );

function wpsl_csv_ajax_import_chunk() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpsl-csv-importer' ) ) );
	}

	$batch_id   = sanitize_key( $_POST['batch_id'] ?? '' );
	$offset     = max( 0, intval( $_POST['offset'] ?? 0 ) );
	$chunk_size = max( 1, min( 200, intval( $_POST['chunk_size'] ?? WPSL_CSV_CHUNK_SIZE ) ) );

	$batch = get_transient( 'wpsl_csv_batch_' . $batch_id );
	if ( ! $batch ) {
		wp_send_json_error( array( 'message' => __( 'Batch expired or not found. Please restart the import.', 'wpsl-csv-importer' ) ) );
	}

	if ( (int) $batch['user_id'] !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'wpsl-csv-importer' ) ) );
	}

	if ( ! file_exists( $batch['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Temporary file missing. Please restart the import.', 'wpsl-csv-importer' ) ) );
	}

	$fh        = fopen( $batch['file'], 'r' );
	$delimiter = $batch['delimiter'];
	$headers   = $batch['headers'];
	$map       = $batch['map'];

	// Seek to data start, then skip already-processed rows
	fseek( $fh, $batch['data_offset'] );
	for ( $i = 0; $i < $offset; $i++ ) {
		if ( fgetcsv( $fh, 0, $delimiter ) === false ) {
			break;
		}
	}

	$inserted  = 0;
	$updated   = 0;
	$skipped   = 0;
	$errors    = array();
	$processed = 0;

	while ( $processed < $chunk_size ) {
		$row = fgetcsv( $fh, 0, $delimiter );
		if ( $row === false ) {
			break;
		}
		$processed++;

		// Build data array keyed by header
		$data = array();
		foreach ( $headers as $i => $col ) {
			$val         = isset( $row[ $i ] ) ? trim( $row[ $i ] ) : '';
			$data[ $col ] = ( $val === 'NULL' ) ? '' : $val;
		}

		// Resolve map → field values
		$fields = array();
		foreach ( $map as $wpsl_key => $csv_col ) {
			if ( $csv_col === '' ) {
				$fields[ $wpsl_key ] = '';
				continue;
			}
			if ( array_key_exists( $csv_col, $data ) ) {
				$fields[ $wpsl_key ] = $data[ $csv_col ];
			} elseif ( $wpsl_key === 'wpsl_country' ) {
				$fields[ $wpsl_key ] = $csv_col; // fixed value
			} else {
				$fields[ $wpsl_key ] = '';
			}
		}

		if ( $batch['normalize_case'] ) {
			$fields = wpsl_csv_normalize_case( $fields );
		}

		if ( empty( $fields['post_title'] ) ) {
			$skipped++;
			continue;
		}

		$row_result = wpsl_csv_process_row( $fields, $batch['duplicate_mode'] );
		$inserted  += $row_result['inserted'];
		$updated   += $row_result['updated'];
		$skipped   += $row_result['skipped'];
		if ( ! empty( $row_result['error'] ) ) {
			$errors[] = $row_result['error'];
		}
	}

	fclose( $fh );

	$done        = ( $processed < $chunk_size ); // ran out of rows
	$next_offset = $offset + $processed;

	if ( $done ) {
		@unlink( $batch['file'] );
		delete_transient( 'wpsl_csv_batch_' . $batch_id );
	}

	wp_send_json_success(
		array(
			'inserted'    => $inserted,
			'updated'     => $updated,
			'skipped'     => $skipped,
			'errors'      => $errors,
			'next_offset' => $next_offset,
			'done'        => $done,
		)
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: GET HEADERS (preview)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_get_headers', 'wpsl_csv_ajax_get_headers' );

function wpsl_csv_ajax_get_headers() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpsl-csv-importer' ) ) );
	}

	if ( empty( $_FILES['wpsl_csv_preview']['tmp_name'] ) || $_FILES['wpsl_csv_preview']['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'wpsl-csv-importer' ) ) );
	}

	$tmp = $_FILES['wpsl_csv_preview']['tmp_name'];
	$fh  = fopen( $tmp, 'r' );
	if ( ! $fh ) {
		wp_send_json_error( array( 'message' => __( 'Could not open file.', 'wpsl-csv-importer' ) ) );
	}

	// BOM check
	$bom = fread( $fh, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $fh );
	}

	// Detect delimiter from first line
	$first = fgets( $fh );
	rewind( $fh );
	if ( $bom === "\xEF\xBB\xBF" ) {
		fseek( $fh, 3 );
	}
	$delimiter = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

	$headers = fgetcsv( $fh, 0, $delimiter );
	fclose( $fh );

	if ( ! $headers ) {
		wp_send_json_error( array( 'message' => __( 'Could not read headers.', 'wpsl-csv-importer' ) ) );
	}

	wp_send_json_success( array( 'headers' => array_map( 'trim', $headers ) ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// CORRUPT ZIP CLEANUP
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_corrupt_zip_ids() {
	$query = new WP_Query(
		array(
			'post_type'              => 'wpsl_stores',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => 'wpsl_zip',
					'value' => 'ZipCode',
				),
			),
		)
	);
	return $query->posts;
}

function wpsl_csv_count_corrupt_zips() {
	return count( wpsl_csv_get_corrupt_zip_ids() );
}

function wpsl_csv_cleanup_corrupt_zips() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array( 'type' => 'error', 'message' => __( 'Insufficient permissions.', 'wpsl-csv-importer' ) );
	}

	$ids = wpsl_csv_get_corrupt_zip_ids();
	if ( empty( $ids ) ) {
		return array( 'type' => 'info', 'message' => __( 'No corrupt records found.', 'wpsl-csv-importer' ) );
	}

	$deleted = 0;
	foreach ( $ids as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$deleted++;
		}
	}

	return array(
		'type'    => 'success',
		'message' => sprintf(
			/* translators: %d: number of deleted stores */
			__( '<strong>%d store(s) deleted.</strong> You can now re-import them with the correct CSV.', 'wpsl-csv-importer' ),
			$deleted
		),
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// INLINE JAVASCRIPT
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_inline_js() {
	return '(function($){
"use strict";

var cfg = wpslCsvAjax;

/* ── Import form ─────────────────────────────────────────────────────────── */
$("#wpsl-import-form").on("submit", function(e) {
    e.preventDefault();

    var $form   = $(this);
    var $btn    = $form.find(".wpsl-import-submit");
    var $wrap   = $("#wpsl-progress-wrap");
    var $bar    = $("#wpsl-progress-bar");
    var $status = $("#wpsl-progress-status");
    var $result = $("#wpsl-import-result");

    if (!document.getElementById("wpsl_csv_file").files.length) return;

    $result.hide().html("");
    $wrap.show();
    setBar(0, cfg.i18n.uploading);
    $btn.prop("disabled", true).text(cfg.i18n.uploading);

    var fd = new FormData(this);
    fd.append("action",   "wpsl_csv_import_init");
    fd.append("security", cfg.nonce);

    $.ajax({ url: cfg.ajaxurl, type: "POST", data: fd, processData: false, contentType: false })
     .done(function(res) {
        if (!res.success) { showError(res.data.message); return; }
        var d = res.data;
        if (d.total === 0) { finalize({ inserted:0, updated:0, skipped:0, errors:[] }); return; }
        runChunk(d.batch_id, 0, d.total, { inserted:0, updated:0, skipped:0, errors:[] });
     })
     .fail(function() { showError(cfg.i18n.error); });

    function setBar(pct, label) {
        $bar.css("width", pct + "%");
        $status.text(label);
    }

    function runChunk(batchId, offset, total, cum) {
        var shown = Math.min(offset + parseInt(cfg.chunk_size), total);
        setBar(Math.min(Math.round(offset / total * 100), 99), cfg.i18n.processing + " " + shown + " / " + total);

        $.post(cfg.ajaxurl, {
            action:     "wpsl_csv_import_chunk",
            security:   cfg.nonce,
            batch_id:   batchId,
            offset:     offset,
            chunk_size: cfg.chunk_size
        }, function(res) {
            if (!res.success) { showError(res.data.message); return; }
            var d = res.data;
            cum.inserted += d.inserted;
            cum.updated  += d.updated;
            cum.skipped  += d.skipped;
            cum.errors    = cum.errors.concat(d.errors || []);
            if (d.done) {
                setBar(100, cfg.i18n.done);
                setTimeout(function() { finalize(cum); }, 400);
            } else {
                runChunk(batchId, d.next_offset, total, cum);
            }
        }, "json").fail(function() { showError(cfg.i18n.error); });
    }

    function finalize(cum) {
        $wrap.hide();
        $btn.prop("disabled", false).text(cfg.i18n.import_now);

        var parts = [];
        if (cum.inserted) parts.push("<strong>" + cum.inserted + " " + cfg.i18n.inserted + "</strong>");
        if (cum.updated)  parts.push("<strong>" + cum.updated  + " " + cfg.i18n.updated  + "</strong>");
        if (cum.skipped)  parts.push(cum.skipped + " " + cfg.i18n.skipped);

        var msg  = (parts.length ? parts.join(", ") : cfg.i18n.no_changes) + ".";
        var type = cum.errors.length ? "warning" : "success";

        if (cum.errors.length) {
            msg += "<br><strong>" + cfg.i18n.errors_label + " (" + cum.errors.length + "):</strong><br>" +
                   cum.errors.map(function(e) { return $("<div>").text(e).html(); }).join("<br>");
        }

        $result.html("<div class=\"notice notice-" + type + " is-dismissible\"><p>" + msg + "</p></div>").show();

        if (cum.inserted || cum.updated) {
            setTimeout(function() { location.reload(); }, 3500);
        }
    }

    function showError(msg) {
        $wrap.hide();
        $btn.prop("disabled", false).text(cfg.i18n.import_now);
        $result.html("<div class=\"notice notice-error is-dismissible\"><p>" + msg + "</p></div>").show();
    }
});

/* ── Preview form ────────────────────────────────────────────────────────── */
$("#wpsl-preview-form").on("submit", function(e) {
    e.preventDefault();
    if (!document.getElementById("wpsl_csv_preview_file").files.length) return;

    var fd = new FormData(this);
    fd.append("action",   "wpsl_csv_get_headers");
    fd.append("security", cfg.nonce);

    $.ajax({ url: cfg.ajaxurl, type: "POST", data: fd, processData: false, contentType: false })
     .done(function(res) {
        var $out = $("#wpsl-preview-result");
        if (!res.success) { $out.html("<p style=\"color:#d63638;\">" + res.data.message + "</p>"); return; }
        var html = "<p style=\"margin-top:12px;margin-bottom:6px;font-size:13px;\"><strong>" + cfg.i18n.detected_columns + "</strong></p><ul style=\"margin:0;padding-left:1.4em;font-size:13px;line-height:1.8;\">";
        res.data.headers.forEach(function(col) {
            html += "<li><code>" + $("<div>").text(col).html() + "</code></li>";
        });
        $out.html(html + "</ul>");
     });
});

})(jQuery);';
}
