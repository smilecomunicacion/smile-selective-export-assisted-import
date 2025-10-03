<?php
/**
 * Plugin Name: SMiLE Assisted Import
 * Description: Import pages and their synced patterns from a SMiLE JSON package, download attachments, and rewrite URLs.
 * Version: 1.0.2
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:      smilecomunicacion
 * Author URI:  https://smilecomunicacion.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smile-assisted-import
 * Domain Path: /languages
 *
 * @package smile-assisted-import
 */

/*
 * -------------------------------------------------------------------
 *  Security check
 * -------------------------------------------------------------------
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------
 *  Admin menu
 * -------------------------------------------------------------------
*/
add_action( 'admin_menu', 'smssmpt_import_admin_menu' );

/**
 * Register admin page under Tools.
 *
 * @return void
 */
function smssmpt_import_admin_menu() {
	add_management_page(
		esc_html__( 'SMiLE Assisted Import', 'smile-assisted-import' ),
		esc_html__( 'SMiLE Assisted Import', 'smile-assisted-import' ),
		'manage_options',
		'smile-assisted-import',
		'smssmpt_render_import_page'
	);
}

/*
 * -------------------------------------------------------------------
 *  Admin page renderer
 * -------------------------------------------------------------------
*/

/**
 * Render import UI and process upload.
 *
 * @return void
 */
function smssmpt_render_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'smile-assisted-import' ) );
	}

	$report = array();

	if ( isset( $_POST['smssmpt_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smssmpt_import_nonce'] ) ), 'smssmpt_import_action' ) ) {
		if ( isset( $_FILES['smssmpt_package']['tmp_name'], $_FILES['smssmpt_package']['error'] ) ) {
			$tmp_path = sanitize_text_field( wp_unslash( $_FILES['smssmpt_package']['tmp_name'] ) );

			if ( ! empty( $tmp_path ) && is_uploaded_file( $tmp_path ) && UPLOAD_ERR_OK === (int) $_FILES['smssmpt_package']['error'] ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';

				global $wp_filesystem;

				if ( ! $wp_filesystem ) {
					WP_Filesystem();
				}

				if ( ! $wp_filesystem ) {
					$report['error'] = esc_html__( 'Please upload a JSON package.', 'smile-assisted-import' );
				} else {
					$json = $wp_filesystem->get_contents( $tmp_path );

					if ( false === $json ) {
						$report['error'] = esc_html__( 'Please upload a JSON package.', 'smile-assisted-import' );
					}
				}

				if ( empty( $report['error'] ) ) {
					$pkg = json_decode( $json, true );

					if ( ! is_array( $pkg ) || empty( $pkg['version'] ) ) {
						$report['error'] = esc_html__( 'Invalid package format.', 'smile-assisted-import' );
					} else {
						$report = smssmpt_process_package( $pkg );
					}
				}
			} else {
				$report['error'] = esc_html__( 'Please upload a JSON package.', 'smile-assisted-import' );
			}
		} else {
			$report['error'] = esc_html__( 'Please upload a JSON package.', 'smile-assisted-import' );
		}
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SMiLE Assisted Import', 'smile-assisted-import' ); ?></h1>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'smssmpt_import_action', 'smssmpt_import_nonce' ); ?>

			<p><?php esc_html_e( 'Upload a SMiLE JSON package exported from the source site. The importer will download attachments, rewrite URLs to this site, create synced patterns (wp_block), remap pattern refs, and then import pages.', 'smile-assisted-import' ); ?></p>

			<p>
				<label for="smssmpt_package"><?php esc_html_e( 'JSON package', 'smile-assisted-import' ); ?></label><br />
				<input type="file" id="smssmpt_package" name="smssmpt_package" accept="application/json" />
			</p>

			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import package', 'smile-assisted-import' ); ?></button>
			</p>
		</form>

		<?php if ( ! empty( $report ) ) : ?>
			<hr />
			<h2><?php esc_html_e( 'Import report', 'smile-assisted-import' ); ?></h2>
			<pre style="background:#fff;border:1px solid #ccd0d4;padding:10px;max-height:320px;overflow:auto;"><?php echo esc_html( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}

/*
 * -------------------------------------------------------------------
 *  Package processing
 * -------------------------------------------------------------------
*/

/**
 * Process a package: download media, import wp_block (map old->new IDs),
 * then import pages with ref remapping and URL rewriting.
 *
 * @param array $pkg Package data.
 * @return array
 */
function smssmpt_process_package( $pkg ) {
	$origin = isset( $pkg['site_origin'] ) ? (string) $pkg['site_origin'] : '';
	$to     = trailingslashit( home_url( '/' ) );

	$map_urls      = array(); // old => new media URLs.
	$map_blocks    = array(); // slug => new ID (still useful).
	$map_block_ids = array(); // OLD block ID => NEW block ID.

	$media_report  = smssmpt_import_media( $pkg, $origin, $to, $map_urls );
	$blocks_report = smssmpt_import_posts( $pkg, 'wp_blocks', 'wp_block', $origin, $to, $map_urls, $map_blocks, $map_block_ids );
	$pages_report  = smssmpt_import_posts( $pkg, 'pages', 'page', $origin, $to, $map_urls, $map_blocks, $map_block_ids );

	return array(
		'media'  => $media_report,
		'blocks' => $blocks_report,
		'pages'  => $pages_report,
	);
}

/**
 * Import media: download each URL and map origin URL to local URL.
 *
 * @param array  $pkg       Package.
 * @param string $origin    Origin base.
 * @param string $to        Destination base.
 * @param array  $map_urls  URL map.
 * @return array
 */
function smssmpt_import_media( $pkg, $origin, $to, &$map_urls ) {
	if ( empty( $pkg['media'] ) || ! is_array( $pkg['media'] ) ) {
		return array( 'downloaded' => 0 );
	}

	$count    = 0;
	$errors   = array();
	$uploaded = array();

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( $pkg['media'] as $url ) {
		$url = (string) $url;

		if ( isset( $map_urls[ $url ] ) ) {
			continue;
		}

		if ( 0 === strpos( $url, $to ) ) {
			$map_urls[ $url ] = $url;
			continue;
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$errors[] = array(
				'url'   => $url,
				'error' => $tmp->get_error_message(),
			);
			continue;
		}

		$file_array = array(
			'name'     => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$att_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $att_id ) ) {
			$delete_result = wp_delete_file( $tmp );
			$error_message = $att_id->get_error_message();

			if ( is_wp_error( $delete_result ) ) {
				$error_message .= ' Temporary file removal failed: ' . $delete_result->get_error_message();
			}

			$errors[] = array(
				'url'   => $url,
				'error' => $error_message,
			);
			continue;
		}

		$new_url          = wp_get_attachment_url( $att_id );
		$map_urls[ $url ] = $new_url;
		$uploaded[ $url ] = $new_url;
		++$count;
	}

	return array(
		'downloaded' => $count,
		'uploaded'   => $uploaded,
		'errors'     => $errors,
	);
}

/*
 * -------------------------------------------------------------------
 *  Content transforms
 * -------------------------------------------------------------------
*/

/**
 * Rewrite URLs inside block content.
 *
 * @param string $content Content.
 * @param string $origin  Origin base (with trailing slash).
 * @param string $to      Destination base (with trailing slash).
 * @param array  $map     Map old => new for specific media.
 * @return string
 */
function smssmpt_rewrite_urls( $content, $origin, $to, $map ) {
	if ( ! empty( $map ) ) {
		$content = strtr( $content, $map );
	}

	if ( $origin && $to && $origin !== $to ) {
		$content = str_replace( $origin, $to, $content );
	}

	return $content;
}

/*
 * -------------------------------------------------------------------
 *  Remap de refs de patrones (OLD ID => NEW ID)
 * -------------------------------------------------------------------
*/

/**
 * Remap pattern refs (<!-- wp:block {"ref":OLD} /-->) to NEW IDs.
 *
 * @param string $content       Page content.
 * @param array  $map_block_ids OLD block ID => NEW block ID.
 * @return string
 */
function smssmpt_remap_block_refs( $content, $map_block_ids ) {
	if ( empty( $map_block_ids ) || '' === $content ) {
		return $content;
	}

	$pattern  = '/(<!--\s*wp:block\s+\{[^}]*"ref"\s*:\s*)(\d+)([^}]*\}\s*(?:\/\s*)?-->)/is';
	$callback = function ( $m ) use ( $map_block_ids ) {
		$old = (int) $m[2];
		$new = isset( $map_block_ids[ $old ] ) ? (int) $map_block_ids[ $old ] : $old;
		return $m[1] . $new . $m[3];
	};

	// ¡Aquí estaba el fallo! Faltaba pasar $content como 3er argumento.
	$content = preg_replace_callback( $pattern, $callback, $content );

	return $content;
}


/*
 * -------------------------------------------------------------------
 *  Posts import (wp_block first; pages after with ref remap)
 * -------------------------------------------------------------------
*/

/**
 * Import posts (wp_block or page) with URL rewriting, ref remapping and meta.
 *
 * @param array  $pkg            Package.
 * @param string $key            Array key in package.
 * @param string $post_type      Target post type.
 * @param string $origin         Origin base.
 * @param string $to             Destination base.
 * @param array  $map_urls       URL map.
 * @param array  $map_blocks     Block map (slug => new ID).
 * @param array  $map_block_ids  Block ID map (old => new).
 * @return array
 */
function smssmpt_import_posts( $pkg, $key, $post_type, $origin, $to, &$map_urls, &$map_blocks, &$map_block_ids ) {
	$report = array(
		'created' => array(),
		'updated' => array(),
		'errors'  => array(),
	);

	if ( empty( $pkg[ $key ] ) || ! is_array( $pkg[ $key ] ) ) {
		return $report;
	}

	foreach ( $pkg[ $key ] as $data ) {
		$title   = isset( $data['post_title'] ) ? (string) $data['post_title'] : '';
		$slug    = isset( $data['post_name'] ) ? (string) $data['post_name'] : '';
		$status  = isset( $data['post_status'] ) ? (string) $data['post_status'] : 'draft';
		$content = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';
		$excerpt = isset( $data['post_excerpt'] ) ? (string) $data['post_excerpt'] : '';
		$old_id  = isset( $data['ID'] ) ? (int) $data['ID'] : 0;

		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
			if ( '' === $slug ) {
				$slug = 'smile-item-' . wp_generate_uuid4();
			}
		}

		// 1) Remap media URLs.
		$content = smssmpt_rewrite_urls( $content, $origin, $to, $map_urls );

		// 2) Remap pattern refs (solo en páginas).
		if ( 'page' === $post_type ) {
			$content = smssmpt_remap_block_refs( $content, $map_block_ids );
		}

		if ( 'wp_block' === $post_type ) {
			$post_arr = array(
				'post_type'    => 'wp_block',
				'post_status'  => $status,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
			);

			$existing = get_page_by_path( $slug, OBJECT, 'wp_block' );
			if ( $existing ) {
				$post_arr['ID'] = $existing->ID;
				$new_id         = wp_update_post( wp_slash( $post_arr ), true );
				if ( is_wp_error( $new_id ) ) {
					$report['errors'][] = array(
						'type'  => 'wp_block',
						'slug'  => $slug,
						'error' => $new_id->get_error_message(),
					);
					continue;
				}
				$report['updated'][] = $new_id;
			} else {
				$new_id = wp_insert_post( wp_slash( $post_arr ), true );
				if ( is_wp_error( $new_id ) ) {
					$report['errors'][] = array(
						'type'  => 'wp_block',
						'slug'  => $slug,
						'error' => $new_id->get_error_message(),
					);
					continue;
				}
				$report['created'][] = $new_id;
			}

			// Map: slug => new ID y OLD_ID => NEW_ID.
			$map_blocks[ $slug ] = (int) $new_id;
			if ( $old_id > 0 ) {
				$map_block_ids[ $old_id ] = (int) $new_id;
			}

			// Meta.
			if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
				smssmpt_restore_meta( $new_id, $data['meta'] );
			}
		} else {
			$post_arr = array(
				'post_type'    => 'page',
				'post_status'  => $status,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'menu_order'   => isset( $data['menu_order'] ) ? (int) $data['menu_order'] : 0,
			);

			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existing ) {
				$post_arr['ID'] = $existing->ID;
				$new_id         = wp_update_post( wp_slash( $post_arr ), true );
				if ( is_wp_error( $new_id ) ) {
					$report['errors'][] = array(
						'type'  => 'page',
						'slug'  => $slug,
						'error' => $new_id->get_error_message(),
					);
					continue;
				}
				$report['updated'][] = $new_id;
			} else {
				$new_id = wp_insert_post( wp_slash( $post_arr ), true );
				if ( is_wp_error( $new_id ) ) {
					$report['errors'][] = array(
						'type'  => 'page',
						'slug'  => $slug,
						'error' => $new_id->get_error_message(),
					);
					continue;
				}
				$report['created'][] = $new_id;
			}

			// Meta.
			if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
				smssmpt_restore_meta( $new_id, $data['meta'] );
			}
		}
	}

	return $report;
}

/**
 * Restore post meta.
 *
 * @param int   $post_id Post ID.
 * @param array $meta    Meta array (key => [values]).
 * @return void
 */
function smssmpt_restore_meta( $post_id, $meta ) {
	foreach ( $meta as $key => $values ) {
		$key = sanitize_key( (string) $key );
		delete_post_meta( $post_id, $key );
		foreach ( (array) $values as $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
