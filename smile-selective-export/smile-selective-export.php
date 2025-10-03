<?php
/**
 * Plugin Name: SMiLE Selective Export
 * Description: Export selected pages with their synced patterns (wp_block) and referenced media into a JSON package.
 * Version: 1.0.2
 * Author: Smile
 * Text Domain: smile-selective-export
 *
 * @package smile-selective-export
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
add_action( 'admin_menu', 'smslxpt_export_admin_menu' );

/**
 * Register admin page under Tools.
 *
 * @return void
 */
function smslxpt_export_admin_menu() {
	add_management_page(
		esc_html__( 'SMiLE Selective Export', 'smile-selective-export' ),
		esc_html__( 'SMiLE Selective Export', 'smile-selective-export' ),
		'manage_options',
		'smile-selective-export',
		'smslxpt_render_export_page'
	);
}

/*
 * -------------------------------------------------------------------
 *  Admin-post handler (download JSON without rendering admin)
 * -------------------------------------------------------------------
*/
add_action( 'admin_post_smslxpt_export', 'smslxpt_handle_export_download' );

/**
 * Handle export submission and stream pure JSON to the browser.
 *
 * @return void
 */
function smslxpt_handle_export_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export.', 'smile-selective-export' ) );
	}

	$nonce = isset( $_POST['smslxpt_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['smslxpt_export_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'smslxpt_export_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'smile-selective-export' ) );
	}

$selected_raw = isset( $_POST['smslxpt_pages'] ) ? wp_unslash( $_POST['smslxpt_pages'] ) : array();
$selected_raw = is_array( $selected_raw ) ? $selected_raw : array( $selected_raw );
$selected      = array_map( 'absint', $selected_raw );
$page_ids = array();

	foreach ( $selected as $id ) {
		$id = absint( $id );
		if ( $id > 0 ) {
			$page_ids[ $id ] = $id;
		}
	}

	if ( empty( $page_ids ) ) {
		wp_die( esc_html__( 'No pages selected.', 'smile-selective-export' ) );
	}

	$export = smslxpt_build_export_package( array_values( $page_ids ) );
	if ( is_wp_error( $export ) ) {
		wp_die( esc_html( $export->get_error_message() ) );
	}

	$filename = 'smile-export-' . gmdate( 'Ymd-His' ) . '.json';

	ignore_user_abort( true );
	nocache_headers();
	header( 'Content-Type: application/json; charset=' . get_bloginfo( 'charset' ) );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo wp_json_encode( $export );

	exit;
}

/*
 * -------------------------------------------------------------------
 *  Helpers: scanning content to collect refs
 * -------------------------------------------------------------------
*/

/**
 * Extract referenced wp_block IDs from post content (synced patterns).
 *
 * @param string $content Post content.
 * @return array
 */
function smslxpt_extract_wp_block_refs( $content ) {
	$refs = array();

	// Soporta: <!-- wp:block {"ref":123} /--> y <!-- wp:block {"ref":123} -->.
	$pattern = '/<!--\s*wp:block\s+\{[^}]*"ref"\s*:\s*(\d+)[^}]*\}\s*(?:\/\s*)?-->/is';
	if ( preg_match_all( $pattern, $content, $matches ) ) {
		foreach ( $matches[1] as $maybe_id ) {
			$id = absint( $maybe_id );
			if ( $id > 0 ) {
				$refs[ $id ] = $id;
			}
		}
	}

	return array_values( $refs );
}

/**
 * Extract attachment IDs and absolute media URLs from post content.
 *
 * @param string $content Post content.
 * @return array{ids: int[], urls: string[]}
 */
function smslxpt_extract_media( $content ) {
	$ids  = array();
	$urls = array();

	// core/image con "id":123.
	$pattern_id = '/"id"\s*:\s*(\d+)/';
	if ( preg_match_all( $pattern_id, $content, $matches_id ) ) {
		foreach ( $matches_id[1] as $mid ) {
			$aid = absint( $mid );
			if ( $aid > 0 ) {
				$ids[ $aid ] = $aid;
			}
		}
	}

	// URLs absolutas típicas de medios.
	$pattern_url = '/\bhttps?:\/\/[^\s"\']+\.(?:png|jpe?g|gif|webp|svg|mp4|webm|ogg)\b/i';
	if ( preg_match_all( $pattern_url, $content, $matches_url ) ) {
		foreach ( $matches_url[0] as $url ) {
			$urls[ $url ] = $url;
		}
	}

	return array(
		'ids'  => array_values( $ids ),
		'urls' => array_values( $urls ),
	);
}

/**
 * Build a serializable array from a post (page or wp_block).
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function smslxpt_serialize_post( $post ) {
	$slug = (string) $post->post_name;
	if ( '' === $slug ) {
		$slug = sanitize_title( (string) $post->post_title );
		if ( '' === $slug ) {
			$slug = 'smile-item-' . (int) $post->ID;
		}
	}

	return array(
		'ID'           => (int) $post->ID,
		'post_type'    => $post->post_type,
		'post_title'   => $post->post_title,
		'post_name'    => $slug,
		'post_status'  => $post->post_status,
		'post_parent'  => (int) $post->post_parent,
		'post_content' => $post->post_content,
		'post_excerpt' => $post->post_excerpt,
		'menu_order'   => (int) $post->menu_order,
                'meta'         => smslxpt_collect_meta( $post->ID ),
	);
}

/**
 * Collect post meta in a safe array.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function smslxpt_collect_meta( $post_id ) {
	$all = get_post_meta( $post_id );
	$out = array();

	foreach ( $all as $key => $values ) {
		$tmp = array();
		foreach ( $values as $value ) {
			$tmp[] = maybe_unserialize( $value );
		}
		$out[ $key ] = $tmp;
	}

	return $out;
}

/*
 * -------------------------------------------------------------------
 *  Admin page renderer (posts to admin-post.php)
 * -------------------------------------------------------------------
*/

/**
 * Render export UI and submit to admin-post.php to output pure JSON.
 *
 * @return void
 */
function smslxpt_render_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'smile-selective-export' ) );
	}

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'posts_per_page' => 20,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

       $action_url = admin_url( 'admin-post.php' );
	?>
	<div class="wrap">
        <h1><?php esc_html_e( 'SMiLE Selective Export', 'smile-selective-export' ); ?></h1>
               <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                        <input type="hidden" name="action" value="smslxpt_export" />
                        <?php wp_nonce_field( 'smslxpt_export_action', 'smslxpt_export_nonce' ); ?>

                    <p><?php esc_html_e( 'Select the pages to export. The package will include any synced patterns (wp_block) and media referenced by those pages.', 'smile-selective-export' ); ?></p>

			<ul style="max-height:320px;overflow:auto;border:1px solid #ccd0d4;padding:10px;">
				<?php foreach ( $pages as $pid ) : ?>
					<li>
						<label>
                                                        <input type="checkbox" name="smslxpt_pages[]" value="<?php echo esc_attr( (string) $pid ); ?>" />
							<?php
							$title = get_the_title( $pid );
							echo esc_html( $title ) . ' (ID ' . (int) $pid . ')';
							?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>

			<p>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Export JSON package', 'smile-selective-export' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}

/*
 * -------------------------------------------------------------------
 *  Build export data
 * -------------------------------------------------------------------
*/

/**
 * Create export data: selected pages + required wp_block + media list.
 *
 * @param int[] $page_ids Page IDs.
 * @return array|\WP_Error
 */
function smslxpt_build_export_package( $page_ids ) {
	$page_ids = array_map( 'absint', (array) $page_ids );
	$page_ids = array_filter( $page_ids );

	if ( empty( $page_ids ) ) {
		return new WP_Error( 'smslxpt_no_pages', esc_html__( 'No pages selected.', 'smile-selective-export' ) );
	}

	$posts       = array();
	$blocks      = array();
	$media_ids   = array();
	$media_urls  = array();
	$site_origin = trailingslashit( home_url( '/' ) );

	// Collect selected pages.
	foreach ( $page_ids as $pid ) {
		$post = get_post( $pid );
		if ( $post && 'page' === $post->post_type ) {
			$serialized    = smslxpt_serialize_post( $post );
			$posts[ $pid ] = $serialized;

			$media_from_page = smslxpt_extract_media( $post->post_content );
			foreach ( $media_from_page['ids'] as $mid ) {
				$media_ids[ $mid ] = $mid;
			}
			foreach ( $media_from_page['urls'] as $u ) {
				$media_urls[ $u ] = $u;
			}

			$refs = smslxpt_extract_wp_block_refs( $post->post_content );
			foreach ( $refs as $bid ) {
				$blocks[ $bid ] = $bid;
			}
		}
	}

	// Collect referenced wp_block posts (synced patterns).
	if ( ! empty( $blocks ) ) {
		$ref_posts = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post__in'       => array_values( $blocks ),
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			)
		);

		$blocks = array();

		foreach ( $ref_posts as $bpost ) {
			$serialized           = smslxpt_serialize_post( $bpost );
			$blocks[ $bpost->ID ] = $serialized;

			$m = smslxpt_extract_media( $bpost->post_content );
			foreach ( $m['ids'] as $mid ) {
				$media_ids[ $mid ] = $mid;
			}
			foreach ( $m['urls'] as $u ) {
				$media_urls[ $u ] = $u;
			}
		}
	} else {
		$blocks = array();
	}

	// Resolve URLs for attachment IDs.
	if ( ! empty( $media_ids ) ) {
		foreach ( $media_ids as $aid ) {
			$src = wp_get_attachment_url( $aid );
			if ( $src ) {
				$media_urls[ $src ] = $src;
			}
		}
	}

	$package = array(
		'version'      => '1.0.0',
		'site_origin'  => $site_origin,
		'generated_at' => gmdate( 'c' ),
		'pages'        => array_values( $posts ),
		'wp_blocks'    => array_values( $blocks ),
		'media'        => array_values( $media_urls ),
	);

	return $package;
}
