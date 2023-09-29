<?php

defined( 'ABSPATH' ) || exit;

class WPEI_Export {

	public function __construct() {
		add_action( 'export_wp_post_ids', array( $this, 'add_attachments' ), 10, 2 );

		require_once __DIR__ . '/export.php';
		add_action( 'export_wp', 'WPEI_export_wp' );
	}

	private function attachment_url_to_postid( $url ) {
		global $wpdb;

		// Remove the upload path directories to get a path relative to the 'uploads' directory.
		$upload_dir = wp_upload_dir();
		$path = str_replace( $upload_dir['baseurl'] . '/', '', $url );

		// Remove any resizing or cropping suffix (e.g., -150x150, -300x200, etc.).
		$path = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $path );

		// Fetch the attachment ID from the database.
		$sql = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$path
		);
		$attachment_id = $wpdb->get_var($sql);

		return $attachment_id ? absint($attachment_id) : 0;
	}

	/**
	 * Filter exported data to add attachments
	 */
	public function add_attachments( $post_ids, $args ) {
		if ( 'post' !== $args['content'] ) {
			return $post_ids;
		}
		$attachment_ids = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			// Fetch the thumbnail.
			$thumbnail_id = get_post_thumbnail_id( $post->ID );
			if ( $thumbnail_id ) {
				$attachment_ids[] = $thumbnail_id;
			}

			// Get images from the_content.
			$post_content = $post->post_content;
			preg_match_all( '/<img [^>]*src=["|\'](.*?)["|\']/i', $post_content, $matches );
			$image_urls = $matches[1] ?? [];

			foreach ( $image_urls as $image_url ) {
				$attachment_id = $this->attachment_url_to_postid( $image_url );
				if ( $attachment_id ) {
					$attachment_ids[] = $attachment_id;
				}
			}
		}
		$attachment_ids = array_unique( $attachment_ids );
		sort( $attachment_ids );

		return array_merge( $attachment_ids, $post_ids );
	}
}

new WPEI_Export();
