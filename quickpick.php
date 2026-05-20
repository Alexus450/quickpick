<?php
/**
 * Plugin Name:       QuickPick
 * Plugin URI:        https://wordpress.org/plugins/quickpick
 * Description:       QuickPick is a tiny WordPress plugin that will help you to save time on finding just recently editing posts.
 * Version:           1.0.6
 * Author:            Alexei Samarschi
 * Author URI:        https://profiles.wordpress.org/alexus450/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quickpick
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      5.6
 * Tested up to:      7.0
 * Update URI:        https://wordpress.org/plugins/quickpick/
 */

 // Prevent direct access
 if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
if ( ! defined( 'QUICKPICK_VERSION' ) ) {
	define( 'QUICKPICK_VERSION', get_file_data( __FILE__, [ 'Version' ] )[0] ); // phpcs:ignore
}

if( ! class_exists( 'QuickPick' ) ) {
	/**
	 * The main PHP class for QuickPick plugin.
	 */
	final class QuickPick {

		/** 
		 * This plugin's instance.
		 *
		 * @var QuickPick
		 * @since 1.0.0
		 */
		private static $instance;

		/**
		 * Main Quickpick Instance.
		 *
		 * Insures that only one instance of Quickpick exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 * @static
		 * @return object|QuickPick The one true Quickpick
		 */
		public static function instance() {

			if ( null === self::$instance ) {
				self::$instance = new QuickPick();
			}

			return self::$instance;

		}

	/**
	 * Plugin constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {

		add_action( 'init', array( $this, 'i18n' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_views_filters' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 20 );
		// Add "Set as Homepage" feature
		add_filter( 'page_row_actions', array( $this, 'filter_admin_row_actions' ), 11, 2 );
		add_action( 'wp_ajax_quickpick_set_homepage', array( $this, 'set_page_as_homepage' ) );
		add_action( 'admin_notices', array( $this, 'render_success_notice' ) );

	}

		/**
		 * Load Textdomain
		 *
		 * Load plugin localization files.
		 *
		 * Fired by 'init' action hook.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function i18n() {
			load_plugin_textdomain( 'quickpick', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

	/**
	 * Check if the homepage exists and add it's link to the QuickPick
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_homepage_edit_link() {

		$homepage_id = get_option( 'page_on_front' );
		$show_on_front = get_option( 'show_on_front' );
		$out = esc_html__( 'No Homepage', 'quickpick' ); 
		if ( 'page' !== $show_on_front || empty( $homepage_id ) ) {
			return $out;
		}
		$homepage_link = get_edit_post_link( $homepage_id );
		$homepage_title = get_the_title( $homepage_id );
		$out = sprintf( '<a href="%s">%s</a>', esc_url( $homepage_link ), esc_html( $homepage_title ) );

		return $out;

	}

	public function register_views_filters() {
		foreach ( $this->get_enabled_post_types() as $post_type ) {
			add_filter(
				'views_edit-' . $post_type,
				function( $views ) use ( $post_type ) {
					return $this->quickpick_button_for_post_type( $views, $post_type );
				},
				99
			);
		}
	}

	public function quickpick_button_for_post_type( $views, $post_type ) {
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
			return $views;
		}

		$menu_id = 'quickpick-menu-' . sanitize_html_class( $post_type );
		$content = '';

		if ( 'page' === $post_type ) {
			$desc = '<small>' . esc_html__( 'this page is set as homepage', 'quickpick' ) . '</small>';
			if ( empty( get_option( 'page_on_front' ) ) ) {
				$desc = '';
			}
			$content .= '<li class="homepage-link">' . $this->get_homepage_edit_link() . $desc . '</li>';
			$content .= '<li class="divider"></li>';
		}

		$content .= '<li>' . $this->last_updated_items( $post_type ) . '</li>';

		$views['quickpick'] = sprintf(
			'<div class="qp-dropdown" data-qp-dropdown>
				<button type="button" class="qp-button" aria-expanded="false" aria-controls="%1$s">%2$s</button>
				<ul class="qp-menu" id="%1$s" hidden>%3$s</ul>
			</div>',
			esc_attr( $menu_id ),
			esc_html__( 'QuickPick', 'quickpick' ),
			$content
		);

		return $views;
	}

		/**
		 * Get 5 last modified/edited posts
		 *
		 * @since 1.0.0
		 * @return void
		 */
	public function last_updated_items( $post_type ) {
		$limit = absint( get_option( 'quickpick_items_limit', 5 ) );
		if ( $limit < 1 ) {
			$limit = 5;
		}

		$args = array(
			'post_type'              => $post_type,
			'orderby'                => 'modified',
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'perm'                   => 'editable',
			'ignore_sticky_posts'    => true,
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( get_option( 'quickpick_only_mine', 0 ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );
		$out   = '<ul>';

		if ( ! $query->have_posts() ) {
			$out .= '<li class="qp-empty">' . esc_html__( 'No recent content found.', 'quickpick' ) . '</li>';
		}

		while ( $query->have_posts() ) :
			$query->the_post();
			$edit_link = get_edit_post_link( $query->post->ID );
			if ( empty( $edit_link ) ) {
				continue;
			}
			$out .= sprintf(
				'<li><a href="%1$s">%2$s <span>%3$s %4$s %5$s %6$s</span></a></li>',
				esc_url( $edit_link ),
				esc_html( get_the_title( $query->post->ID ) ),
				esc_html__( 'edited:', 'quickpick' ),
				esc_html( get_the_modified_date( 'Y/m/d', $query->post->ID ) ),
				esc_html__( 'at', 'quickpick' ),
				esc_html( get_the_modified_time( '', $query->post->ID ) )
			);
		endwhile;

		wp_reset_postdata();

		$out .= '</ul>';
		return $out;
	}

		/**
		 * Add/Remove edit link in dashboard.
		 *
		 * Add or remove an edit link to the page action links on the pages list table.
		 *
		 * Fired by 'page_row_actions' filter.
		 *
		 * @since 1.0.3
		 * @access public
		 *
		 * @param array    $actions An array of row action links.
		 * @param WP_Post  $post    The post object.
		 *
		 * @return array An updated array of row action links.
		 */
		public function filter_admin_row_actions( $actions, $post ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		// Make sure the page is published
		if( 'publish' !== $post->post_status ) {
			return $actions;
		}
		
		// Check if the page is not homepage already
		if( 'page' == get_option( 'show_on_front' ) && $post->ID == get_option( 'page_on_front' ) ) {
			return $actions;
		}

		// Add our link with icon
		$actions['quickpick_set_as_homepage'] = sprintf(			
			'<a class="quickpick-set-homepage" href="#" data-page-id="%1$d" data-nonce="%2$s" title="%3$s" aria-label="%3$s"><span class="dashicons dashicons-admin-home"></span><span class="quickpick-label">%4$s</span></a>',
			$post->ID,
			wp_create_nonce( 'quickpick-set-homepage' ),
			esc_attr__( 'Set as Homepage', 'quickpick' ),
			esc_html__( 'Set as Homepage', 'quickpick' )
		);

			return $actions;

		}

	/**
	 * Set page as homepage via AJAX
	 *
	 * @since 1.0.3
	 * @access public
	 *
	 * @return void
	 */
	public function set_page_as_homepage() {

		check_ajax_referer( 'quickpick-set-homepage', 'nonce' );

			if( isset( $_POST['page_id'] ) ) {
				$page_id = absint( wp_unslash( $_POST['page_id'] ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid page ID', 'quickpick' ) ) );
				return;
			}

			if( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action', 'quickpick' ) ) );
				return;
			}

			if( !empty( $page_id ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_id );
				wp_send_json_success( array( 'message' => esc_html__( 'Page set as homepage successfully', 'quickpick' ) ) );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid page ID', 'quickpick' ) ) );
			}

		}

		/**
		 * Enqueue plugin scripts and styles
		 *
		 * @since 1.0.3
		 * @access public
		 *
		 * @return void
		 */
		public function enqueue_admin_assets() {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$is_edit_screen     = 0 === strpos( $screen->id, 'edit-' );
			$is_settings_screen = 'settings_page_quickpick-settings' === $screen->id;
			if ( ! $is_edit_screen && ! $is_settings_screen ) {
				return;
			}

		wp_enqueue_style(
			'quickpick-homepage',
			plugins_url( '/assets/css/quickpick-homepage.css', __FILE__ ),
			array(),
			QUICKPICK_VERSION
		);

		if ( ! $is_edit_screen ) {
			return;
		}

		wp_enqueue_script(
			'quickpick-homepage-js',
			plugins_url( '/assets/js/quickpick-homepage.js', __FILE__ ),
			array( 'jquery' ),
			QUICKPICK_VERSION,
			true
		);

		wp_localize_script(
			'quickpick-homepage-js',
			'QuickPickHomepage',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'successRedirect'  => add_query_arg( 'quickpick_homepage_set', '1', admin_url( 'edit.php?post_type=page' ) ),
				'settingText'      => esc_html__( 'Setting...', 'quickpick' ),
				'successText'      => esc_html__( 'Homepage updated successfully.', 'quickpick' ),
				'errorGenericText' => esc_html__( 'An error occurred. Please try again.', 'quickpick' ),
			)
		);

		}

		/**
		 * Add style to the quickpick button and dropdown block
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function render_success_notice() {
			if ( ! isset( $_GET['quickpick_homepage_set'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['quickpick_homepage_set'] ) ) ) {
				return;
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Homepage updated successfully.', 'quickpick' ) . '</p></div>';
		}

		public function register_settings() {
			register_setting( 'quickpick_settings_group', 'quickpick_items_limit', array( $this, 'sanitize_items_limit' ) );
			register_setting( 'quickpick_settings_group', 'quickpick_only_mine', array( $this, 'sanitize_checkbox' ) );
			register_setting( 'quickpick_settings_group', 'quickpick_enabled_post_types', array( $this, 'sanitize_post_types' ) );
		}

		public function sanitize_items_limit( $value ) {
			$value = absint( $value );
			if ( $value < 1 ) {
				$value = 5;
			}
			if ( $value > 20 ) {
				$value = 20;
			}
			return $value;
		}

		public function sanitize_checkbox( $value ) {
			return empty( $value ) ? 0 : 1;
		}

		public function sanitize_post_types( $types ) {
			$types = is_array( $types ) ? array_map( 'sanitize_key', $types ) : array( 'post', 'page' );
			$allowed = array_keys( $this->get_available_post_types( false ) );
			$types = array_values( array_intersect( $types, $allowed ) );
			return empty( $types ) ? array( 'post', 'page' ) : $types;
		}

		public function register_settings_page() {
			add_options_page(
				esc_html__( 'QuickPick Settings', 'quickpick' ),
				esc_html__( 'QuickPick', 'quickpick' ),
				'manage_options',
				'quickpick-settings',
				array( $this, 'render_settings_page' )
			);
		}

		public function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$post_types = $this->get_available_post_types( true );
			$enabled    = $this->get_enabled_post_types();
			$icon_url   = plugins_url( '/assets/images/quickpick.png', __FILE__ );
			?>
			<div class="wrap">
				<h1 class="qp-settings-title">
					<img src="<?php echo esc_url( $icon_url ); ?>" alt="" width="24" height="24" />
					<?php esc_html_e( 'QuickPick Settings', 'quickpick' ); ?>
				</h1>
				<form method="post" action="options.php">
					<?php settings_fields( 'quickpick_settings_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="quickpick_items_limit"><?php esc_html_e( 'Number of items', 'quickpick' ); ?></label></th>
							<td><input id="quickpick_items_limit" name="quickpick_items_limit" type="number" min="1" max="20" value="<?php echo esc_attr( absint( get_option( 'quickpick_items_limit', 5 ) ) ); ?>" class="small-text" /></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Content scope', 'quickpick' ); ?></th>
							<td><label><input name="quickpick_only_mine" type="checkbox" value="1" <?php checked( 1, absint( get_option( 'quickpick_only_mine', 0 ) ) ); ?> /> <?php esc_html_e( 'Show only content edited by current user', 'quickpick' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enabled post types', 'quickpick' ); ?></th>
							<td>
								<?php if ( ! empty( $post_types ) ) : ?>
									<?php foreach ( $post_types as $post_type ) : ?>
										<label style="display:block;margin-bottom:6px;">
											<input type="checkbox" name="quickpick_enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled, true ) ); ?> />
											<?php echo esc_html( $post_type->labels->name ); ?>
										</label>
									<?php endforeach; ?>
								<?php else : ?>
									<p><?php esc_html_e( 'No accessible post types are available for QuickPick.', 'quickpick' ); ?></p>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Only post types with accessible edit list screens are shown here.', 'quickpick' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
				<hr />
				<p>
					<a class="button button-secondary qp-donate-button" href="https://paypal.me/gt330/10usd" target="_blank" rel="noopener noreferrer">
						<span class="qp-paypal-icon" aria-hidden="true">P</span>
						<span class="qp-paypal-text"><?php esc_html_e( 'PayPal', 'quickpick' ); ?></span>
						<span><?php esc_html_e( 'Support QuickPick with a Donation', 'quickpick' ); ?></span>
					</a>
				</p>
			</div>
			<?php
		}

		private function get_enabled_post_types() {
			$default = array( 'post', 'page' );
			$types   = get_option( 'quickpick_enabled_post_types', $default );
			return $this->sanitize_post_types( $types );
		}

		private function get_available_post_types( $require_current_user_access = false ) {
			$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
			$available  = array();

			foreach ( $post_types as $post_type ) {
				// Exclude post types that do not belong in the standard edit list workflow.
				if ( 'attachment' === $post_type->name || 0 === strpos( $post_type->name, 'wp_' ) ) {
					continue;
				}

				if ( empty( $post_type->cap ) || empty( $post_type->cap->edit_posts ) ) {
					continue;
				}

				if ( $require_current_user_access && ! current_user_can( $post_type->cap->edit_posts ) ) {
					continue;
				}

				$available[ $post_type->name ] = $post_type;
			}

			return $available;
		}

	}
	
	add_action( 'init', 'QuickPick::instance' );

}