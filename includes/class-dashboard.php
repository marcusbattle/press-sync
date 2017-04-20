<?php

class Press_Sync_Dashboard {

	/**
	 * Parent plugin class.
	 *
	 * @var   Press_Sync
	 * @since 0.1.0
	 */
	protected $plugin = null;

	/**
	 * Prefix for meta keys
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $prefix = 'press_sync_dashboard_';

	/**
	 * Constructor.
	 *
	 * @since  0.1.0
	 *
	 * @param  WDS_Fordham_Library_Calendar $plugin Main plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();
	}

	/**
	 * Add our hooks.
	 *
	 * @since  0.1.0
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 10, 1 );
		add_action( 'admin_notices', array( $this, 'error_notice' ) );

		// CMB2 hooks
		add_action( 'cmb2_admin_init', array( $this, 'init_press_sync_dashboard_metabox' ) );
		add_action( 'cmb2_admin_init', array( $this, 'init_press_sync_settings_metabox' ) );
		add_action( 'cmb2_render_connection_status', array( $this, 'render_connection_status_field' ), 10, 5 );
	}

	/**
	 * Initialize the menu page
	 *
	 * @since 0.1.0
	 */
	public function add_menu_page() {
		add_management_page( __( 'Press Sync','press-sync' ), __( 'Press Sync','press-sync' ), 'manage_options', 'press-sync', array( $this, 'show_menu_page' ) );
	}

	/**
	 * Display the menu page in the 'Tools' section
	 *
	 * @since 0.1.0
	 */
	public function show_menu_page() {

		$selected_tab 	= isset( $_REQUEST['tab'] ) ? 'dashboard/' . $_REQUEST['tab'] : 'dashboard';
		$this->plugin->include_page( $selected_tab );
	}

	/**
	 * Initializes the CMB2 metabox for "Dashboard" tab in the dashboard
	 *
	 * @since 0.1.0
	 */
	public function init_press_sync_dashboard_metabox() {

		$cmb_options = new_cmb2_box( array(
			'id'      => $this->prefix . 'metabox',
			'title'   => __( 'Press Sync Metabox', 'press-sync' ),
			'hookup'  => false, // Do not need the normal user/post hookup
			'show_on' => array(
				// These are important, don't remove
				'key'   => 'options-page',
				'value' => array( 'press_sync_options' )
			),
		) );

		$cmb_options->add_field( array(
			'name'    => __( 'Remote Server', 'press-sync' ),
			'id'      => 'connected_server',
			'type'    => 'text',
		) );

		$cmb_options->add_field( array(
			'name'    => __( 'Remote Press Sync Key', 'press-sync' ),
			'id'      => 'remote_press_sync_key',
			'type'    => 'text',
		) );

		$cmb_options->add_field( array(
			'name'    => __( 'Connection Status', 'press-sync' ),
			'id'      => 'connection_status',
			'type'    => 'connection_status',
		) );

		$cmb_options->add_field( array(
			'name'    => __( 'Sync Method', 'press-sync' ),
			'id'      => 'sync_method',
			'type'    => 'select',
			'options' => array(
				'push' => 'Push'
			)
		) );

		$cmb_options->add_field( array(
			'name'       => __( 'Objects to Sync', 'press-sync' ),
			'id'         => 'objects_to_sync',
			'type'       => 'select',
			'options_cb' => array( $this, 'objects_to_sync' )
		) );

	}

	/**
	 * Initializes the CMB2 metabox for "Settings" tab in the dashboard
	 *
	 * @since 0.1.0
	 */
	public function init_press_sync_settings_metabox() {

		$prefix = $this->prefix . 'settings_';

		$cmb_options = new_cmb2_box( array(
			'id'      => $prefix . 'metabox',
			'title'   => __( 'Press Sync Settings', 'press-sync' ),
			'hookup'  => false, // Do not need the normal user/post hookup
			'show_on' => array(
				// These are important, don't remove
				'key'   => 'options-page',
				'value' => array( 'press_sync_options' )
			),
		) );

		$cmb_options->add_field( array(
			'name'  => __( 'Sync Key', 'press-sync' ),
			'id'    => 'press_sync_key',
			'desc'	=> __( 'This secure key is used to authenticate requests to your site. Without it, press sync won\'t work.','press-sync' ),
			'type'	=> 'text',
		) );

	}

	public function error_notice() {

		$press_sync_key = $this->plugin->press_sync_option('press_sync_key');

		if ( $press_sync_key ) {
			return;
		}

		?>
	    <div class="update-nag notice is-dismissable">
	        <p><?php _e( '<strong>PressSync:</strong> You must define your PressSync key before you can recieve updates from another WordPress site. <a href="tools.php?page=press-sync&tab=settings">Set it now</a>', 'press-sync' ); ?></p>
	    </div>
	    <?php
	}

	public function objects_to_sync( $objects = array() ) {

		$objects = array(
			'attachment' 	=> 'Media',
			'page' 			=> 'Pages',
			'post' 			=> 'Posts',
			'user'			=> 'Users',
		);

		$custom_post_types = get_post_types( array( '_builtin' => false ), 'objects' );

		if ( $custom_post_types ) {

			$objects[] = '-- Custom Post Types --';
			
			foreach ( $custom_post_types as $cpt ) {
				$objects[ $cpt->name ] = $cpt->label;
			}

		}

		$objects = apply_filters( 'press_sync_objects_to_sync', $objects );

		return $objects;

	}

	public function render_connection_status_field( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {

		$url = cmb2_get_option( 'press-sync-options', 'connected_server' );

		$is_connected = $this->plugin->check_connection( $url );

		if ( $is_connected ) {
			echo "<div><p>Connected</p></div>";
		} else {
			echo "<div><p>Not Connected</p></div>";
		}

	}

}