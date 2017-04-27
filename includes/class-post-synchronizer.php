<?php

class Press_Sync_Post_Synchronizer {
	/**
	 *
	 */
	public function hooks() {
		add_action( 'press_sync_insert_new_post', array( $this, 'add_p2p_connections' ), 10, 2 );
	}

	/**
	 * Syncs a post of any type
	 *
	 * @since 0.1.0
	 *
	 * @param array $post_args
	 * @param string $duplicate_action
	 * @return array
	 */
	public function sync_post( $post_args, $duplicate_action ) {

		if ( ! $post_args ) {
			return false;
		}

		// Set the correct post author
		$post_args['post_author'] = $this->get_press_sync_author_id( $post_args['post_author'] );

		// Check for post parent and update IDs accordingly
		if ( isset( $post_args['post_parent'] ) && $post_parent_id = $post_args['post_parent'] ) {

			$post_parent_args['post_type'] = $post_args['post_type'];
			$post_parent_args['meta_input']['press_sync_post_id'] = $post_parent_id;

			$parent_post = $this->get_synced_post( $post_parent_args );

			$post_args['post_parent'] = ( $parent_post ) ? $parent_post['ID'] : 0;

		}

		// Check to see if the post exists
		$local_post = $this->get_synced_post( $post_args );

		// Check to see a non-synced duplicate of the post exists
		if ( 'sync' == $duplicate_action && ! $local_post ) {
			$local_post = $this->get_non_synced_duplicate( $post_args['post_name'], $post_args['post_type'] );
		}

		// Update the existing ID of the post if present
		$post_args['ID'] = isset( $local_post['ID'] ) ? $local_post['ID'] : 0;

		// Determine which content is newer, local or remote
		if ( $local_post && ( strtotime( $local_post['post_modified'] ) >= strtotime( $post_args['post_modified'] ) ) ) {

			// If we're here, then we need to keep our local version
			$response['remote_post_id']	= $post_args['meta_input']['press_sync_post_id'];
			$response['local_post_id'] 	= $local_post['ID'];
			$response['message'] 		= __( 'Local version is newer than remote version', 'press-sync' );

			// Assign a press sync ID
			$this->add_press_sync_id( $local_post['ID'], $post_args );

			return array( 'debug' => $response );

		}

		// Add categories
		if ( isset( $post_args['tax_input']['category'] ) && $post_args['tax_input']['category'] ) {

			require_once( ABSPATH . '/wp-admin/includes/taxonomy.php');

			foreach( $post_args['tax_input']['category'] as $category ) {
				wp_insert_category( array(
					'cat_name'	=> $category
				) );
				$post_args['post_category'][] = $category;
			}

		}

		// Insert/update the post
		$local_post_id = wp_insert_post( $post_args );

		// Bail if the insert didn't work
		if ( is_wp_error( $local_post_id ) ) {
			return array( 'debug' => $local_post_id );
		}

		// Attach featured image
		$this->attach_featured_image( $local_post_id, $post_args );

		// Attach any comments
		$comments = isset( $post_args['comments'] ) ? $post_args['comments'] : array();
		$this->attach_comments( $local_post_id, $comments );

		// Set taxonomies for custom post type
		// if ( ! in_array( $post_args['post_type'], array( 'post', 'page' ) ) ) {

		if ( isset( $post_args['tax_input'] ) ) {

			foreach ( $post_args['tax_input'] as $taxonomy => $terms )	{
				wp_set_object_terms( $local_post_id, $terms, $taxonomy, false );
			}

		}

		// }

		// Run any secondary commands
		do_action( 'press_sync_insert_new_post', $local_post_id, $post_args );

		return array( 'debug' => array(
			'remote_post_id'	=> $post_args['meta_input']['press_sync_post_id'],
			'local_post_id'		=> $local_post_id,
			'message'			=> __( 'The post has been synced with the remote server', 'press-sync' ),
		) );

	}

	public function get_synced_post( $post_args ) {

		global $wpdb;

		// Capture the press sync post ID
		$press_sync_post_id = isset( $post_args['meta_input']['press_sync_post_id'] ) ? $post_args['meta_input']['press_sync_post_id'] : 0;

		$sql = "
			SELECT post_id AS ID, post_type, post_modified FROM $wpdb->postmeta AS meta
			LEFT JOIN $wpdb->posts AS posts ON posts.ID = meta.post_id
			WHERE meta.meta_key = 'press_sync_post_id' AND meta.meta_value = %d AND posts.post_type = %s
			LIMIT 1
		";

		$prepared_sql = $wpdb->prepare( $sql, $press_sync_post_id, $post_args['post_type'] );
		$post = $wpdb->get_row( $prepared_sql, ARRAY_A );

		return ( $post ) ? $post : false;

	}

	public function comment_exists( $comment_args = array() ) {

		$press_sync_comment_id 	= isset( $comment_args['meta_input']['press_sync_comment_id'] ) ? $comment_args['meta_input']['press_sync_comment_id'] : 0;
		$press_sync_source 		= isset( $comment_args['meta_input']['press_sync_source'] ) ? $comment_args['meta_input']['press_sync_source'] : 0;

		$query_args = array(
			'number'		=> 1,
			'meta_query' 	=> array(
				array(
					'key'     => 'press_sync_comment_id',
					'value'   => $press_sync_comment_id,
					'compare' => '='
				),
				array(
					'key'     => 'press_sync_source',
					'value'   => $press_sync_source,
					'compare' => '='
				),
			)
		);

		$comment = get_comments( $query_args );

		if ( $comment ) {
			return (array) $comment[0];
		}

		return false;

	}

	public function get_post_by_orig_id( $press_sync_post_id ) {

		global $wpdb;

		$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'press_sync_post_id' AND meta_value = $press_sync_post_id";

		return $wpdb->get_var( $sql );

	}

	/**
	 * Returns the original author ID of the synced post
	 *
	 * @since 0.1.0
	 *
	 * @param integer $user_id
	 * @return integer $user_id
	 */
	public function get_press_sync_author_id( $user_id ) {

		if ( ! $user_id ) {
			return 1;
		}

		global $wpdb;

		$sql = "SELECT user_id AS ID FROM $wpdb->usermeta WHERE meta_key = 'press_sync_user_id' AND meta_value = %d";
		$prepared_sql = $wpdb->prepare( $sql, $user_id );

		$press_sync_user_id = $wpdb->get_var( $prepared_sql );

		return ( $press_sync_user_id ) ? $press_sync_user_id : 1;

	}

	public function attach_featured_image( $post_id, $post_args ) {

		// Post does not have a featured image so bail early.
		if ( empty( $post_args['featured_image'] ) ) {
			return false;
		}

		// Allow download_url() to use an external request to retrieve featured images.
		add_filter( 'http_request_host_is_external', array( $this, 'allow_sync_external_host' ), 10, 3 );

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params( $post_args['featured_image'] );

		// Download the attachment
		$attachment 	= $this->insert_new_media( $request, true );
		$thumbnail_id 	= isset( $attachment['id'] ) ? $attachment['id'] : 0;

		$response = set_post_thumbnail( $post_id, $thumbnail_id );

		// Remove filter that allowed an external request to be made via download_url().
		remove_filter( 'http_request_host_is_external', array( $this, 'allow_sync_external_host' ) );

	}

	public function attach_comments( $post_id, $comments ) {

		if ( empty( $post_id ) || ! $comments ) {
			return;
		}

		foreach ( $comments as $comment_args ) {

			// Check to see if the comment already exists
			if ( $comment = $this->comment_exists( $comment_args ) ) {
				continue;
			}

			// Set Comment Post ID to correct local Post ID
			$comment_args['comment_post_ID'] = $post_id;

			// Get the comment author ID
			$comment_args['user_id'] = $this->get_press_sync_author_id( $comment_args['post_author'] );

			$comment_id = wp_insert_comment( $comment_args );

			if ( ! is_wp_error( $comment_id ) ) {

				foreach ( $comment_args['meta_input'] as $meta_key => $meta_value ) {
					update_comment_meta( $comment_id, $meta_key, $meta_value );
				}
			}

		}

	}

	public function add_p2p_connections( $post_id, $post_args ) {

		if ( ! class_exists('P2P_Autoload') || ! $post_args['p2p_connections'] ) {
			return;
		}

		$connections = isset( $post_args['p2p_connections'] ) ? $post_args['p2p_connections'] : array();

		if ( ! $connections ) {
			return;
		}

		foreach ( $connections as $connection ) {

			$p2p_from 	= $this->get_post_id_by_press_sync_id( $connection['p2p_from'] );
			$p2p_to 	= $this->get_post_id_by_press_sync_id( $connection['p2p_to'] );
			$p2p_type 	= $connection['p2p_type'];

			$response = p2p_type( $p2p_type )->connect( $p2p_from, $p2p_to );

		}

	}

	public function get_post_id_by_press_sync_id( $press_sync_post_id ) {

		global $wpdb;

		$sql 		= "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'press_sync_post_id' AND meta_value = $press_sync_post_id";
		$post_id 	= $wpdb->get_var( $sql );

		return $post_id;
	}

	public function insert_comments( $post_id, $post_args ) {
		// Post ID empty or post does not have any comments so bail early.
		if ( empty( $post_id ) || ( ! array_key_exists( 'comments', $post_args ) && empty( $post_args['comments'] ) ) ) {
			return false;
		}

		foreach ( $post_args['comments'] as $comment ) {
			$comment['comment_post_ID'] = $post_id;
			if ( isset( $comment['comment_post_ID'] ) ) {
				wp_insert_comment( $comment );
			}
		}
	}

	/**
	 * Checks to see if a non-synced duplicate exists
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_name
	 * @param string $post_type
	 * @return WP_Post
	 */
	public function get_non_synced_duplicate( $post_name, $post_type ) {

		global $wpdb;

		$sql = "SELECT ID, post_type, post_modified FROM $wpdb->posts WHERE post_name = %s AND post_type = %s";
		$prepared_sql = $wpdb->prepare( $sql, $post_name, $post_type );

		$post = $wpdb->get_row( $prepared_sql, ARRAY_A );

		return ( $post ) ? $post : false;

	}

	/**
	 * Assign a WP Object the missing press sync ID
	 *
	 * @since 0.1.0
	 *
	 * @param array $object_args
	 * @return boolean
	 */
	public function add_press_sync_id( $object_id, $object_args ) {

		if ( ! isset( $object_args['post_type'] ) ) {
			return false;
		}

		$press_sync_post_id = isset( $object_args['meta_input']['press_sync_post_id'] ) ? $object_args['meta_input']['press_sync_post_id'] : '';
		$press_sync_source = isset( $object_args['meta_input']['press_sync_source'] ) ? $object_args['meta_input']['press_sync_source'] : '';

		update_post_meta( $object_id, 'press_sync_post_id', $press_sync_post_id );
		update_post_meta( $object_id, 'press_sync_source', $press_sync_source );

	}
}

