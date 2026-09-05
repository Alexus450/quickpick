<?php
/**
 * Remove QuickPick options when the plugin is deleted.
 *
 * @package QuickPick
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete stored QuickPick options for the current site.
 *
 * @return void
 */
function quickpick_delete_options() {
	delete_option( 'quickpick_items_limit' );
	delete_option( 'quickpick_only_mine' );
	delete_option( 'quickpick_show_admin_bar' );
	delete_option( 'quickpick_enabled_post_types' );
}

/**
 * Run uninstall cleanup for every site on the network, or the current site.
 *
 * @return void
 */
function quickpick_uninstall() {
	if ( ! is_multisite() ) {
		quickpick_delete_options();
		return;
	}

	$quickpick_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $quickpick_site_ids as $quickpick_site_id ) {
		switch_to_blog( $quickpick_site_id );
		quickpick_delete_options();
		restore_current_blog();
	}
}

quickpick_uninstall();
