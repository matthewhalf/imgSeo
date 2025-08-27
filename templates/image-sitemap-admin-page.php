<?php
/**
 * Template for the Image Sitemap administration page.
 *
 * @package ImgSEO
 * @since   1.2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Verify user capability
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'imgseo' ) );
}

$sitemap_enabled_option_name = 'imgseo_sitemap_enabled';
$sitemap_url                 = home_url( '/imgseo-sitemap.xml' );
$sitemap_enabled_default     = true; // Default to true for new installations (also handled in activate())
$sitemap_enabled             = get_option( $sitemap_enabled_option_name, $sitemap_enabled_default );
$auto_refresh_enabled        = get_option( 'imgseo_sitemap_auto_refresh', false );
$auto_refresh_interval       = get_option( 'imgseo_sitemap_auto_refresh_interval', 'daily' );
$sitemap_needs_update        = get_option( 'imgseo_sitemap_needs_update', false );

// Gestione della programmazione/deprogrammazione del cron job
if (isset($_POST['imgseo_sitemap_auto_refresh'])) {
	$sitemap_generator = ImgSEO_Image_Sitemap_Generator::get_instance();
	if ($_POST['imgseo_sitemap_auto_refresh'] === '1') {
		// Programma il cron job
		$interval = sanitize_text_field($_POST['imgseo_sitemap_auto_refresh_interval']);
		$sitemap_generator->schedule_auto_refresh($interval);
	} else {
		// Rimuovi il cron job
		$sitemap_generator->unschedule_auto_refresh();
	}
}

// Retrieve sitemap information passed from the class
$template_data = get_query_var( 'imgseo_sitemap_data', array() );
$sitemap_exists = !empty($template_data['sitemap_exists']);
$sitemap_static_url = !empty($template_data['sitemap_url']) ? $template_data['sitemap_url'] : $sitemap_url;
$last_generated = !empty($template_data['last_generated']) ? $template_data['last_generated'] : 0;

// Handle sitemap settings save
if ( isset( $_POST['imgseo_save_sitemap_settings_nonce'] ) &&
     wp_verify_nonce( sanitize_key( $_POST['imgseo_save_sitemap_settings_nonce'] ), 'imgseo_save_sitemap_settings_action' ) ) {

	if ( isset( $_POST['imgseo_save_sitemap_settings'] ) ) {
		$current_sitemap_status_on_db = (bool) get_option( $sitemap_enabled_option_name, $sitemap_enabled_default );
		$new_sitemap_status_from_form = isset( $_POST[ $sitemap_enabled_option_name ] );
		$auto_refresh_enabled = isset( $_POST['imgseo_sitemap_auto_refresh'] );
		$auto_refresh_interval = sanitize_text_field( $_POST['imgseo_sitemap_auto_refresh_interval'] ?? 'daily' );

		// Update options in database
		update_option( $sitemap_enabled_option_name, $new_sitemap_status_from_form );
		update_option( 'imgseo_sitemap_auto_refresh', $auto_refresh_enabled );
		update_option( 'imgseo_sitemap_auto_refresh_interval', $auto_refresh_interval );
		$sitemap_enabled = $new_sitemap_status_from_form; // Update local variable for page rendering

		// Schedule or unschedule auto refresh
		if ( $auto_refresh_enabled && $new_sitemap_status_from_form ) {
			// Schedule auto refresh
			wp_clear_scheduled_hook( 'imgseo_auto_refresh_sitemap' );
			wp_schedule_event( time(), $auto_refresh_interval, 'imgseo_auto_refresh_sitemap' );
		} else {
			// Unschedule auto refresh
			wp_clear_scheduled_hook( 'imgseo_auto_refresh_sitemap' );
		}

		// Determine message and whether to flush
		if ( $new_sitemap_status_from_form ) {
			// Sitemap is now enabled
			flush_rewrite_rules(); // Always flush when enabling or saving as enabled
			if ( $new_sitemap_status_from_form != $current_sitemap_status_on_db ) {
				// Was disabled, now enabled
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image sitemap has been enabled and rewrite rules flushed.', 'imgseo' ) . '</p></div>';
			} else {
				// Was already enabled and saved
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image sitemap settings saved and rewrite rules flushed.', 'imgseo' ) . '</p></div>';
			}
		} else {
			// Sitemap is now disabled
			if ( $new_sitemap_status_from_form != $current_sitemap_status_on_db ) {
				// Was enabled, now disabled
				flush_rewrite_rules(); // Flush also when disabling to remove the rule
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image sitemap has been disabled and rewrite rules flushed.', 'imgseo' ) . '</p></div>';
			} else {
				// Was already disabled and saved
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Image sitemap settings saved. Sitemap remains disabled.', 'imgseo' ) . '</p></div>';
			}
		}
	}
}

// Check if manual rewrite rules flush was requested
if ( isset( $_POST['imgseo_force_flush_rules_nonce'] ) &&
     wp_verify_nonce( sanitize_key( $_POST['imgseo_force_flush_rules_nonce'] ), 'imgseo_force_flush_rules_action' ) ) {
    
    if ( isset( $_POST['imgseo_force_flush_rules'] ) ) {
        // Force manual flush of rewrite rules
        flush_rewrite_rules();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rewrite rules have been manually flushed. Try accessing your sitemap now.', 'imgseo' ) . '</p></div>';
    }
}
?>
<div class="wrap imgseo-admin-page">
	<h1><?php esc_html_e( 'Image Sitemap', 'imgseo' ); ?></h1>

	<p>
		<?php esc_html_e( 'Your image sitemap helps search engines discover and index the images on your site.', 'imgseo' ); ?>
	</p>

	<div id="dashboard-widgets-wrap">
		<div id="dashboard-widgets" class="metabox-holder">
			<div id="postbox-container-1" class="postbox-container">
				<div class="meta-box-sortables">

					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Sitemap Settings', 'imgseo' ); ?></span></h2>
						<div class="inside">
							<form method="post" action="">
								<?php wp_nonce_field( 'imgseo_save_sitemap_settings_action', 'imgseo_save_sitemap_settings_nonce' ); ?>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><?php esc_html_e( 'Enable Image Sitemap', 'imgseo' ); ?></th>
										<td>
											<label for="<?php echo esc_attr( $sitemap_enabled_option_name ); ?>">
												<input type="checkbox" id="<?php echo esc_attr( $sitemap_enabled_option_name ); ?>" name="<?php echo esc_attr( $sitemap_enabled_option_name ); ?>" value="1" <?php checked( $sitemap_enabled, true ); ?> />
												<?php esc_html_e( 'Generate an XML sitemap for images.', 'imgseo' ); ?>
											</label>
											<p class="description">
												<?php
												if ( $sitemap_enabled ) {
													printf(
														/* translators: %s: sitemap URL */
														esc_html__( 'Image sitemap is currently enabled and available at %s.', 'imgseo' ),
														'<a href="' . esc_url( $sitemap_static_url ) . '" target="_blank">' . esc_url( $sitemap_static_url ) . '</a>'
													);
												} else {
													esc_html_e( 'Image sitemap is currently disabled. Enable to make it available.', 'imgseo' );
												}
												?>
											</p>
											<p class="description">
												<?php esc_html_e( 'Submit the sitemap URL to search engines like Google Search Console once enabled.', 'imgseo' ); ?>
											</p>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><?php esc_html_e( 'Auto Refresh', 'imgseo' ); ?></th>
										<td>
											<label for="imgseo_sitemap_auto_refresh">
												<input type="checkbox" id="imgseo_sitemap_auto_refresh" name="imgseo_sitemap_auto_refresh" value="1" <?php checked( $auto_refresh_enabled, true ); ?> />
												<?php esc_html_e( 'Automatically refresh sitemap periodically.', 'imgseo' ); ?>
											</label>
											<p class="description">
												<?php esc_html_e( 'When enabled, the sitemap will be automatically updated at the specified interval.', 'imgseo' ); ?>
											</p>
											<select name="imgseo_sitemap_auto_refresh_interval" id="imgseo_sitemap_auto_refresh_interval">
												<option value="hourly" <?php selected( $auto_refresh_interval, 'hourly' ); ?>><?php esc_html_e( 'Every Hour', 'imgseo' ); ?></option>
												<option value="daily" <?php selected( $auto_refresh_interval, 'daily' ); ?>><?php esc_html_e( 'Daily', 'imgseo' ); ?></option>
												<option value="weekly" <?php selected( $auto_refresh_interval, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'imgseo' ); ?></option>
											</select>
										</td>
									</tr>
								</table>
								<p class="submit">
									<button type="submit" name="imgseo_save_sitemap_settings" class="button button-primary">
										<?php esc_html_e( 'Save Settings', 'imgseo' ); ?>
									</button>
									<span class="description" style="margin-left: 10px;">
										<?php esc_html_e( 'Saving will also regenerate URL rules if needed.', 'imgseo' ); ?>
									</span>
								</p>
							</form>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Sitemap URL', 'imgseo' ); ?></span></h2>
						<div class="inside">
							<?php if ( $sitemap_enabled ) : ?>
								<p>
									<?php esc_html_e( 'Your image sitemap is available at the following URL:', 'imgseo' ); ?>
								</p>
								<p>
									<a href="<?php echo esc_url( $sitemap_static_url ); ?>" target="_blank"><?php echo esc_url( $sitemap_static_url ); ?></a>
								</p>
								<p>
									<em><?php esc_html_e( 'Submit this URL to search engines like Google Search Console.', 'imgseo' ); ?></em>
								</p>
							<?php else : ?>
								<p>
									<?php esc_html_e( 'The image sitemap is currently disabled. Enable it in the "Sitemap Settings" section above to get the URL.', 'imgseo' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'Sitemap Management', 'imgseo' ); ?></span></h2>
						<div class="inside">
							<?php if ( $sitemap_enabled ) : ?>
								<?php if ( $sitemap_needs_update ) : ?>
									<div class="notice notice-warning inline">
										<p><strong><?php esc_html_e( 'Sitemap Update Required', 'imgseo' ); ?></strong></p>
										<p><?php esc_html_e( 'New images have been added or modified. Click REFRESH to update your sitemap.', 'imgseo' ); ?></p>
									</div>
								<?php endif; ?>
								
								<div style="margin-bottom: 20px;">
									<?php if ( !$sitemap_exists ) : ?>
										<form method="post" action="" style="display: inline-block; margin-right: 10px;">
											<?php wp_nonce_field( 'imgseo_activate_sitemap_action', 'imgseo_activate_sitemap_nonce' ); ?>
											<button type="submit" name="imgseo_activate_sitemap" class="button button-primary button-large">
												<?php esc_html_e( 'ACTIVATE', 'imgseo' ); ?>
											</button>
										</form>
										<p class="description" style="display: inline-block; margin-left: 10px; vertical-align: top; margin-top: 8px;">
											<?php esc_html_e( 'Create the sitemap file and activate URL rules.', 'imgseo' ); ?>
										</p>
									<?php else : ?>
										<form method="post" action="" style="display: inline-block; margin-right: 10px;">
											<?php wp_nonce_field( 'imgseo_refresh_sitemap_action', 'imgseo_refresh_sitemap_nonce' ); ?>
											<button type="submit" name="imgseo_refresh_sitemap" class="button button-primary button-large">
												<?php esc_html_e( 'REFRESH', 'imgseo' ); ?>
											</button>
										</form>
										<p class="description" style="display: inline-block; margin-left: 10px; vertical-align: top; margin-top: 8px;">
											<?php esc_html_e( 'Update sitemap content and refresh URL rules.', 'imgseo' ); ?>
										</p>
									<?php endif; ?>
								</div>
								
								<?php if ( $last_generated > 0 ) : ?>
									<p class="description">
										<strong><?php esc_html_e( 'Status:', 'imgseo' ); ?></strong>
										<?php
										printf(
											/* translators: %s: formatted date and time */
											esc_html__( 'Last updated: %s', 'imgseo' ),
											date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_generated )
										);
										?>
										<?php if ( $auto_refresh_enabled ) : ?>
											<br><em><?php printf( esc_html__( 'Auto-refresh: %s', 'imgseo' ), esc_html( ucfirst( $auto_refresh_interval ) ) ); ?></em>
										<?php endif; ?>
									</p>
								<?php endif; ?>
							<?php else : ?>
								<p>
									<?php esc_html_e( 'To manage the sitemap, first enable the "Enable Image Sitemap" option in the settings section above.', 'imgseo' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>