<?php

use Press_Sync_API_Validator as Validator;
use Press_Sync_Post_Synchronizer as Synchronizer;

/**
 * Class Press_Sync_API_Route_Post
 */
class Press_Sync_API_Route_Post extends Press_Sync_API_Abstract_Route_Post_Type {
	/**
	 * Press_Sync_API_Route_Post constructor.
	 *
	 * @param Press_Sync_API_Validator     $validator
	 * @param Press_Sync_Post_Synchronizer $synchronizer
	 */
	public function __construct( Validator $validator, Synchronizer $synchronizer ) {
		$this->validator    = $validator;
		$this->synchronizer = $synchronizer;
	}

	/**
	 *
	 */
	public function register_routes() {
		register_rest_route( 'press-sync/v1', '/post', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this->synchronizer, 'sync_posts' ),
			'permission_callback' => array( $this->validator, 'validate_sync_key' ),
		) );
	}
}
