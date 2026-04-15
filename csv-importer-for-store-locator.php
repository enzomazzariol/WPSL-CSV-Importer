<?php
/**
 * Plugin Name: CSV Importer for Store Locator
 * Plugin URI:  https://github.com/enzomazzariol/wpsl-csv-importer
 * Description: Import stores from CSV files directly into WP Store Locator — no file manager needed.
 * Version:     2.0.0
 * Author:      Enzo Paolo Mazzariol Saba
 * Author URI:  https://enzomazzariol.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: csv-importer-for-store-locator
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSL_CSV_VERSION',          '2.0.0' );
define( 'WPSL_CSV_OPTION_MAPPING',   'wpsl_csv_last_mapping' );
define( 'WPSL_CSV_OPTION_SETTINGS',  'wpsl_csv_settings' );
define( 'WPSL_CSV_CHUNK_SIZE',       50 ); // legacy fallback; real default is in settings

// ─────────────────────────────────────────────────────────────────────────────
// SETTINGS HELPER
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_settings() {
	return wp_parse_args(
		get_option( WPSL_CSV_OPTION_SETTINGS, array() ),
		array(
			'chunk_size'     => 50,
			'duplicate_mode' => 'skip',
			'normalize_case' => 0,
		)
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// UNINSTALL HOOK
// ─────────────────────────────────────────────────────────────────────────────

register_uninstall_hook( __FILE__, 'wpsl_csv_importer_uninstall' );

function wpsl_csv_importer_uninstall() {
	delete_option( WPSL_CSV_OPTION_MAPPING );
	delete_option( WPSL_CSV_OPTION_SETTINGS );
}

// ─────────────────────────────────────────────────────────────────────────────
// i18n
// ─────────────────────────────────────────────────────────────────────────────

// WordPress loads translations automatically for hosted plugins (WP 4.6+).

// ─────────────────────────────────────────────────────────────────────────────
// PLUGIN ROW META (donate link in plugins list)
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'plugin_row_meta', 'wpsl_csv_plugin_row_meta', 10, 2 );

function wpsl_csv_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}
	$links[] = '<a href="https://buymeacoffee.com/mazzariolem" target="_blank" rel="noopener noreferrer">'
		. esc_html__( 'Buy me a coffee', 'csv-importer-for-store-locator' )
		. '</a>';
	return $links;
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
		esc_html__( 'CSV Importer for Store Locator requires WP Store Locator to be installed and active. %s', 'csv-importer-for-store-locator' ),
		'<a href="' . esc_url( admin_url( 'plugin-install.php?s=wp+store+locator&tab=search&type=term' ) ) . '">' . esc_html__( 'Install it now', 'csv-importer-for-store-locator' ) . '</a>'
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
			__( 'CSV Importer', 'csv-importer-for-store-locator' ),
			__( 'CSV Importer', 'csv-importer-for-store-locator' ),
			'manage_options',
			'csv-importer-for-store-locator',
			'wpsl_csv_importer_page'
		);
	} else {
		add_menu_page(
			__( 'CSV Importer for Store Locator', 'csv-importer-for-store-locator' ),
			__( 'CSV Importer for Store Locator', 'csv-importer-for-store-locator' ),
			'manage_options',
			'csv-importer-for-store-locator',
			'wpsl_csv_importer_page',
			'dashicons-location-alt',
			30
		);
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN SCRIPTS & STYLES
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'wpsl_csv_importer_enqueue_scripts' );

function wpsl_csv_importer_enqueue_scripts( $hook ) {
	if ( strpos( $hook, 'csv-importer-for-store-locator' ) === false ) {
		return;
	}

	$settings = wpsl_csv_get_settings();

	// Styles
	wp_register_style( 'csv-importer-for-store-locator', false, array(), WPSL_CSV_VERSION );
	wp_enqueue_style( 'csv-importer-for-store-locator' );
	wp_add_inline_style( 'csv-importer-for-store-locator', wpsl_csv_get_admin_css() );

	// Scripts
	wp_register_script( 'csv-importer-for-store-locator', false, array( 'jquery' ), WPSL_CSV_VERSION, true );
	wp_enqueue_script( 'csv-importer-for-store-locator' );

	wp_localize_script(
		'csv-importer-for-store-locator',
		'wpslCsvAjax',
		array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'wpsl_csv_ajax' ),
			'chunk_size' => (int) $settings['chunk_size'],
			'i18n'       => array(
				'uploading'        => __( 'Uploading file\u2026', 'csv-importer-for-store-locator' ),
				'processing'       => __( 'Processing', 'csv-importer-for-store-locator' ),
				'done'             => __( 'Import complete.', 'csv-importer-for-store-locator' ),
				'error'            => __( 'An error occurred. Please try again.', 'csv-importer-for-store-locator' ),
				'import_now'       => __( 'Import now', 'csv-importer-for-store-locator' ),
				'inserted'         => __( 'store(s) inserted', 'csv-importer-for-store-locator' ),
				'updated'          => __( 'store(s) updated', 'csv-importer-for-store-locator' ),
				'skipped'          => __( 'skipped', 'csv-importer-for-store-locator' ),
				'no_changes'       => __( 'No changes', 'csv-importer-for-store-locator' ),
				'errors_label'     => __( 'Errors', 'csv-importer-for-store-locator' ),
				'detected_columns' => __( 'Detected columns:', 'csv-importer-for-store-locator' ),
				'confirm_delete_all' => __( 'This will permanently delete ALL stores from WP Store Locator. This cannot be undone. Continue?', 'csv-importer-for-store-locator' ),
				'confirm_delete_cat' => __( 'Delete all stores in the selected category? This cannot be undone.', 'csv-importer-for-store-locator' ),
				'select_category'    => __( 'Please select a category first.', 'csv-importer-for-store-locator' ),
				'deleting'           => __( 'Deleting\u2026', 'csv-importer-for-store-locator' ),
				'deleted'            => __( 'store(s) deleted', 'csv-importer-for-store-locator' ),
				'regeocoding'        => __( 'Triggering re-geocoding\u2026', 'csv-importer-for-store-locator' ),
				'regeocod_done'      => __( 'Done. WPSL will geocode these stores shortly.', 'csv-importer-for-store-locator' ),
				'no_ungeocoded'      => __( 'No ungeocoded stores found.', 'csv-importer-for-store-locator' ),
				'select_file'        => __( 'Please select a CSV file.', 'csv-importer-for-store-locator' ),
				'enter_url'          => __( 'Please enter a URL.', 'csv-importer-for-store-locator' ),
				'loading_preview'    => __( 'Loading preview\u2026', 'csv-importer-for-store-locator' ),
				'action_insert'      => __( 'Insert', 'csv-importer-for-store-locator' ),
				'action_update'      => __( 'Update', 'csv-importer-for-store-locator' ),
				'action_skip'        => __( 'Skip', 'csv-importer-for-store-locator' ),
				'col_number'         => __( '#', 'csv-importer-for-store-locator' ),
				'col_name'           => __( 'Name', 'csv-importer-for-store-locator' ),
				'col_address'        => __( 'Address', 'csv-importer-for-store-locator' ),
				'col_action'         => __( 'Action', 'csv-importer-for-store-locator' ),
				/* translators: %d: total number of rows in the CSV */
				'showing_first'      => __( 'Showing first 10 of %d total rows.', 'csv-importer-for-store-locator' ),
			),
		)
	);

	wp_add_inline_script( 'csv-importer-for-store-locator', wpsl_csv_get_inline_js() );
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN CSS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_admin_css() {
	return '
.wpsl-tab-content { margin-top: 20px; }
.wpsl-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px 24px;
	margin-bottom: 20px;
}
.wpsl-card > h2:first-child,
.wpsl-card > h3:first-child { margin-top: 0; }
.wpsl-flex { display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; }
.wpsl-main  { flex: 1; min-width: 360px; }
.wpsl-sidebar { max-width: 320px; flex-shrink: 0; }
.wpsl-stat-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
	gap: 12px;
	margin-bottom: 8px;
}
.wpsl-stat-box {
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 16px 12px;
	text-align: center;
}
.wpsl-stat-num {
	font-size: 30px;
	font-weight: 600;
	color: #1d2327;
	line-height: 1;
	margin-bottom: 6px;
}
.wpsl-stat-label {
	font-size: 11px;
	color: #646970;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.wpsl-stat-num.is-warning { color: #d63638; }
.wpsl-progress-wrap {
	display: none;
	margin: 12px 0 0;
	background: #fff;
	padding: 16px 20px;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}
.wpsl-progress-track {
	background: #e0e0e0;
	border-radius: 4px;
	height: 18px;
	overflow: hidden;
	margin-top: 8px;
}
.wpsl-progress-bar {
	background: #2271b1;
	height: 100%;
	width: 0%;
	transition: width .25s ease;
}
.wpsl-bulk-sep { border: none; border-top: 1px solid #f0f0f1; margin: 20px 0; }
.wpsl-cat-list { margin: 0; padding: 0; list-style: none; }
.wpsl-cat-list li {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 6px 0;
	border-bottom: 1px solid #f0f0f1;
	font-size: 13px;
}
.wpsl-cat-list li:last-child { border-bottom: none; }
.wpsl-cat-count {
	background: #f0f0f1;
	color: #50575e;
	font-size: 11px;
	padding: 2px 7px;
	border-radius: 10px;
}
';
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
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_csv_export_nonce'] ) ), 'wpsl_csv_export' )
	) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) );
	}

	$all_export_cols = wpsl_csv_get_export_column_definitions();

	// Which columns to include (default: all)
	$requested = isset( $_POST['export_columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['export_columns'] ) ) : array_keys( $all_export_cols );
	$selected  = array_intersect( array_keys( $all_export_cols ), array_map( 'sanitize_key', $requested ) );
	if ( empty( $selected ) ) {
		$selected = array_keys( $all_export_cols );
	}

	// Build WP_Query args
	$args = array(
		'post_type'      => 'wpsl_stores',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	);

	// Filter: category
	$filter_cat = intval( $_POST['filter_category'] ?? 0 );
	if ( $filter_cat > 0 ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'wpsl_store_category',
				'field'    => 'term_id',
				'terms'    => $filter_cat,
			),
		);
	}

	// Filter: state and/or ungeocoded
	$filter_state      = sanitize_text_field( wp_unslash( $_POST['filter_state'] ?? '' ) );
	$filter_ungeocoded = ! empty( $_POST['filter_ungeocoded'] );
	$meta_query        = array();

	if ( $filter_state !== '' ) {
		$meta_query[] = array(
			'key'     => 'wpsl_state',
			'value'   => $filter_state,
			'compare' => 'LIKE',
		);
	}
	if ( $filter_ungeocoded ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array( 'key' => 'wpsl_lat', 'value' => '', 'compare' => '=' ),
			array( 'key' => 'wpsl_lat', 'compare' => 'NOT EXISTS' ),
		);
	}
	if ( ! empty( $meta_query ) ) {
		$args['meta_query'] = $meta_query;
	}

	$stores = get_posts( $args );

	// Stream CSV
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="wpsl-stores-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for Excel

	// Header row
	$header_row = array();
	foreach ( $selected as $key ) {
		$header_row[] = $all_export_cols[ $key ]['header'];
	}
	fputcsv( $output, $header_row );

	// Data rows
	foreach ( $stores as $store ) {
		$row = array();
		foreach ( $selected as $key ) {
			$col = $all_export_cols[ $key ];
			if ( $key === 'name' ) {
				$row[] = $store->post_title;
			} elseif ( $key === 'category' ) {
				$terms = wp_get_post_terms( $store->ID, 'wpsl_store_category', array( 'fields' => 'names' ) );
				$row[] = is_array( $terms ) ? implode( '|', $terms ) : '';
			} else {
				$row[] = get_post_meta( $store->ID, $col['meta'], true );
			}
		}
		fputcsv( $output, $row );
	}

	fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN POST: SAVE SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_post_wpsl_csv_save_settings', 'wpsl_csv_handle_save_settings' );

function wpsl_csv_handle_save_settings() {
	if (
		empty( $_POST['wpsl_csv_settings_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_csv_settings_nonce'] ) ), 'wpsl_csv_save_settings' )
	) {
		wp_die( esc_html__( 'Security check failed.', 'csv-importer-for-store-locator' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) );
	}

	$dup_mode_raw = sanitize_key( wp_unslash( $_POST['default_duplicate_mode'] ?? 'skip' ) );
	$settings     = array(
		'chunk_size'     => max( 1, min( 500, intval( $_POST['chunk_size'] ?? 50 ) ) ),
		'duplicate_mode' => in_array( $dup_mode_raw, array( 'skip', 'update', 'insert' ), true ) ? $dup_mode_raw : 'skip',
		'normalize_case' => ! empty( $_POST['normalize_case'] ) ? 1 : 0,
	);
	update_option( WPSL_CSV_OPTION_SETTINGS, $settings );

	// Save mapping defaults if column fields were submitted
	if ( isset( $_POST['col_name'] ) ) {
		wpsl_csv_save_mapping( $_POST );
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'csv-importer-for-store-locator', 'tab' => 'settings', 'saved' => '1' ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN POST: CORRUPT ZIP CLEANUP
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_post_wpsl_csv_cleanup', 'wpsl_csv_handle_cleanup_post' );

function wpsl_csv_handle_cleanup_post() {
	if (
		empty( $_POST['wpsl_cleanup_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_cleanup_nonce'] ) ), 'wpsl_cleanup' )
	) {
		wp_die( esc_html__( 'Security check failed.', 'csv-importer-for-store-locator' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) );
	}

	$result  = wpsl_csv_cleanup_corrupt_zips();
	$message = rawurlencode( wp_strip_all_tags( $result['message'] ) );
	$type    = rawurlencode( $result['type'] );

	wp_safe_redirect(
		add_query_arg(
			array( 'page' => 'csv-importer-for-store-locator', 'tab' => 'manage', 'cleanup_type' => $type, 'cleanup_msg' => $message ),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN PAGE — TAB ROUTER
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_importer_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'csv-importer-for-store-locator' ) );
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'import'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation
	$valid_tabs = array( 'import', 'export', 'manage', 'settings' );
	if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
		$active_tab = 'import';
	}

	$base_url = add_query_arg( 'page', 'csv-importer-for-store-locator', admin_url( 'admin.php' ) );
	$tabs     = array(
		'import'   => __( 'Import', 'csv-importer-for-store-locator' ),
		'export'   => __( 'Export', 'csv-importer-for-store-locator' ),
		'manage'   => __( 'Manage Stores', 'csv-importer-for-store-locator' ),
		'settings' => __( 'Settings', 'csv-importer-for-store-locator' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CSV Importer for Store Locator', 'csv-importer-for-store-locator' ); ?></h1>

		<nav class="nav-tab-wrapper wp-clearfix">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
				   class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="wpsl-tab-content">
			<?php
			switch ( $active_tab ) {
				case 'export':
					wpsl_csv_tab_export();
					break;
				case 'manage':
					wpsl_csv_tab_manage();
					break;
				case 'settings':
					wpsl_csv_tab_settings();
					break;
				default:
					wpsl_csv_tab_import();
					break;
			}
			?>
		</div>

		<p style="margin-top:24px;color:#666;font-size:12px;">
			<?php
			printf(
				/* translators: %s: Buy Me a Coffee link */
				esc_html__( 'If this plugin saves you time, consider %s — it helps keep it maintained and free.', 'csv-importer-for-store-locator' ),
				'<a href="https://buymeacoffee.com/mazzariolem" target="_blank" rel="noopener noreferrer">' . esc_html__( 'buying me a coffee', 'csv-importer-for-store-locator' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB: IMPORT
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_tab_import() {
	$settings = wpsl_csv_get_settings();
	$saved    = get_option( WPSL_CSV_OPTION_MAPPING, array() );
	$fields   = wpsl_csv_get_field_definitions();

	// Defaults: saved mapping overrides settings defaults for dup mode / normalize
	$dup_default   = $saved['duplicate_mode'] ?? $settings['duplicate_mode'];
	$norm_default  = isset( $saved['normalize_case'] ) ? (bool) $saved['normalize_case'] : (bool) $settings['normalize_case'];
	?>

	<!-- Progress bar -->
	<div id="wpsl-progress-wrap" class="wpsl-progress-wrap">
		<p id="wpsl-progress-status" style="margin:0 0 6px;font-weight:500;"></p>
		<div class="wpsl-progress-track">
			<div id="wpsl-progress-bar" class="wpsl-progress-bar"></div>
		</div>
	</div>

	<div id="wpsl-import-result" style="display:none;margin-bottom:16px;"></div>

	<div class="wpsl-flex">

		<!-- ── IMPORT FORM ──────────────────────────────────────────────── -->
		<div class="wpsl-card wpsl-main">
			<h2><?php esc_html_e( 'Import CSV', 'csv-importer-for-store-locator' ); ?></h2>

			<form id="wpsl-import-form" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpsl_csv_import', 'wpsl_csv_import_nonce' ); ?>
				<table class="form-table" role="presentation">

					<!-- Source toggle -->
					<tr>
						<th><?php esc_html_e( 'Source', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<label style="margin-right:20px;">
								<input type="radio" name="import_source" value="file" checked>
								<?php esc_html_e( 'Upload file', 'csv-importer-for-store-locator' ); ?>
							</label>
							<label>
								<input type="radio" name="import_source" value="url">
								<?php esc_html_e( 'Import from URL', 'csv-importer-for-store-locator' ); ?>
							</label>
						</td>
					</tr>

					<!-- File input (shown by default) -->
					<tr id="wpsl-file-input-row">
						<th><label for="wpsl_csv_file"><?php esc_html_e( 'CSV File', 'csv-importer-for-store-locator' ); ?></label></th>
						<td>
							<input type="file" id="wpsl_csv_file" name="wpsl_csv_file" accept=".csv,text/csv">
							<p class="description">
								<?php
								/* translators: %s: maximum upload file size allowed by the server, e.g. "8M" */
								printf( esc_html__( 'Maximum %s. UTF-8 encoding recommended.', 'csv-importer-for-store-locator' ), esc_html( ini_get( 'upload_max_filesize' ) ) ); ?>
							</p>
						</td>
					</tr>

					<!-- URL input (hidden by default) -->
					<tr id="wpsl-url-input-row" style="display:none;">
						<th><label for="wpsl_csv_url"><?php esc_html_e( 'CSV URL', 'csv-importer-for-store-locator' ); ?></label></th>
						<td>
							<input type="url" id="wpsl_csv_url" name="wpsl_csv_url" class="large-text"
								placeholder="https://docs.google.com/spreadsheets/d/…/edit">
							<p class="description">
								<?php esc_html_e( 'Paste a public CSV URL or a Google Sheets link — both the edit URL and the export URL are accepted. The spreadsheet must be shared publicly (anyone with the link can view).', 'csv-importer-for-store-locator' ); ?>
							</p>
						</td>
					</tr>

					<!-- Column mapping -->
					<?php foreach ( $fields as $key => $f ) :
						$val = isset( $saved[ 'col_' . $f['input_name'] ] ) ? $saved[ 'col_' . $f['input_name'] ] : $f['default'];
					?>
					<tr>
						<th>
							<label for="col_<?php echo esc_attr( $f['input_name'] ); ?>">
								<?php echo esc_html( $f['label'] ); ?>
								<?php if ( ! $f['required'] ) : ?>
									<small>(<?php esc_html_e( 'optional', 'csv-importer-for-store-locator' ); ?>)</small>
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
						<th><?php esc_html_e( 'Existing stores', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<fieldset>
								<?php
								$dup_opts = array(
									'skip'   => __( 'Skip — do not modify existing stores', 'csv-importer-for-store-locator' ),
									'update' => __( 'Update — overwrite data of existing stores', 'csv-importer-for-store-locator' ),
									'insert' => __( 'Always insert — ignore duplicates', 'csv-importer-for-store-locator' ),
								);
								foreach ( $dup_opts as $val => $label ) :
								?>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="duplicate_mode" value="<?php echo esc_attr( $val ); ?>"
											<?php checked( $dup_default, $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Duplicates are detected by exact store name + address.', 'csv-importer-for-store-locator' ); ?></p>
						</td>
					</tr>

					<!-- Capitalization -->
					<tr>
						<th><?php esc_html_e( 'Capitalization', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="normalize_case" value="1"
									<?php checked( $norm_default ); ?>>
								<?php esc_html_e( 'Normalize capitalization in name, city, and state', 'csv-importer-for-store-locator' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Converts KANSAS CITY → Kansas City. Useful when the CSV is all caps.', 'csv-importer-for-store-locator' ); ?>
							</p>
						</td>
					</tr>

					<!-- Dry run -->
					<tr>
						<th><?php esc_html_e( 'Dry run', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dry_run" id="wpsl_dry_run" value="1">
								<?php esc_html_e( 'Preview first 10 rows before importing', 'csv-importer-for-store-locator' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Shows what would be inserted, updated, or skipped — without making any changes to your data.', 'csv-importer-for-store-locator' ); ?>
							</p>
						</td>
					</tr>

				</table>

				<button type="submit" class="button button-primary button-large wpsl-import-submit">
					<?php esc_html_e( 'Import now', 'csv-importer-for-store-locator' ); ?>
				</button>
			</form>

			<!-- Dry run modal -->
			<div id="wpsl-dryrun-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);z-index:100000;">
				<div style="background:#fff;max-width:700px;margin:60px auto;border-radius:4px;box-shadow:0 8px 30px rgba(0,0,0,.3);display:flex;flex-direction:column;max-height:80vh;">
					<div style="padding:16px 24px;border-bottom:1px solid #dcdcde;flex-shrink:0;">
						<h2 style="margin:0;font-size:15px;"><?php esc_html_e( 'Import Preview — first 10 rows', 'csv-importer-for-store-locator' ); ?></h2>
					</div>
					<div id="wpsl-dryrun-body" style="padding:16px 24px;overflow-y:auto;flex:1;font-size:13px;"></div>
					<div style="padding:12px 24px;border-top:1px solid #dcdcde;display:flex;gap:8px;flex-shrink:0;">
						<button id="wpsl-dryrun-confirm" class="button button-primary">
							<?php esc_html_e( 'Confirm — run full import', 'csv-importer-for-store-locator' ); ?>
						</button>
						<button id="wpsl-dryrun-cancel" class="button button-secondary">
							<?php esc_html_e( 'Cancel', 'csv-importer-for-store-locator' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
		<div class="wpsl-sidebar">

			<!-- How it works -->
			<div class="wpsl-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'How it works', 'csv-importer-for-store-locator' ); ?></h3>
				<ol style="margin:0;padding-left:1.2em;line-height:1.8;">
					<li><?php esc_html_e( 'Prepare your CSV with headers in the first row.', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Upload the file using the form on the left.', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Map your column names to WPSL fields.', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Click "Import now" and watch the progress bar.', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Stores appear under WP Store Locator → Stores.', 'csv-importer-for-store-locator' ); ?></li>
				</ol>
			</div>

			<!-- Preview columns -->
			<div class="wpsl-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Preview CSV columns', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;margin-bottom:12px;">
					<?php esc_html_e( 'Upload your CSV to see its exact column names before importing.', 'csv-importer-for-store-locator' ); ?>
				</p>
				<form id="wpsl-preview-form" enctype="multipart/form-data">
					<input type="file" id="wpsl_csv_preview_file" name="wpsl_csv_preview" accept=".csv,text/csv" style="margin-bottom:8px;display:block;">
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'View columns', 'csv-importer-for-store-locator' ); ?>
					</button>
				</form>
				<div id="wpsl-preview-result"></div>
			</div>

			<!-- CSV format reference -->
			<div class="wpsl-card" style="background:#f0f6fc;border-color:#72aee6;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'CSV Format', 'csv-importer-for-store-locator' ); ?></h3>

				<p style="margin:0 0 4px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#50575e;">
					<?php esc_html_e( 'Required', 'csv-importer-for-store-locator' ); ?>
				</p>
				<code style="display:block;font-size:11px;background:#fff;padding:7px 10px;border-radius:3px;border:1px solid #c3d4e4;margin-bottom:10px;word-break:break-all;">Name, Address, City, State, ZipCode</code>

				<p style="margin:0 0 4px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#50575e;">
					<?php esc_html_e( 'Optional', 'csv-importer-for-store-locator' ); ?>
				</p>
				<code style="display:block;font-size:11px;background:#fff;padding:7px 10px;border-radius:3px;border:1px solid #c3d4e4;margin-bottom:12px;word-break:break-all;">Country, Phone, Fax, Email, Website, Lat, Lng, Category</code>

				<ul style="margin:0 0 14px;padding-left:1.3em;font-size:12px;line-height:1.9;color:#2c3338;">
					<li><?php esc_html_e( 'Comma or semicolon — auto-detected.', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'UTF-8 recommended (Excel BOM supported).', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Multiple categories: separate with |', 'csv-importer-for-store-locator' ); ?></li>
					<li><?php esc_html_e( 'Lat/Lng columns skip geocoding.', 'csv-importer-for-store-locator' ); ?></li>
				</ul>

				<?php
				$sample_rows = array(
					array( 'Name', 'Address', 'City', 'State', 'ZipCode', 'Country', 'Phone', 'Email', 'Website', 'Category' ),
					array( 'Example Store', '123 Main St', 'Springfield', 'IL', '62701', 'United States', '555-0100', 'info@example.com', 'https://example.com', 'Retail' ),
					array( 'Another Store', '456 Oak Ave', 'Shelbyville', 'IL', '62565', 'United States', '555-0200', '', 'https://another.com', 'Retail|Pharmacy' ),
				);
				$sample_csv = '';
				foreach ( $sample_rows as $row ) {
					$sample_csv .= implode( ',', array_map( function( $v ) {
						return strpos( $v, ',' ) !== false ? '"' . $v . '"' : $v;
					}, $row ) ) . "\r\n";
				}
				$sample_uri = 'data:text/csv;charset=utf-8,' . rawurlencode( $sample_csv );
				?>
				<a href="<?php echo esc_attr( $sample_uri ); ?>" download="wpsl-sample.csv"
				   style="display:inline-flex;align-items:center;gap:5px;font-size:12px;text-decoration:none;color:#2271b1;font-weight:500;border:1px solid #2271b1;border-radius:3px;padding:4px 10px;">
					&#x21E9; <?php esc_html_e( 'Download sample CSV', 'csv-importer-for-store-locator' ); ?>
				</a>
			</div>

		</div>
	</div>
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB: EXPORT
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_tab_export() {
	$all_cols   = wpsl_csv_get_export_column_definitions();
	$categories = get_terms( array(
		'taxonomy'   => 'wpsl_store_category',
		'hide_empty' => false,
		'orderby'    => 'name',
	) );
	if ( is_wp_error( $categories ) ) {
		$categories = array();
	}
	?>
	<div class="wpsl-flex">
		<div class="wpsl-card wpsl-main">
			<h2><?php esc_html_e( 'Export Stores to CSV', 'csv-importer-for-store-locator' ); ?></h2>
			<p style="color:#50575e;margin-top:-8px;margin-bottom:20px;">
				<?php esc_html_e( 'Download all WP Store Locator stores as a CSV file compatible with this importer.', 'csv-importer-for-store-locator' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'wpsl_csv_export', 'wpsl_csv_export_nonce' ); ?>

				<h3><?php esc_html_e( 'Filters', 'csv-importer-for-store-locator' ); ?></h3>
				<table class="form-table" role="presentation">
					<!-- Filter: Category -->
					<tr>
						<th><label for="filter_category"><?php esc_html_e( 'Category', 'csv-importer-for-store-locator' ); ?></label></th>
						<td>
							<select id="filter_category" name="filter_category" style="min-width:200px;">
								<option value="0"><?php esc_html_e( '— All categories —', 'csv-importer-for-store-locator' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>">
										<?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<!-- Filter: State -->
					<tr>
						<th><label for="filter_state"><?php esc_html_e( 'State / Province', 'csv-importer-for-store-locator' ); ?></label></th>
						<td>
							<input type="text" id="filter_state" name="filter_state" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. California — leave empty for all', 'csv-importer-for-store-locator' ); ?>">
						</td>
					</tr>

					<!-- Filter: Ungeocoded only -->
					<tr>
						<th><?php esc_html_e( 'Geocoding', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="filter_ungeocoded" value="1">
								<?php esc_html_e( 'Only export stores without coordinates (lat/lng)', 'csv-importer-for-store-locator' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Columns to export', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="margin-bottom:8px;">
					<a href="#" id="wpsl-export-select-all" style="font-size:13px;"><?php esc_html_e( 'Select all', 'csv-importer-for-store-locator' ); ?></a>
					&nbsp;|&nbsp;
					<a href="#" id="wpsl-export-deselect-all" style="font-size:13px;"><?php esc_html_e( 'Deselect all', 'csv-importer-for-store-locator' ); ?></a>
				</p>
				<div style="display:flex;flex-wrap:wrap;gap:8px 24px;margin-bottom:24px;">
					<?php foreach ( $all_cols as $key => $col ) : ?>
						<label style="display:flex;align-items:center;gap:6px;font-size:13px;">
							<input type="checkbox" name="export_columns[]"
								value="<?php echo esc_attr( $key ); ?>" checked class="wpsl-export-col-check">
							<?php echo esc_html( $col['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<button type="submit" name="wpsl_do_export" value="1" class="button button-primary button-large">
					<?php esc_html_e( 'Download CSV', 'csv-importer-for-store-locator' ); ?>
				</button>
			</form>
		</div>

		<div class="wpsl-sidebar">
			<div class="wpsl-card" style="background:#fff3cd;border-color:#ffc107;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Export format', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;margin-bottom:0;">
					&bull; <?php esc_html_e( 'UTF-8 BOM (compatible with Excel).', 'csv-importer-for-store-locator' ); ?><br>
					&bull; <?php esc_html_e( 'Multiple categories joined with |', 'csv-importer-for-store-locator' ); ?><br>
					&bull; <?php esc_html_e( 'The exported file is directly re-importable.', 'csv-importer-for-store-locator' ); ?>
				</p>
			</div>
		</div>
	</div>
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB: MANAGE STORES
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_tab_manage() {
	// Cleanup result notice (from redirect) — phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect params set by our own wp_safe_redirect
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['cleanup_msg'] ) ) {
		$type = in_array( sanitize_key( wp_unslash( $_GET['cleanup_type'] ?? 'info' ) ), array( 'success', 'info', 'warning', 'error' ), true )
			? sanitize_key( wp_unslash( $_GET['cleanup_type'] ) ) : 'info';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>'
			. esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['cleanup_msg'] ) ) ) )
			. '</p></div>';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	// Stats
	$stats     = wpsl_csv_get_store_stats();
	$corrupt   = wpsl_csv_count_corrupt_zips();
	$all_cats  = get_terms( array(
		'taxonomy'   => 'wpsl_store_category',
		'hide_empty' => false,
		'orderby'    => 'name',
	) );
	if ( is_wp_error( $all_cats ) ) {
		$all_cats = array();
	}
	?>

	<div class="wpsl-flex">
		<div style="flex:1;min-width:360px;">

			<!-- Stats -->
			<div class="wpsl-card">
				<h2><?php esc_html_e( 'Store Statistics', 'csv-importer-for-store-locator' ); ?></h2>
				<div class="wpsl-stat-grid">
					<div class="wpsl-stat-box">
						<div class="wpsl-stat-num"><?php echo intval( $stats['total'] ); ?></div>
						<div class="wpsl-stat-label"><?php esc_html_e( 'Total stores', 'csv-importer-for-store-locator' ); ?></div>
					</div>
					<div class="wpsl-stat-box">
						<div class="wpsl-stat-num<?php echo $stats['ungeocoded'] > 0 ? ' is-warning' : ''; ?>">
							<?php echo intval( $stats['ungeocoded'] ); ?>
						</div>
						<div class="wpsl-stat-label"><?php esc_html_e( 'Without coordinates', 'csv-importer-for-store-locator' ); ?></div>
					</div>
					<div class="wpsl-stat-box">
						<div class="wpsl-stat-num<?php echo $corrupt > 0 ? ' is-warning' : ''; ?>">
							<?php echo intval( $corrupt ); ?>
						</div>
						<div class="wpsl-stat-label"><?php esc_html_e( 'Corrupt records', 'csv-importer-for-store-locator' ); ?></div>
					</div>
					<div class="wpsl-stat-box">
						<div class="wpsl-stat-num"><?php echo intval( $stats['categories'] ); ?></div>
						<div class="wpsl-stat-label"><?php esc_html_e( 'Categories', 'csv-importer-for-store-locator' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Top categories -->
			<?php if ( ! empty( $stats['top_cats'] ) ) : ?>
			<div class="wpsl-card">
				<h3><?php esc_html_e( 'Top categories', 'csv-importer-for-store-locator' ); ?></h3>
				<ul class="wpsl-cat-list">
					<?php foreach ( $stats['top_cats'] as $cat ) : ?>
						<li>
							<span><?php echo esc_html( $cat->name ); ?></span>
							<span class="wpsl-cat-count"><?php echo intval( $cat->count ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<!-- Bulk Actions -->
			<div class="wpsl-card">
				<h3><?php esc_html_e( 'Bulk Actions', 'csv-importer-for-store-locator' ); ?></h3>

				<!-- Delete all -->
				<p style="margin-bottom:6px;font-weight:500;"><?php esc_html_e( 'Delete all stores', 'csv-importer-for-store-locator' ); ?></p>
				<p style="font-size:13px;color:#50575e;margin-top:0;">
					<?php esc_html_e( 'Permanently remove all stores from WP Store Locator.', 'csv-importer-for-store-locator' ); ?>
				</p>
				<button id="wpsl-delete-all-btn" class="button button-secondary" <?php echo $stats['total'] === 0 ? 'disabled' : ''; ?>>
					<?php
					/* translators: %d: total number of stores */
					printf( esc_html__( 'Delete all %d stores', 'csv-importer-for-store-locator' ), intval( $stats['total'] ) ); ?>
				</button>
				<div id="wpsl-delete-all-wrap" class="wpsl-progress-wrap">
					<p id="wpsl-delete-all-status" style="margin:0 0 6px;font-weight:500;"></p>
					<div class="wpsl-progress-track"><div id="wpsl-delete-all-bar" class="wpsl-progress-bar"></div></div>
				</div>

				<hr class="wpsl-bulk-sep">

				<!-- Delete by category -->
				<p style="margin-bottom:6px;font-weight:500;"><?php esc_html_e( 'Delete by category', 'csv-importer-for-store-locator' ); ?></p>
				<p style="font-size:13px;color:#50575e;margin-top:0;">
					<?php esc_html_e( 'Permanently remove all stores assigned to a specific category.', 'csv-importer-for-store-locator' ); ?>
				</p>
				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<select id="wpsl-delete-cat-select" style="min-width:200px;" <?php echo empty( $all_cats ) ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( '— Select category —', 'csv-importer-for-store-locator' ); ?></option>
						<?php foreach ( $all_cats as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>">
								<?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button id="wpsl-delete-cat-btn" class="button button-secondary" <?php echo empty( $all_cats ) ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Delete category stores', 'csv-importer-for-store-locator' ); ?>
					</button>
				</div>
				<div id="wpsl-delete-cat-wrap" class="wpsl-progress-wrap" style="margin-top:12px;">
					<p id="wpsl-delete-cat-status" style="margin:0 0 6px;font-weight:500;"></p>
					<div class="wpsl-progress-track"><div id="wpsl-delete-cat-bar" class="wpsl-progress-bar"></div></div>
				</div>

				<hr class="wpsl-bulk-sep">

				<!-- Re-geocode ungeocoded -->
				<p style="margin-bottom:6px;font-weight:500;"><?php esc_html_e( 'Re-geocode stores without coordinates', 'csv-importer-for-store-locator' ); ?></p>
				<p style="font-size:13px;color:#50575e;margin-top:0;">
					<?php esc_html_e( 'Clears lat/lng from stores that have no coordinates and re-publishes them so WPSL re-geocodes them. Rate limits may apply.', 'csv-importer-for-store-locator' ); ?>
				</p>
				<button id="wpsl-regeocod-btn" class="button button-secondary"
					data-count="<?php echo intval( $stats['ungeocoded'] ); ?>"
					<?php echo $stats['ungeocoded'] === 0 ? 'disabled' : ''; ?>>
					<?php
					/* translators: %d: number of stores without geocoding coordinates */
					printf( esc_html__( 'Re-geocode %d store(s)', 'csv-importer-for-store-locator' ), intval( $stats['ungeocoded'] ) ); ?>
				</button>
				<div id="wpsl-regeocod-wrap" class="wpsl-progress-wrap" style="margin-top:12px;">
					<p id="wpsl-regeocod-status" style="margin:0 0 6px;font-weight:500;"></p>
					<div class="wpsl-progress-track"><div id="wpsl-regeocod-bar" class="wpsl-progress-bar"></div></div>
				</div>

			</div>

		</div>

		<!-- Sidebar: corrupt records -->
		<div class="wpsl-sidebar">
			<?php if ( $corrupt > 0 ) : ?>
			<div class="wpsl-card" style="border-color:#d63638;">
				<h3 style="margin-top:0;color:#d63638;"><?php esc_html_e( 'Corrupt records detected', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;">
					<?php printf(
						/* translators: %d: number of corrupt stores */
						esc_html__( '%d store(s) have "ZipCode" as a literal zip code value — corrupt data from a previous import. Delete and re-import them.', 'csv-importer-for-store-locator' ),
						intval( $corrupt )
					); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpsl_csv_cleanup">
					<?php wp_nonce_field( 'wpsl_cleanup', 'wpsl_cleanup_nonce' ); ?>
					<?php
					/* translators: %d: number of corrupt stores to delete */
					$confirm_delete_corrupt = sprintf( __( 'Delete %d corrupt store(s)? This cannot be undone.', 'csv-importer-for-store-locator' ), intval( $corrupt ) );
					?>
					<button type="submit" class="button button-secondary"
						onclick="return confirm('<?php echo esc_js( $confirm_delete_corrupt ); ?>');">
						<?php
						/* translators: %d: number of corrupt stores to delete */
						printf( esc_html__( 'Delete %d corrupt record(s)', 'csv-importer-for-store-locator' ), intval( $corrupt ) ); ?>
					</button>
				</form>
			</div>
			<?php else : ?>
			<div class="wpsl-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Data integrity', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;color:#50575e;margin:0;">
					<?php esc_html_e( 'No corrupt records detected.', 'csv-importer-for-store-locator' ); ?>
				</p>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// TAB: SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_tab_settings() {
	$settings = wpsl_csv_get_settings();
	$saved    = get_option( WPSL_CSV_OPTION_MAPPING, array() );
	$fields   = wpsl_csv_get_field_definitions();

	if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect param set by our own wp_safe_redirect
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'csv-importer-for-store-locator' ) . '</p></div>';
	}
	?>
	<div class="wpsl-flex">
		<div class="wpsl-card wpsl-main">
			<h2><?php esc_html_e( 'Settings', 'csv-importer-for-store-locator' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpsl_csv_save_settings">
				<?php wp_nonce_field( 'wpsl_csv_save_settings', 'wpsl_csv_settings_nonce' ); ?>

				<!-- General -->
				<h3><?php esc_html_e( 'General', 'csv-importer-for-store-locator' ); ?></h3>
				<table class="form-table" role="presentation">

					<tr>
						<th><label for="chunk_size"><?php esc_html_e( 'Chunk size', 'csv-importer-for-store-locator' ); ?></label></th>
						<td>
							<input type="number" id="chunk_size" name="chunk_size"
								value="<?php echo intval( $settings['chunk_size'] ); ?>"
								min="1" max="500" class="small-text">
							<p class="description">
								<?php esc_html_e( 'Rows processed per AJAX request. Lower = safer on slow servers. Default: 50.', 'csv-importer-for-store-locator' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Default duplicate mode', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<fieldset>
								<?php
								$dup_opts = array(
									'skip'   => __( 'Skip — do not modify existing stores', 'csv-importer-for-store-locator' ),
									'update' => __( 'Update — overwrite data of existing stores', 'csv-importer-for-store-locator' ),
									'insert' => __( 'Always insert — ignore duplicates', 'csv-importer-for-store-locator' ),
								);
								foreach ( $dup_opts as $val => $label ) :
								?>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="default_duplicate_mode" value="<?php echo esc_attr( $val ); ?>"
											<?php checked( $settings['duplicate_mode'], $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Default capitalization', 'csv-importer-for-store-locator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="normalize_case" value="1"
									<?php checked( ! empty( $settings['normalize_case'] ) ); ?>>
								<?php esc_html_e( 'Normalize capitalization by default', 'csv-importer-for-store-locator' ); ?>
							</label>
						</td>
					</tr>

				</table>

				<!-- Column mapping defaults -->
				<h3><?php esc_html_e( 'Default column mapping', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;color:#50575e;margin-top:-8px;">
					<?php esc_html_e( 'These values pre-fill the Import form. They are also updated automatically after each successful import.', 'csv-importer-for-store-locator' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $f ) :
						$val = isset( $saved[ 'col_' . $f['input_name'] ] ) ? $saved[ 'col_' . $f['input_name'] ] : $f['default'];
					?>
					<tr>
						<th>
							<label for="settings_col_<?php echo esc_attr( $f['input_name'] ); ?>">
								<?php echo esc_html( $f['label'] ); ?>
								<?php if ( ! $f['required'] ) : ?>
									<small>(<?php esc_html_e( 'optional', 'csv-importer-for-store-locator' ); ?>)</small>
								<?php endif; ?>
							</label>
						</th>
						<td>
							<input type="text"
								id="settings_col_<?php echo esc_attr( $f['input_name'] ); ?>"
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
				</table>

				<p>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Save settings', 'csv-importer-for-store-locator' ); ?>
					</button>
				</p>
			</form>
		</div>

		<div class="wpsl-sidebar">
			<div class="wpsl-card">
				<h3 style="margin-top:0;"><?php esc_html_e( 'About chunk size', 'csv-importer-for-store-locator' ); ?></h3>
				<p style="font-size:13px;">
					<?php esc_html_e( 'Each chunk is a single PHP request. If your server has a 30s time limit and geocoding is enabled, keep this at 20–50. For pre-geocoded CSVs (with lat/lng columns), you can safely increase it to 200+.', 'csv-importer-for-store-locator' ); ?>
				</p>
			</div>
		</div>
	</div>
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// FIELD DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_field_definitions() {
	return array(
		'name'     => array(
			'label'       => __( 'Column → Name', 'csv-importer-for-store-locator' ),
			'input_name'  => 'name',
			'default'     => 'Name',
			'required'    => true,
			'placeholder' => 'Name',
		),
		'address'  => array(
			'label'       => __( 'Column → Address', 'csv-importer-for-store-locator' ),
			'input_name'  => 'address',
			'default'     => 'Address',
			'required'    => true,
			'placeholder' => 'Address',
		),
		'address2' => array(
			'label'       => __( 'Column → Address 2', 'csv-importer-for-store-locator' ),
			'input_name'  => 'address2',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Address2',
		),
		'city'     => array(
			'label'       => __( 'Column → City', 'csv-importer-for-store-locator' ),
			'input_name'  => 'city',
			'default'     => 'City',
			'required'    => true,
			'placeholder' => 'City',
		),
		'state'    => array(
			'label'       => __( 'Column → State / Province', 'csv-importer-for-store-locator' ),
			'input_name'  => 'state',
			'default'     => 'State',
			'required'    => true,
			'placeholder' => 'State',
		),
		'zip'      => array(
			'label'       => __( 'Column → Zip Code', 'csv-importer-for-store-locator' ),
			'input_name'  => 'zip',
			'default'     => 'ZipCode',
			'required'    => true,
			'placeholder' => 'ZipCode',
		),
		'country'  => array(
			'label'       => __( 'Column → Country (or fixed value)', 'csv-importer-for-store-locator' ),
			'input_name'  => 'country',
			'default'     => 'United States',
			'required'    => false,
			'placeholder' => 'Country',
			'description' => __( 'If your CSV has no country column, type the value directly (e.g. <em>United States</em>).', 'csv-importer-for-store-locator' ),
		),
		'phone'    => array(
			'label'       => __( 'Column → Phone', 'csv-importer-for-store-locator' ),
			'input_name'  => 'phone',
			'default'     => 'Phone',
			'required'    => false,
			'placeholder' => 'Phone',
		),
		'fax'      => array(
			'label'       => __( 'Column → Fax', 'csv-importer-for-store-locator' ),
			'input_name'  => 'fax',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Fax',
		),
		'email'    => array(
			'label'       => __( 'Column → Email', 'csv-importer-for-store-locator' ),
			'input_name'  => 'email',
			'default'     => 'Email',
			'required'    => false,
			'placeholder' => 'Email',
		),
		'url'      => array(
			'label'       => __( 'Column → URL / Website', 'csv-importer-for-store-locator' ),
			'input_name'  => 'url',
			'default'     => 'Website',
			'required'    => false,
			'placeholder' => 'Website',
		),
		'lat'      => array(
			'label'       => __( 'Column → Latitude', 'csv-importer-for-store-locator' ),
			'input_name'  => 'lat',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Lat',
			'description' => __( 'Leave empty to let WPSL geocode the address automatically.', 'csv-importer-for-store-locator' ),
		),
		'lng'      => array(
			'label'       => __( 'Column → Longitude', 'csv-importer-for-store-locator' ),
			'input_name'  => 'lng',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Lng',
		),
		'category' => array(
			'label'       => __( 'Column → Category', 'csv-importer-for-store-locator' ),
			'input_name'  => 'category',
			'default'     => '',
			'required'    => false,
			'placeholder' => 'Category',
			'description' => __( 'Assigns a WPSL store category. Category is created if it does not exist. Separate multiple with |', 'csv-importer-for-store-locator' ),
		),
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORT COLUMN DEFINITIONS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_export_column_definitions() {
	return array(
		'name'     => array( 'label' => __( 'Name', 'csv-importer-for-store-locator' ),      'header' => 'Name',     'meta' => null ),
		'address'  => array( 'label' => __( 'Address', 'csv-importer-for-store-locator' ),   'header' => 'Address',  'meta' => 'wpsl_address' ),
		'address2' => array( 'label' => __( 'Address 2', 'csv-importer-for-store-locator' ), 'header' => 'Address2', 'meta' => 'wpsl_address2' ),
		'city'     => array( 'label' => __( 'City', 'csv-importer-for-store-locator' ),      'header' => 'City',     'meta' => 'wpsl_city' ),
		'state'    => array( 'label' => __( 'State', 'csv-importer-for-store-locator' ),     'header' => 'State',    'meta' => 'wpsl_state' ),
		'zip'      => array( 'label' => __( 'Zip Code', 'csv-importer-for-store-locator' ),  'header' => 'ZipCode',  'meta' => 'wpsl_zip' ),
		'country'  => array( 'label' => __( 'Country', 'csv-importer-for-store-locator' ),   'header' => 'Country',  'meta' => 'wpsl_country' ),
		'phone'    => array( 'label' => __( 'Phone', 'csv-importer-for-store-locator' ),     'header' => 'Phone',    'meta' => 'wpsl_phone' ),
		'fax'      => array( 'label' => __( 'Fax', 'csv-importer-for-store-locator' ),       'header' => 'Fax',      'meta' => 'wpsl_fax' ),
		'email'    => array( 'label' => __( 'Email', 'csv-importer-for-store-locator' ),     'header' => 'Email',    'meta' => 'wpsl_email' ),
		'url'      => array( 'label' => __( 'Website', 'csv-importer-for-store-locator' ),   'header' => 'Website',  'meta' => 'wpsl_url' ),
		'lat'      => array( 'label' => __( 'Latitude', 'csv-importer-for-store-locator' ),  'header' => 'Lat',      'meta' => 'wpsl_lat' ),
		'lng'      => array( 'label' => __( 'Longitude', 'csv-importer-for-store-locator' ), 'header' => 'Lng',      'meta' => 'wpsl_lng' ),
		'category' => array( 'label' => __( 'Category', 'csv-importer-for-store-locator' ),  'header' => 'Category', 'meta' => '_category' ),
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
			'<strong>' . esc_html__( 'Required columns not found in CSV:', 'csv-importer-for-store-locator' ) . '</strong> '
			. implode( ', ', $missing )
			. '<br>'
			. esc_html__( 'CSV headers found:', 'csv-importer-for-store-locator' ) . ' '
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
	if ( ! is_dir( $tmp ) ) {
		wp_mkdir_p( $tmp );
	}
	if ( is_writable( $tmp ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return $tmp;
	}

	// Fallback: WP uploads (protected by .htaccess)
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	$upload_dir = wp_upload_dir();
	$tmp        = trailingslashit( $upload_dir['basedir'] ) . 'wpsl-csv-tmp/';
	if ( ! is_dir( $tmp ) ) {
		wp_mkdir_p( $tmp );
		$wp_filesystem->put_contents( $tmp . '.htaccess', 'Deny from all' . PHP_EOL, FS_CHMOD_FILE );
		$wp_filesystem->put_contents( $tmp . 'index.php', '<?php // Silence is golden.', FS_CHMOD_FILE );
	}

	if ( ! is_writable( $tmp ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return new WP_Error( 'not_writable', __( 'Temporary directory is not writable. Check server permissions.', 'csv-importer-for-store-locator' ) );
	}

	return $tmp;
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: STORE LOOKUP (name + address to handle chains)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Find an existing store by name AND address.
 *
 * @return int Post ID, or 0 if not found.
 */
function wpsl_csv_find_store( $title, $address ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'wpsl_stores',
			'title'                  => $title,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		)
	);

	if ( empty( $query->posts ) ) {
		return 0;
	}

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

	$category_value = $fields['_category'] ?? '';
	unset( $fields['_category'] );

	// Validate required fields at row level — the column mapping guarantees the
	// columns exist, but individual rows can still have empty values.
	$required = array( 'post_title', 'wpsl_address', 'wpsl_city', 'wpsl_state', 'wpsl_zip' );
	foreach ( $required as $req_key ) {
		if ( empty( $fields[ $req_key ] ) ) {
			$result['skipped']++;
			return $result;
		}
	}

	$existing_id = wpsl_csv_find_store( $fields['post_title'], $fields['wpsl_address'] ?? '' );

	// SKIP
	if ( $duplicate_mode === 'skip' && $existing_id ) {
		$result['skipped']++;
		return $result;
	}

	// UPDATE
	if ( $duplicate_mode === 'update' && $existing_id ) {
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

		if ( is_wp_error( $update ) || 0 === $update ) {
			$result['error'] = 'Error updating "' . esc_html( $fields['post_title'] ) . '": '
				. ( is_wp_error( $update ) ? $update->get_error_message() : 'unknown error' );
			return $result;
		}

		foreach ( $fields as $meta_key => $meta_val ) {
			if ( $meta_key === 'post_title' ) {
				continue;
			}
			update_post_meta( $existing_id, $meta_key, sanitize_text_field( $meta_val ) );
		}

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

	if ( is_wp_error( $post_id ) || 0 === $post_id ) {
		$result['error'] = 'Error inserting "' . esc_html( $fields['post_title'] ) . '": '
			. ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'unknown error' );
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
		if ( is_wp_error( $term ) ) {
			// Term may have been inserted by a concurrent request; try fetching it.
			$existing = get_term_by( 'name', $name, 'wpsl_store_category' );
			if ( $existing ) {
				$term_ids[] = (int) $existing->term_id;
			}
			continue;
		}
		$term_id = is_array( $term ) ? ( $term['term_id'] ?? 0 ) : (int) $term;
		if ( $term_id ) {
			$term_ids[] = (int) $term_id;
		}
	}

	if ( ! empty( $term_ids ) ) {
		wp_set_object_terms( $post_id, $term_ids, 'wpsl_store_category', false );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: STORE STATS
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_store_stats() {
	// Total
	$counts = wp_count_posts( 'wpsl_stores' );
	$total  = 0;
	if ( is_object( $counts ) ) {
		foreach ( (array) $counts as $status => $count ) {
			if ( $status !== 'auto-draft' && $status !== 'trash' ) {
				$total += (int) $count;
			}
		}
	}

	// Ungeocoded (empty lat)
	$ung_q = new WP_Query( array(
		'post_type'      => 'wpsl_stores',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'no_found_rows'  => false,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => 'wpsl_lat', 'value' => '', 'compare' => '=' ),
			array( 'key' => 'wpsl_lat', 'compare' => 'NOT EXISTS' ),
		),
	) );
	$ungeocoded = (int) $ung_q->found_posts;

	// Top 5 categories
	$top_cats = get_terms( array(
		'taxonomy'   => 'wpsl_store_category',
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 5,
		'hide_empty' => true,
	) );
	if ( is_wp_error( $top_cats ) ) {
		$top_cats = array();
	}

	// Total categories
	$all_cats_count = wp_count_terms( array( 'taxonomy' => 'wpsl_store_category' ) );
	$categories     = is_wp_error( $all_cats_count ) ? 0 : (int) $all_cats_count;

	return compact( 'total', 'ungeocoded', 'top_cats', 'categories' );
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: UPLOAD ERROR MESSAGES
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_upload_error_message( $code ) {
	$messages = array(
		UPLOAD_ERR_INI_SIZE   => sprintf(
			/* translators: %s: php.ini upload size limit */
			__( 'File exceeds the server upload limit (%s). Ask your host to increase upload_max_filesize.', 'csv-importer-for-store-locator' ),
			ini_get( 'upload_max_filesize' )
		),
		UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the form size limit.', 'csv-importer-for-store-locator' ),
		UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded. Please try again.', 'csv-importer-for-store-locator' ),
		UPLOAD_ERR_NO_FILE    => __( 'No file was selected.', 'csv-importer-for-store-locator' ),
		UPLOAD_ERR_NO_TMP_DIR => __( 'Server temporary folder is missing. Contact your host.', 'csv-importer-for-store-locator' ),
		UPLOAD_ERR_CANT_WRITE => __( 'Server failed to write the file to disk. Check server permissions.', 'csv-importer-for-store-locator' ),
		UPLOAD_ERR_EXTENSION  => __( 'A PHP extension blocked the upload.', 'csv-importer-for-store-locator' ),
	);
	return $messages[ $code ] ?? sprintf(
		/* translators: %d: PHP upload error code */
		__( 'Upload failed (error code %d).', 'csv-importer-for-store-locator' ),
		$code
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS: GOOGLE SHEETS URL CONVERSION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * If the URL is a Google Sheets edit/view/pub URL, converts it to a direct
 * CSV export URL. Returns the URL unchanged for anything else.
 */
function wpsl_csv_maybe_convert_google_sheets_url( $url ) {
	if ( strpos( $url, 'docs.google.com/spreadsheets' ) === false ) {
		return $url;
	}
	// Already a CSV export URL — leave it alone.
	if ( strpos( $url, '/export' ) !== false && strpos( $url, 'format=csv' ) !== false ) {
		return $url;
	}

	// Extract spreadsheet ID.
	if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $id_match ) ) {
		return $url;
	}
	$spreadsheet_id = $id_match[1];

	// Extract sheet GID from query string (?gid=) or fragment (#gid=).
	$gid    = '0';
	$parsed = wp_parse_url( $url );
	if ( ! empty( $parsed['query'] ) ) {
		parse_str( $parsed['query'], $query_params );
		if ( isset( $query_params['gid'] ) ) {
			$gid = $query_params['gid'];
		}
	}
	if ( ! empty( $parsed['fragment'] ) && preg_match( '/gid=(\d+)/', $parsed['fragment'], $frag_match ) ) {
		$gid = $frag_match[1];
	}

	return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $spreadsheet_id ) . '/export?format=csv&gid=' . rawurlencode( $gid );
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: INIT IMPORT  (upload file, validate, return batch_id + total)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_import_init', 'wpsl_csv_ajax_import_init' );

function wpsl_csv_ajax_import_init() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	$source = sanitize_key( $_POST['import_source'] ?? 'file' );

	// Temp dir (shared setup for both sources)
	$tmp_dir = wpsl_csv_get_tmp_dir();
	if ( is_wp_error( $tmp_dir ) ) {
		wp_send_json_error( array( 'message' => $tmp_dir->get_error_message() ) );
	}

	// Remove stale temp files (> 2 hours old)
	foreach ( glob( $tmp_dir . '*.csv' ) ?: array() as $stale ) {
		if ( filemtime( $stale ) < ( time() - 2 * HOUR_IN_SECONDS ) ) {
			wp_delete_file( $stale );
		}
	}

	$batch_id = sanitize_key( wp_generate_uuid4() );
	$dest     = $tmp_dir . $batch_id . '.csv';

	if ( $source === 'url' ) {
		// ── URL import ───────────────────────────────────────────────────
		$url = esc_url_raw( trim( wp_unslash( $_POST['wpsl_csv_url'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw() is the correct URL sanitization function

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a URL.', 'csv-importer-for-store-locator' ) ) );
		}
		if ( ! wp_http_validate_url( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid URL. Please enter a valid http:// or https:// URL.', 'csv-importer-for-store-locator' ) ) );
		}

		// Auto-convert Google Sheets edit/view URLs to direct CSV export URLs.
		$url = wpsl_csv_maybe_convert_google_sheets_url( $url );

		$response = wp_remote_get( $url, array( 'timeout' => 60 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => sprintf(
				/* translators: %s: error message */
				__( 'Could not fetch URL: %s', 'csv-importer-for-store-locator' ),
				$response->get_error_message()
			) ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			wp_send_json_error( array( 'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'URL returned HTTP %d. Make sure the URL is public and points to a CSV file.', 'csv-importer-for-store-locator' ),
				$code
			) ) );
		}

		// Reject responses that are clearly not CSV (e.g. HTML error pages).
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/octet-stream' );
		$type_ok = empty( $content_type ); // If no header, give benefit of the doubt.
		foreach ( $allowed_types as $allowed ) {
			if ( strpos( $content_type, $allowed ) !== false ) {
				$type_ok = true;
				break;
			}
		}
		if ( ! $type_ok ) {
			wp_send_json_error( array( 'message' => sprintf(
				/* translators: %s: content-type returned by the server */
				__( 'The URL did not return a CSV file (server responded with "%s"). Make sure the file is publicly accessible and the URL points directly to a CSV.', 'csv-importer-for-store-locator' ),
				esc_html( strtok( $content_type, ';' ) )
			) ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			wp_send_json_error( array( 'message' => __( 'No data received from URL.', 'csv-importer-for-store-locator' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! $wp_filesystem->put_contents( $dest, $body, FS_CHMOD_FILE ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not write temporary file.', 'csv-importer-for-store-locator' ) ) );
		}

	} else {
		// ── File upload ──────────────────────────────────────────────────
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES values are validated/used safely below
		$upload_error = isset( $_FILES['wpsl_csv_file']['error'] ) ? (int) $_FILES['wpsl_csv_file']['error'] : UPLOAD_ERR_NO_FILE;
		$upload_tmp   = isset( $_FILES['wpsl_csv_file']['tmp_name'] ) ? $_FILES['wpsl_csv_file']['tmp_name'] : '';
		$upload_name  = isset( $_FILES['wpsl_csv_file']['name'] ) ? sanitize_file_name( $_FILES['wpsl_csv_file']['name'] ) : '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $upload_tmp ) || $upload_error !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => wpsl_csv_upload_error_message( $upload_error ) ) );
		}

		$ext = strtolower( pathinfo( $upload_name, PATHINFO_EXTENSION ) );
		if ( $ext !== 'csv' ) {
			wp_send_json_error( array( 'message' => __( 'File must be a .csv.', 'csv-importer-for-store-locator' ) ) );
		}

		if ( ! move_uploaded_file( $upload_tmp, $dest ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- no WP equivalent for verifying HTTP-uploaded files
			wp_send_json_error( array( 'message' => __( 'Could not save uploaded file.', 'csv-importer-for-store-locator' ) ) );
		}
	}

	// Detect delimiter, BOM, headers, and row count via WP_Filesystem (no direct file handles needed).
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
	}

	$raw = $wp_filesystem->get_contents( $dest );
	if ( $raw === false || $raw === '' ) {
		wp_delete_file( $dest );
		wp_send_json_error( array( 'message' => __( 'Could not read CSV headers.', 'csv-importer-for-store-locator' ) ) );
	}

	// Strip UTF-8 BOM if present and record its byte length.
	$bom_length = 0;
	if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
		$bom_length = 3;
		$raw        = substr( $raw, 3 );
	}

	// Detect delimiter from first line.
	$eol_pos    = strcspn( $raw, "\r\n" );
	$first_line = substr( $raw, 0, $eol_pos );
	$delimiter  = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

	// Parse headers using str_getcsv (no file handle required).
	$headers = str_getcsv( $first_line, $delimiter );
	if ( ! $headers || ( count( $headers ) === 1 && '' === trim( $headers[0] ) ) ) {
		wp_delete_file( $dest );
		wp_send_json_error( array( 'message' => __( 'Could not read CSV headers.', 'csv-importer-for-store-locator' ) ) );
	}
	$headers = array_map( 'trim', $headers );

	// Calculate the byte offset where data rows start (used by the chunk handler for fseek).
	$eol_len         = ( substr( $raw, $eol_pos, 2 ) === "\r\n" ) ? 2 : 1;
	$data_start_byte = $bom_length + $eol_pos + $eol_len;

	// Count data rows.
	$data_raw = substr( $raw, $eol_pos + $eol_len );
	$total    = count( preg_split( '/\r\n|\r|\n/', $data_raw, -1, PREG_SPLIT_NO_EMPTY ) );

	// Build and validate map
	$map        = wpsl_csv_build_map( $_POST );
	$validation = wpsl_csv_validate_columns( $headers, $map );
	if ( is_wp_error( $validation ) ) {
		wp_delete_file( $dest );
		wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
	}
	$map = wpsl_csv_clean_optional_map( $headers, $map );

	$dup_mode_raw   = sanitize_key( wp_unslash( $_POST['duplicate_mode'] ?? 'skip' ) );
	$duplicate_mode = in_array( $dup_mode_raw, array( 'skip', 'update', 'insert' ), true ) ? $dup_mode_raw : 'skip';

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
			'total'          => $total,
		),
		HOUR_IN_SECONDS
	);

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
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	$batch_id   = sanitize_key( $_POST['batch_id'] ?? '' );
	$offset     = max( 0, intval( $_POST['offset'] ?? 0 ) );
	$chunk_size = max( 1, min( 500, intval( $_POST['chunk_size'] ?? WPSL_CSV_CHUNK_SIZE ) ) );

	$batch = get_transient( 'wpsl_csv_batch_' . $batch_id );
	if ( ! $batch ) {
		wp_send_json_error( array( 'message' => __( 'Batch expired or not found. Please restart the import.', 'csv-importer-for-store-locator' ) ) );
	}

	if ( (int) $batch['user_id'] !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'csv-importer-for-store-locator' ) ) );
	}

	if ( ! file_exists( $batch['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Temporary file missing. Please restart the import.', 'csv-importer-for-store-locator' ) ) );
	}

	$fh        = fopen( $batch['file'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fgetcsv/fseek/ftell have no WP_Filesystem equivalent
	$delimiter = $batch['delimiter'];
	$headers   = $batch['headers'];
	$map       = $batch['map'];

	// Fast path: jump directly to the byte position where this chunk starts.
	// The JS passes back the next_byte_offset returned by the previous chunk,
	// avoiding O(N²) row-scanning on large files.
	$byte_offset = max( 0, intval( $_POST['byte_offset'] ?? 0 ) );
	if ( $byte_offset > 0 ) {
		fseek( $fh, $byte_offset );
	} else {
		fseek( $fh, $batch['data_offset'] );
		for ( $i = 0; $i < $offset; $i++ ) {
			if ( fgetcsv( $fh, 0, $delimiter ) === false ) {
				break;
			}
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

		$data = array();
		foreach ( $headers as $i => $col ) {
			$val         = isset( $row[ $i ] ) ? trim( $row[ $i ] ) : '';
			$data[ $col ] = ( $val === 'NULL' ) ? '' : $val;
		}

		$fields = array();
		foreach ( $map as $wpsl_key => $csv_col ) {
			if ( $csv_col === '' ) {
				$fields[ $wpsl_key ] = '';
				continue;
			}
			if ( array_key_exists( $csv_col, $data ) ) {
				$fields[ $wpsl_key ] = $data[ $csv_col ];
			} elseif ( $wpsl_key === 'wpsl_country' ) {
				$fields[ $wpsl_key ] = $csv_col;
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

	$next_byte_offset = ftell( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	$done        = ( $processed < $chunk_size );
	$next_offset = $offset + $processed;

	if ( $done ) {
		wp_delete_file( $batch['file'] );
		delete_transient( 'wpsl_csv_batch_' . $batch_id );
	}

	wp_send_json_success(
		array(
			'inserted'          => $inserted,
			'updated'           => $updated,
			'skipped'           => $skipped,
			'errors'            => $errors,
			'next_offset'       => $next_offset,
			'next_byte_offset'  => $done ? 0 : $next_byte_offset,
			'done'              => $done,
		)
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: DRY RUN PREVIEW  (reads first 10 rows, no DB writes)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_dry_run_preview', 'wpsl_csv_ajax_dry_run_preview' );

function wpsl_csv_ajax_dry_run_preview() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	$batch_id = sanitize_key( $_POST['batch_id'] ?? '' );
	$batch    = get_transient( 'wpsl_csv_batch_' . $batch_id );

	if ( ! $batch ) {
		wp_send_json_error( array( 'message' => __( 'Batch expired or not found. Please re-upload the file.', 'csv-importer-for-store-locator' ) ) );
	}
	if ( (int) $batch['user_id'] !== get_current_user_id() ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'csv-importer-for-store-locator' ) ) );
	}
	if ( ! file_exists( $batch['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Temporary file missing. Please re-upload the file.', 'csv-importer-for-store-locator' ) ) );
	}

	$fh        = fopen( $batch['file'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- fgetcsv/fseek have no WP_Filesystem equivalent
	$delimiter = $batch['delimiter'];
	$headers   = $batch['headers'];
	$map       = $batch['map'];
	$dup_mode  = $batch['duplicate_mode'];
	$max_rows  = 10;

	fseek( $fh, $batch['data_offset'] );

	$preview = array();
	$counts  = array( 'insert' => 0, 'update' => 0, 'skip' => 0 );
	$i       = 0;

	while ( $i < $max_rows ) {
		$row = fgetcsv( $fh, 0, $delimiter );
		if ( $row === false ) {
			break;
		}
		$i++;

		// Build data array (same logic as chunk handler)
		$data = array();
		foreach ( $headers as $j => $col ) {
			$val         = isset( $row[ $j ] ) ? trim( $row[ $j ] ) : '';
			$data[ $col ] = ( $val === 'NULL' ) ? '' : $val;
		}

		// Resolve fields from map
		$fields = array();
		foreach ( $map as $wpsl_key => $csv_col ) {
			if ( $csv_col === '' ) {
				$fields[ $wpsl_key ] = '';
				continue;
			}
			if ( array_key_exists( $csv_col, $data ) ) {
				$fields[ $wpsl_key ] = $data[ $csv_col ];
			} elseif ( $wpsl_key === 'wpsl_country' ) {
				$fields[ $wpsl_key ] = $csv_col;
			} else {
				$fields[ $wpsl_key ] = '';
			}
		}

		if ( $batch['normalize_case'] ) {
			$fields = wpsl_csv_normalize_case( $fields );
		}

		$name    = $fields['post_title']   ?? '';
		$address = $fields['wpsl_address'] ?? '';

		if ( empty( $name ) ) {
			$counts['skip']++;
			$preview[] = array( 'name' => '', 'address' => '', 'action' => 'skip', 'note' => 'empty_name' );
			continue;
		}

		// Determine action WITHOUT writing anything
		$existing_id = wpsl_csv_find_store( $name, $address );

		if ( $dup_mode === 'skip' && $existing_id ) {
			$action = 'skip';
			$counts['skip']++;
		} elseif ( $dup_mode === 'update' && $existing_id ) {
			$action = 'update';
			$counts['update']++;
		} else {
			$action = 'insert';
			$counts['insert']++;
		}

		$preview[] = array(
			'name'    => $name,
			'address' => $address,
			'action'  => $action,
		);
	}

	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	wp_send_json_success( array(
		'preview'  => $preview,
		'counts'   => $counts,
		'total'    => (int) ( $batch['total'] ?? 0 ),
		'has_more' => ( (int) ( $batch['total'] ?? 0 ) ) > $max_rows,
	) );
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: GET HEADERS (column preview)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_get_headers', 'wpsl_csv_ajax_get_headers' );

function wpsl_csv_ajax_get_headers() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES values are validated/used safely below
	$preview_error = isset( $_FILES['wpsl_csv_preview']['error'] ) ? (int) $_FILES['wpsl_csv_preview']['error'] : UPLOAD_ERR_NO_FILE;
	$preview_tmp   = isset( $_FILES['wpsl_csv_preview']['tmp_name'] ) ? $_FILES['wpsl_csv_preview']['tmp_name'] : '';
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( empty( $preview_tmp ) || $preview_error !== UPLOAD_ERR_OK ) {
		wp_send_json_error( array( 'message' => wpsl_csv_upload_error_message( $preview_error ) ) );
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fread
	$fh = fopen( $preview_tmp, 'r' );
	if ( ! $fh ) {
		wp_send_json_error( array( 'message' => __( 'Could not open file.', 'csv-importer-for-store-locator' ) ) );
	}

	$bom = fread( $fh, 3 );
	if ( $bom !== "\xEF\xBB\xBF" ) {
		rewind( $fh );
	}

	$first = fgets( $fh );
	rewind( $fh );
	if ( $bom === "\xEF\xBB\xBF" ) {
		fseek( $fh, 3 );
	}
	$delimiter = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

	$headers = fgetcsv( $fh, 0, $delimiter );
	fclose( $fh );
	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fread

	if ( ! $headers ) {
		wp_send_json_error( array( 'message' => __( 'Could not read headers.', 'csv-importer-for-store-locator' ) ) );
	}

	wp_send_json_success( array( 'headers' => array_map( 'trim', $headers ) ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: BULK DELETE STORES
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_bulk_delete', 'wpsl_csv_ajax_bulk_delete' );

function wpsl_csv_ajax_bulk_delete() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	$sub_action = sanitize_key( $_POST['sub_action'] ?? 'all' );
	$cat_id     = intval( $_POST['category_id'] ?? 0 );
	$phase      = sanitize_key( $_POST['phase'] ?? 'delete' );
	$chunk_size = 100;

	$args = array(
		'post_type'   => 'wpsl_stores',
		'post_status' => 'any',
		'fields'      => 'ids',
	);

	if ( $sub_action === 'category' && $cat_id > 0 ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'wpsl_store_category',
				'field'    => 'term_id',
				'terms'    => $cat_id,
			),
		);
	}

	if ( $phase === 'count' ) {
		// Return total without deleting
		$args['posts_per_page'] = 1;
		$args['no_found_rows']  = false;
		$q = new WP_Query( $args );
		wp_send_json_success( array( 'total' => (int) $q->found_posts ) );
		return;
	}

	// Delete phase: always query offset=0 and delete the first chunk
	$args['posts_per_page'] = $chunk_size;
	$args['no_found_rows']  = true;
	$stores = get_posts( $args );

	$deleted = 0;
	foreach ( $stores as $id ) {
		if ( wp_delete_post( (int) $id, true ) ) {
			$deleted++;
		}
	}

	$done = count( $stores ) < $chunk_size;

	wp_send_json_success(
		array(
			'deleted' => $deleted,
			'done'    => $done,
		)
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: CANCEL BATCH  (dry-run cancel — cleans up temp file + transient)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_cancel_batch', 'wpsl_csv_ajax_cancel_batch' );

function wpsl_csv_ajax_cancel_batch() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}

	$batch_id = sanitize_key( $_POST['batch_id'] ?? '' );
	if ( ! $batch_id ) {
		wp_send_json_success();
	}

	$batch = get_transient( 'wpsl_csv_batch_' . $batch_id );
	if ( $batch && ! empty( $batch['file'] ) && (int) $batch['user_id'] === get_current_user_id() ) {
		wp_delete_file( $batch['file'] );
	}
	delete_transient( 'wpsl_csv_batch_' . $batch_id );

	wp_send_json_success();
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: BULK RE-GEOCODE
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_wpsl_csv_bulk_regeocod', 'wpsl_csv_ajax_bulk_regeocod' );

function wpsl_csv_ajax_bulk_regeocod() {
	check_ajax_referer( 'wpsl_csv_ajax', 'security' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) ) );
	}

	$offset     = max( 0, intval( $_POST['offset'] ?? 0 ) );
	$chunk_size = 5; // Small to avoid timeouts (each triggers WPSL geocoding)
	$batch_key  = 'wpsl_regeocod_' . get_current_user_id();

	if ( $offset === 0 ) {
		// Build the list of IDs on first call
		$ids = get_posts( array(
			'post_type'      => 'wpsl_stores',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => 'wpsl_lat', 'value' => '', 'compare' => '=' ),
				array( 'key' => 'wpsl_lat', 'compare' => 'NOT EXISTS' ),
			),
		) );
		set_transient( $batch_key, $ids, HOUR_IN_SECONDS );
	} else {
		$ids = get_transient( $batch_key );
		if ( $ids === false ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please try again.', 'csv-importer-for-store-locator' ) ) );
		}
	}

	$total = count( $ids );
	if ( $total === 0 ) {
		wp_send_json_success( array( 'processed' => 0, 'total' => 0, 'next_offset' => 0, 'done' => true ) );
		return;
	}

	$chunk     = array_slice( $ids, $offset, $chunk_size );
	$processed = 0;

	foreach ( $chunk as $id ) {
		delete_post_meta( (int) $id, 'wpsl_lat' );
		delete_post_meta( (int) $id, 'wpsl_lng' );
		$updated = wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'publish' ) );
		if ( $updated && ! is_wp_error( $updated ) ) {
			$processed++;
		}
	}

	$next_offset = $offset + $processed;
	$done        = $next_offset >= $total;

	if ( $done ) {
		delete_transient( $batch_key );
	}

	wp_send_json_success( array(
		'processed'   => $processed,
		'total'       => $total,
		'next_offset' => $next_offset,
		'done'        => $done,
	) );
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
	$query = new WP_Query(
		array(
			'post_type'              => 'wpsl_stores',
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'fields'                 => 'ids',
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => 'wpsl_zip',
					'value' => 'ZipCode',
				),
			),
		)
	);
	return (int) $query->found_posts;
}

function wpsl_csv_cleanup_corrupt_zips() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array( 'type' => 'error', 'message' => __( 'Insufficient permissions.', 'csv-importer-for-store-locator' ) );
	}

	$ids = wpsl_csv_get_corrupt_zip_ids();
	if ( empty( $ids ) ) {
		return array( 'type' => 'info', 'message' => __( 'No corrupt records found.', 'csv-importer-for-store-locator' ) );
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
			__( '%d store(s) deleted. You can now re-import them with the correct CSV.', 'csv-importer-for-store-locator' ),
			$deleted
		),
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// INLINE JAVASCRIPT
// ─────────────────────────────────────────────────────────────────────────────

function wpsl_csv_get_inline_js() {
	return <<<'ENDJS'
(function($){
"use strict";

var cfg = wpslCsvAjax;

/* ── Module-level import state (shared between submit + dry-run confirm) ── */
var _importBtn, _importWrap, _importBar, _importStatus, _importResult;
var _pendingBatchId = null, _pendingTotal = 0;

function importSetBar(pct, label) {
    _importBar.css("width", pct + "%");
    _importStatus.text(label);
}

function importRunChunk(batchId, offset, byteOffset, total, cum) {
    var shown = Math.min(offset + parseInt(cfg.chunk_size), total);
    importSetBar(Math.min(Math.round(offset / total * 100), 99), cfg.i18n.processing + " " + shown + " / " + total);

    $.post(cfg.ajaxurl, {
        action:       "wpsl_csv_import_chunk",
        security:     cfg.nonce,
        batch_id:     batchId,
        offset:       offset,
        byte_offset:  byteOffset || 0,
        chunk_size:   cfg.chunk_size
    }, function(res) {
        if (!res.success) { importShowError(res.data.message); return; }
        var d = res.data;
        cum.inserted += d.inserted;
        cum.updated  += d.updated;
        cum.skipped  += d.skipped;
        cum.errors    = cum.errors.concat(d.errors || []);
        if (d.done) {
            importSetBar(100, cfg.i18n.done);
            setTimeout(function() { importFinalize(cum); }, 400);
        } else {
            importRunChunk(batchId, d.next_offset, d.next_byte_offset || 0, total, cum);
        }
    }, "json").fail(function() { importShowError(cfg.i18n.error); });
}

function importFinalize(cum) {
    _importWrap.hide();
    _importBtn.prop("disabled", false).text(cfg.i18n.import_now);

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

    _importResult.html('<div class="notice notice-' + type + ' is-dismissible"><p>' + msg + '</p></div>').show();

    if (cum.inserted || cum.updated) {
        setTimeout(function() { location.reload(); }, 3500);
    }
}

function importShowError(msg) {
    _importWrap.hide();
    _importBtn.prop("disabled", false).text(cfg.i18n.import_now);
    _importResult.html('<div class="notice notice-error is-dismissible"><p>' + msg + '</p></div>').show();
}

/* ── Import form submit ──────────────────────────────────────────────────── */
$("#wpsl-import-form").on("submit", function(e) {
    e.preventDefault();

    _importBtn    = $(this).find(".wpsl-import-submit");
    _importWrap   = $("#wpsl-progress-wrap");
    _importBar    = $("#wpsl-progress-bar");
    _importStatus = $("#wpsl-progress-status");
    _importResult = $("#wpsl-import-result");

    var source    = $("input[name='import_source']:checked").val() || "file";
    var isDryRun  = $("#wpsl_dry_run").is(":checked");

    // Client-side source validation
    if (source === "file" && !document.getElementById("wpsl_csv_file").files.length) {
        importShowError(cfg.i18n.select_file);
        return;
    }
    if (source === "url" && !$("#wpsl_csv_url").val().trim()) {
        importShowError(cfg.i18n.enter_url);
        return;
    }

    _importResult.hide().html("");
    _importWrap.show();
    importSetBar(0, cfg.i18n.uploading);
    _importBtn.prop("disabled", true).text(cfg.i18n.uploading);

    var fd = new FormData(this);
    fd.append("action",   "wpsl_csv_import_init");
    fd.append("security", cfg.nonce);

    $.ajax({ url: cfg.ajaxurl, type: "POST", data: fd, processData: false, contentType: false })
     .done(function(res) {
        if (!res.success) { importShowError(res.data.message); return; }
        var d = res.data;
        _pendingBatchId = d.batch_id;
        _pendingTotal   = d.total;

        if (isDryRun) {
            importSetBar(50, cfg.i18n.loading_preview);
            $.post(cfg.ajaxurl, {
                action:   "wpsl_csv_dry_run_preview",
                security: cfg.nonce,
                batch_id: d.batch_id
            }, function(res2) {
                _importWrap.hide();
                _importBtn.prop("disabled", false).text(cfg.i18n.import_now);
                if (!res2.success) { importShowError(res2.data.message); return; }
                showDryRunModal(res2.data);
            }, "json").fail(function() { importShowError(cfg.i18n.error); });
        } else {
            if (d.total === 0) { importFinalize({ inserted:0, updated:0, skipped:0, errors:[] }); return; }
            importRunChunk(d.batch_id, 0, 0, d.total, { inserted:0, updated:0, skipped:0, errors:[] });
        }
     })
     .fail(function() { importShowError(cfg.i18n.error); });
});

/* ── Source toggle ───────────────────────────────────────────────────────── */
$("input[name='import_source']").on("change", function() {
    if ($(this).val() === "url") {
        $("#wpsl-file-input-row").hide();
        $("#wpsl-url-input-row").show();
    } else {
        $("#wpsl-file-input-row").show();
        $("#wpsl-url-input-row").hide();
    }
});

/* ── Dry run modal ───────────────────────────────────────────────────────── */
function showDryRunModal(data) {
    var preview  = data.preview  || [];
    var counts   = data.counts   || {};
    var total    = data.total    || 0;
    var hasMore  = data.has_more || false;

    var actionLabel = { insert: cfg.i18n.action_insert, update: cfg.i18n.action_update, skip: cfg.i18n.action_skip };
    var actionColor = { insert: "#0a7227", update: "#2271b1", skip: "#646970" };

    // Summary line
    var summary = '<p style="margin-top:0;">';
    if (counts.insert) summary += '<strong style="color:#0a7227">' + counts.insert + ' ' + cfg.i18n.action_insert + '</strong>  ';
    if (counts.update) summary += '<strong style="color:#2271b1">' + counts.update + ' ' + cfg.i18n.action_update + '</strong>  ';
    if (counts.skip)   summary += '<strong style="color:#646970">' + counts.skip   + ' ' + cfg.i18n.action_skip   + '</strong>';
    summary += '</p>';
    if (hasMore) {
        summary += '<p style="color:#50575e;font-size:12px;margin-top:-4px;">' + cfg.i18n.showing_first.replace('%d', total) + '</p>';
    }

    // Table
    var table = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    table += '<thead><tr style="border-bottom:2px solid #dcdcde;">';
    table += '<th style="text-align:left;padding:6px 8px;">'  + cfg.i18n.col_number  + '</th>';
    table += '<th style="text-align:left;padding:6px 8px;">'  + cfg.i18n.col_name    + '</th>';
    table += '<th style="text-align:left;padding:6px 8px;">'  + cfg.i18n.col_address + '</th>';
    table += '<th style="text-align:left;padding:6px 8px;">'  + cfg.i18n.col_action  + '</th>';
    table += '</tr></thead><tbody>';

    $.each(preview, function(i, row) {
        var name    = row.name    ? $("<div>").text(row.name).html()    : '<em style="color:#999">—</em>';
        var address = row.address ? $("<div>").text(row.address).html() : '<em style="color:#999">—</em>';
        var action  = actionLabel[row.action] || row.action;
        var color   = actionColor[row.action] || "#000";
        table += '<tr style="border-bottom:1px solid #f0f0f1;">';
        table += '<td style="padding:6px 8px;color:#999">' + (i + 1) + '</td>';
        table += '<td style="padding:6px 8px;">' + name + '</td>';
        table += '<td style="padding:6px 8px;">' + address + '</td>';
        table += '<td style="padding:6px 8px;font-weight:600;color:' + color + '">' + action + '</td>';
        table += '</tr>';
    });

    table += '</tbody></table>';

    $("#wpsl-dryrun-body").html(summary + table);
    $("#wpsl-dryrun-overlay").show();
}

$("#wpsl-dryrun-confirm").on("click", function() {
    $("#wpsl-dryrun-overlay").hide();
    if (!_pendingBatchId || _pendingTotal === 0) {
        importFinalize({ inserted:0, updated:0, skipped:0, errors:[] });
        return;
    }
    _importWrap.show();
    importSetBar(0, cfg.i18n.processing + " 0 / " + _pendingTotal);
    _importBtn.prop("disabled", true).text(cfg.i18n.processing);
    importRunChunk(_pendingBatchId, 0, 0, _pendingTotal, { inserted:0, updated:0, skipped:0, errors:[] });
});

$("#wpsl-dryrun-cancel").on("click", function() {
    $("#wpsl-dryrun-overlay").hide();
    if (_pendingBatchId) {
        $.post(cfg.ajaxurl, { action: "wpsl_csv_cancel_batch", security: cfg.nonce, batch_id: _pendingBatchId });
    }
    _pendingBatchId = null;
    _pendingTotal   = 0;
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
        if (!res.success) { $out.html('<p style="color:#d63638;">' + res.data.message + "</p>"); return; }
        var html = '<p style="margin-top:12px;margin-bottom:6px;font-size:13px;"><strong>' + cfg.i18n.detected_columns + '</strong></p><ul style="margin:0;padding-left:1.4em;font-size:13px;line-height:1.8;">';
        res.data.headers.forEach(function(col) {
            html += "<li><code>" + $("<div>").text(col).html() + "</code></li>";
        });
        $out.html(html + "</ul>");
     });
});

/* ── Export: select/deselect all columns ────────────────────────────────── */
$("#wpsl-export-select-all").on("click", function(e) {
    e.preventDefault();
    $(".wpsl-export-col-check").prop("checked", true);
});
$("#wpsl-export-deselect-all").on("click", function(e) {
    e.preventDefault();
    $(".wpsl-export-col-check").prop("checked", false);
});

/* ── Manage: Delete all stores ──────────────────────────────────────────── */
$("#wpsl-delete-all-btn").on("click", function() {
    if (!confirm(cfg.i18n.confirm_delete_all)) return;
    var $btn  = $(this);
    var $wrap = $("#wpsl-delete-all-wrap");
    var $bar  = $("#wpsl-delete-all-bar");
    var $stat = $("#wpsl-delete-all-status");
    var total = 0, deletedTotal = 0;

    $btn.prop("disabled", true);
    $wrap.show();

    function countThenDelete() {
        $.post(cfg.ajaxurl, {
            action:     "wpsl_csv_bulk_delete",
            security:   cfg.nonce,
            sub_action: "all",
            phase:      "count"
        }, function(res) {
            if (!res.success) return;
            total = res.data.total;
            if (total === 0) {
                $stat.text(cfg.i18n.no_changes);
                $bar.css("width", "100%");
                return;
            }
            deleteChunk();
        }, "json");
    }

    function deleteChunk() {
        $stat.text(cfg.i18n.deleting + " " + deletedTotal + " / " + total);
        if (total > 0) $bar.css("width", Math.min(Math.round(deletedTotal / total * 100), 99) + "%");

        $.post(cfg.ajaxurl, {
            action:     "wpsl_csv_bulk_delete",
            security:   cfg.nonce,
            sub_action: "all",
            phase:      "delete"
        }, function(res) {
            if (!res.success) { $stat.text(cfg.i18n.error); return; }
            deletedTotal += res.data.deleted;
            if (res.data.done) {
                $bar.css("width", "100%");
                $stat.text(deletedTotal + " " + cfg.i18n.deleted);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                deleteChunk();
            }
        }, "json");
    }

    countThenDelete();
});

/* ── Manage: Delete by category ─────────────────────────────────────────── */
$("#wpsl-delete-cat-btn").on("click", function() {
    var catId = $("#wpsl-delete-cat-select").val();
    if (!catId) { alert(cfg.i18n.select_category); return; }
    if (!confirm(cfg.i18n.confirm_delete_cat)) return;

    var $btn  = $(this);
    var $wrap = $("#wpsl-delete-cat-wrap");
    var $bar  = $("#wpsl-delete-cat-bar");
    var $stat = $("#wpsl-delete-cat-status");
    var total = 0, deletedTotal = 0;

    $btn.prop("disabled", true);
    $("#wpsl-delete-cat-select").prop("disabled", true);
    $wrap.show();

    function countThenDelete() {
        $.post(cfg.ajaxurl, {
            action:      "wpsl_csv_bulk_delete",
            security:    cfg.nonce,
            sub_action:  "category",
            category_id: catId,
            phase:       "count"
        }, function(res) {
            if (!res.success) return;
            total = res.data.total;
            if (total === 0) {
                $stat.text(cfg.i18n.no_changes);
                $bar.css("width", "100%");
                return;
            }
            deleteChunk();
        }, "json");
    }

    function deleteChunk() {
        $stat.text(cfg.i18n.deleting + " " + deletedTotal + " / " + total);
        if (total > 0) $bar.css("width", Math.min(Math.round(deletedTotal / total * 100), 99) + "%");

        $.post(cfg.ajaxurl, {
            action:      "wpsl_csv_bulk_delete",
            security:    cfg.nonce,
            sub_action:  "category",
            category_id: catId,
            phase:       "delete"
        }, function(res) {
            if (!res.success) { $stat.text(cfg.i18n.error); return; }
            deletedTotal += res.data.deleted;
            if (res.data.done) {
                $bar.css("width", "100%");
                $stat.text(deletedTotal + " " + cfg.i18n.deleted);
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                deleteChunk();
            }
        }, "json");
    }

    countThenDelete();
});

/* ── Manage: Re-geocode ungeocoded stores ───────────────────────────────── */
$("#wpsl-regeocod-btn").on("click", function() {
    var $btn  = $(this);
    var count = parseInt($btn.data("count")) || 0;
    if (count === 0) { alert(cfg.i18n.no_ungeocoded); return; }

    var $wrap = $("#wpsl-regeocod-wrap");
    var $bar  = $("#wpsl-regeocod-bar");
    var $stat = $("#wpsl-regeocod-status");

    $btn.prop("disabled", true);
    $wrap.show();

    function regeocChunk(offset, total) {
        var pct = total > 0 ? Math.min(Math.round(offset / total * 100), 99) : 0;
        $bar.css("width", pct + "%");
        $stat.text(cfg.i18n.regeocoding + " " + offset + " / " + (total || "…"));

        $.post(cfg.ajaxurl, {
            action:   "wpsl_csv_bulk_regeocod",
            security: cfg.nonce,
            offset:   offset
        }, function(res) {
            if (!res.success) { $stat.text(cfg.i18n.error); return; }
            var d = res.data;
            if (d.done) {
                $bar.css("width", "100%");
                $stat.text(cfg.i18n.regeocod_done);
            } else {
                regeocChunk(d.next_offset, d.total);
            }
        }, "json");
    }

    regeocChunk(0, count);
});

})(jQuery);
ENDJS;
}
