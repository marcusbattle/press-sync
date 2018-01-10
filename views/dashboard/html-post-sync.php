<div class="wrap press-sync">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p>The easiest way to synchronize content between two WordPress sites.</p>
    <?php \WDS\PressSync\PressSyncPlugin::init()->include_page( 'dashboard/nav' ); ?>
	<form class="form" method="post" action="options.php">
		<?php settings_fields( 'ps-sync-options' ); ?>
		<?php do_settings_sections( 'ps-sync-options' ); ?>
		<div style="max-width: 800px;">
			<p>In order to sync individual posts, we'll define the settings to connect to the remote site here. After you've defined these settings, view the edit screen of any post type to manage the sync status of the individual post.</p>
		</div>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">Allowed Post Types</th>
				<td>
					<!-- <input type="text" name="remote_press_sync_key" value="<?php echo esc_attr( get_option( 'remote_press_sync_key' ) ); ?>" /> -->
					<p>Select the boxes that you want to enable individual post sync on.</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>