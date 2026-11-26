<?php
/**
 * Plugin Name:       QuickPick
 * Plugin URI:        https://wordpress.org/plugins/quickpick
 * Description:       Jump back to recently edited posts and pages from the admin list screens or the toolbar.
 * Version:           1.0.8
 * Author:            Alexei Samarschi
 * Author URI:        https://profiles.wordpress.org/alexus450/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quickpick
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      5.6
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

if ( ! defined( 'QUICKPICK_PLUGIN_FILE' ) ) {
	define( 'QUICKPICK_PLUGIN_FILE', __FILE__ );
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
		 * Recent-item query cache for the current request.
		 *
		 * @var array
		 */
		private $recent_items_cache = array();

		/**
		 * Whether the current WP_Query is QuickPick's only-mine filter.
		 *
		 * @var bool
		 */
		private $filtering_only_mine = false;

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

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_views_filters' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar' ), 80 );
		add_filter( 'plugin_action_links_' . plugin_basename( QUICKPICK_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		// Add "Set as Homepage" feature
		add_filter( 'page_row_actions', array( $this, 'filter_admin_row_actions' ), 11, 2 );
		add_action( 'wp_ajax_quickpick_set_homepage', array( $this, 'set_page_as_homepage' ) );
		add_action( 'admin_notices', array( $this, 'render_success_notice' ) );
		add_action( 'save_post', array( $this, 'record_last_editor' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'filter_only_mine_clauses' ), 10, 2 );

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
		if ( '' === trim( $homepage_title ) ) {
			$homepage_title = __( '(no title)', 'quickpick' );
		}
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
				<button type="button" class="qp-button" aria-expanded="false" aria-haspopup="true" aria-controls="%1$s">%2$s</button>
				<ul class="qp-menu" id="%1$s" hidden>%3$s</ul>
			</div>',
			esc_attr( $menu_id ),
			esc_html__( 'QuickPick', 'quickpick' ),
			$content
		);

		return $views;
	}

		/**
		 * Get recently modified items for a post type.
		 *
		 * @since 1.0.0
		 * @param string $post_type Post type slug.
		 * @return string
		 */
	public function last_updated_items( $post_type ) {
		$posts = $this->query_recent_items( $post_type );
		$out   = '<ul>';
		$rows  = '';

		foreach ( $posts as $post ) {
			$item = $this->get_item_display( $post );
			if ( empty( $item['edit_link'] ) ) {
				continue;
			}

			$status_html = '';
			if ( ! empty( $item['status_label'] ) ) {
				$status_html = sprintf(
					' <span class="qp-status qp-status-%1$s">%2$s</span>',
					esc_attr( sanitize_html_class( $post->post_status ) ),
					esc_html( $item['status_label'] )
				);
			}

			$view_html = '';
			if ( ! empty( $item['view_link'] ) ) {
				$view_html = sprintf(
					'<a class="qp-item-view" href="%1$s">%2$s</a>',
					esc_url( $item['view_link'] ),
					esc_html( $item['view_label'] )
				);
			}

			$rows .= sprintf(
				'<li class="qp-item"><a class="qp-item-edit" href="%1$s"><span class="qp-item-title">%2$s%3$s</span><span class="qp-item-meta">%4$s %5$s %6$s %7$s</span></a>%8$s</li>',
				esc_url( $item['edit_link'] ),
				esc_html( $item['title'] ),
				$status_html,
				esc_html__( 'edited:', 'quickpick' ),
				esc_html( $item['date'] ),
				esc_html__( 'at', 'quickpick' ),
				esc_html( $item['time'] ),
				$view_html
			);
		}

		if ( '' === $rows ) {
			$out .= '<li class="qp-empty">' . esc_html__( 'No recent content found.', 'quickpick' ) . '</li>';
		} else {
			$out .= $rows;
		}

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

		if ( 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
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

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action', 'quickpick' ) ) );
		}

		$page_id = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Only a published page can be set as the homepage.', 'quickpick' ) ) );
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		wp_send_json_success( array( 'message' => esc_html__( 'Page set as homepage successfully', 'quickpick' ) ) );

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
			$is_edit_screen     = $screen && 0 === strpos( $screen->id, 'edit-' );
			$is_settings_screen = $screen && 'settings_page_quickpick-settings' === $screen->id;

			$load_admin_bar_css = is_admin_bar_showing() && $this->is_admin_bar_menu_enabled();
			if ( ! $is_edit_screen && ! $is_settings_screen && ! $load_admin_bar_css ) {
				return;
			}

			$this->enqueue_plugin_style();

			if ( ! $is_edit_screen ) {
				return;
			}

			wp_enqueue_script(
				'quickpick-homepage-js',
				plugins_url( '/assets/js/quickpick-homepage.js', QUICKPICK_PLUGIN_FILE ),
				array(),
				QUICKPICK_VERSION,
				true
			);

			wp_localize_script(
				'quickpick-homepage-js',
				'QuickPickHomepage',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'successRedirect'  => wp_nonce_url(
						add_query_arg( 'quickpick_homepage_set', '1', admin_url( 'edit.php?post_type=page' ) ),
						'quickpick_homepage_set'
					),
					'settingText'      => esc_html__( 'Setting...', 'quickpick' ),
					'successText'      => esc_html__( 'Homepage updated successfully.', 'quickpick' ),
					'errorGenericText' => esc_html__( 'An error occurred. Please try again.', 'quickpick' ),
				)
			);

		}

		/**
		 * Load styles on the front end when the admin bar is visible.
		 *
		 * @since 1.0.7
		 * @return void
		 */
		public function enqueue_front_assets() {
			if ( ! is_admin_bar_showing() || ! $this->is_admin_bar_menu_enabled() ) {
				return;
			}
			if ( empty( $this->get_user_accessible_enabled_types() ) ) {
				return;
			}
			$this->enqueue_plugin_style();
		}

		/**
		 * Add style to the quickpick button and dropdown block
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function render_success_notice() {
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'quickpick_homepage_set' ) ) {
				return;
			}

			if ( ! isset( $_GET['quickpick_homepage_set'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['quickpick_homepage_set'] ) ) ) {
				return;
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Homepage updated successfully.', 'quickpick' ) . '</p></div>';
		}

		/**
		 * Add a Settings link on the Plugins screen.
		 *
		 * @since 1.0.7
		 * @param array $links Plugin action links.
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=quickpick-settings' ) ),
				esc_html__( 'Settings', 'quickpick' )
			);
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Add QuickPick to the admin bar.
		 *
		 * @since 1.0.7
		 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
		 * @return void
		 */
		public function register_admin_bar( $wp_admin_bar ) {
			if ( ! is_admin_bar_showing() || ! $this->is_admin_bar_menu_enabled() ) {
				return;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				return;
			}

			$post_types = $this->get_user_accessible_enabled_types();
			if ( empty( $post_types ) ) {
				return;
			}

			$icon_url = plugins_url( '/assets/images/quickpick.png', QUICKPICK_PLUGIN_FILE );
			$parent_href = current_user_can( 'manage_options' )
				? admin_url( 'options-general.php?page=quickpick-settings' )
				: false;

			$wp_admin_bar->add_node(
				array(
					'id'    => 'quickpick',
					'title' => sprintf(
						'<img class="qp-ab-logo" src="%s" alt="" width="18" height="18" decoding="async" /><span class="ab-label">%s</span>',
						esc_url( $icon_url ),
						esc_html__( 'QuickPick', 'quickpick' )
					),
					'href'  => $parent_href,
					'meta'  => array(
						'class' => 'menupop qp-admin-bar',
					),
				)
			);

			if ( in_array( 'page', $post_types, true ) ) {
				$homepage_id   = get_option( 'page_on_front' );
				$show_on_front = get_option( 'show_on_front' );
				if ( 'page' === $show_on_front && ! empty( $homepage_id ) ) {
					$home_link  = get_edit_post_link( $homepage_id );
					$home_title = get_the_title( $homepage_id );
					if ( '' === trim( $home_title ) ) {
						$home_title = __( '(no title)', 'quickpick' );
					}
					if ( ! empty( $home_link ) ) {
						$wp_admin_bar->add_node(
							array(
								'id'     => 'quickpick-homepage',
								'parent' => 'quickpick',
								'title'  => sprintf(
									'%s <span class="qp-ab-status">%s</span>',
									esc_html( $home_title ),
									esc_html__( 'Homepage', 'quickpick' )
								),
								'href'   => $home_link,
							)
						);
					}
				}
			}

			$posts = $this->query_recent_items( $post_types );
			if ( empty( $posts ) ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'quickpick-empty',
						'parent' => 'quickpick',
						'title'  => esc_html__( 'No recent content found.', 'quickpick' ),
						'href'   => false,
					)
				);
				return;
			}

			foreach ( $posts as $post ) {
				$item = $this->get_item_display( $post );
				if ( empty( $item['edit_link'] ) ) {
					continue;
				}

				$status_html = '';
				if ( ! empty( $item['status_label'] ) ) {
					$status_html = sprintf(
						' <span class="qp-ab-status">%s</span>',
						esc_html( $item['status_label'] )
					);
				}

				$wp_admin_bar->add_node(
					array(
						'id'     => 'quickpick-item-' . absint( $post->ID ),
						'parent' => 'quickpick',
						'title'  => sprintf(
							'<span class="qp-ab-title">%1$s%2$s</span><span class="qp-ab-meta">%3$s</span>',
							esc_html( $item['title'] ),
							$status_html,
							esc_html( $item['date'] )
						),
						'href'   => $item['edit_link'],
					)
				);
			}
		}

		public function register_settings() {
			register_setting( 'quickpick_settings_group', 'quickpick_items_limit', array( $this, 'sanitize_items_limit' ) );
			register_setting(
				'quickpick_settings_group',
				'quickpick_only_mine',
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
					'default'           => 0,
				)
			);
			register_setting(
				'quickpick_settings_group',
				'quickpick_show_admin_bar',
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
					'default'           => 0,
				)
			);
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
			$icon_url   = plugins_url( '/assets/images/quickpick.png', QUICKPICK_PLUGIN_FILE );
			?>
			<div class="wrap qp-settings">
				<header class="qp-settings-header">
					<div class="qp-settings-mark" aria-hidden="true">
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="" width="48" height="48" />
					</div>
					<div class="qp-settings-heading">
						<h1><?php esc_html_e( 'QuickPick Settings', 'quickpick' ); ?></h1>
						<p class="qp-settings-lede"><?php esc_html_e( 'Jump back to what you were just editing. These options control the list-screen dropdown and the admin toolbar menu.', 'quickpick' ); ?></p>
					</div>
				</header>
				<form class="qp-settings-form" method="post" action="options.php">
					<?php settings_fields( 'quickpick_settings_group' ); ?>

					<section class="qp-field-group">
						<h2><?php esc_html_e( 'List size', 'quickpick' ); ?></h2>
						<p class="qp-field-help"><?php esc_html_e( 'How many recent items to show, from 1 to 20.', 'quickpick' ); ?></p>
						<label class="qp-number-field" for="quickpick_items_limit">
							<input id="quickpick_items_limit" name="quickpick_items_limit" type="number" min="1" max="20" value="<?php echo esc_attr( absint( get_option( 'quickpick_items_limit', 5 ) ) ); ?>" aria-label="<?php echo esc_attr__( 'Number of items', 'quickpick' ); ?>" />
							<span aria-hidden="true"><?php esc_html_e( 'items', 'quickpick' ); ?></span>
						</label>
					</section>

					<section class="qp-field-group">
						<h2><?php esc_html_e( 'Whose content', 'quickpick' ); ?></h2>
						<p class="qp-field-help"><?php esc_html_e( 'Filters by the last person who saved the item, not the original author.', 'quickpick' ); ?></p>
						<label class="qp-check">
							<input type="hidden" name="quickpick_only_mine" value="0" />
							<input name="quickpick_only_mine" type="checkbox" value="1" <?php checked( 1, absint( get_option( 'quickpick_only_mine', 0 ) ) ); ?> />
							<span><?php esc_html_e( 'Only show items I last saved', 'quickpick' ); ?></span>
						</label>
					</section>

					<section class="qp-field-group">
						<h2><?php esc_html_e( 'Where it appears', 'quickpick' ); ?></h2>
						<p class="qp-field-help"><?php esc_html_e( 'The dropdown appears on each selected list screen. The toolbar menu is available from any screen when it is turned on.', 'quickpick' ); ?></p>
						<label class="qp-check qp-check-toolbar">
							<input type="hidden" name="quickpick_show_admin_bar" value="0" />
							<input name="quickpick_show_admin_bar" type="checkbox" value="1" <?php checked( 1, absint( get_option( 'quickpick_show_admin_bar', 0 ) ) ); ?> />
							<span><?php esc_html_e( 'Show QuickPick in the admin toolbar', 'quickpick' ); ?></span>
						</label>
						<?php if ( ! empty( $post_types ) ) : ?>
							<div class="qp-type-list">
								<?php foreach ( $post_types as $post_type ) : ?>
									<label class="qp-type">
										<input type="checkbox" name="quickpick_enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled, true ) ); ?> />
										<span><?php echo esc_html( $post_type->labels->name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="qp-empty-types"><?php esc_html_e( 'No accessible post types are available for QuickPick.', 'quickpick' ); ?></p>
						<?php endif; ?>
						<p class="qp-field-note"><?php esc_html_e( 'If you uncheck every type, QuickPick falls back to Posts and Pages.', 'quickpick' ); ?></p>
					</section>

					<div class="qp-settings-actions">
						<?php submit_button( __( 'Save changes', 'quickpick' ), 'primary', 'submit', false ); ?>
					</div>
				</form>
				<footer class="qp-settings-support">
					<p>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: PayPal link */
								__( 'If QuickPick saves you time, you can support it on %s.', 'quickpick' ),
								'<a href="https://paypal.me/gt330/10usd" target="_blank" rel="noopener noreferrer">' . esc_html__( 'PayPal', 'quickpick' ) . '</a>'
							),
							array(
								'a' => array(
									'href'   => array(),
									'target' => array(),
									'rel'    => array(),
								),
							)
						);
						?>
					</p>
				</footer>
			</div>
			<?php
		}

		/**
		 * Query recent editable items.
		 *
		 * @since 1.0.7
		 * @param string|array $post_types Post type slug or list of slugs.
		 * @return WP_Post[]
		 */
		private function query_recent_items( $post_types ) {
			$limit = absint( get_option( 'quickpick_items_limit', 5 ) );
			if ( $limit < 1 ) {
				$limit = 5;
			}

			$cache_key = md5(
				wp_json_encode(
					array(
						$post_types,
						get_current_user_id(),
						absint( get_option( 'quickpick_only_mine', 0 ) ),
						$limit,
					)
				)
			);

			if ( isset( $this->recent_items_cache[ $cache_key ] ) ) {
				return $this->recent_items_cache[ $cache_key ];
			}

			$args = array(
				'post_type'              => $post_types,
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
				$this->filtering_only_mine = true;
			}

			$query = new WP_Query( $args );
			$this->filtering_only_mine               = false;
			$this->recent_items_cache[ $cache_key ] = $query->posts;
			return $this->recent_items_cache[ $cache_key ];
		}

		/**
		 * Store the current user as last editor.
		 *
		 * WordPress 7 no longer writes `_edit_last` on a normal save.
		 *
		 * @since 1.0.8
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 * @return void
		 */
		public function record_last_editor( $post_id, $post ) {
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( $user_id < 1 ) {
				return;
			}

			update_post_meta( $post_id, '_edit_last', $user_id );
		}

		/**
		 * Restrict recent-item queries to the current user's last saves.
		 *
		 * Uses `_edit_last` when present, otherwise the latest revision author,
		 * otherwise the original author. Core stopped writing `_edit_last` on save.
		 *
		 * @since 1.0.8
		 * @param array    $clauses Query clauses.
		 * @param WP_Query $query  Query object.
		 * @return array
		 */
		public function filter_only_mine_clauses( $clauses, $query ) {
			if ( ! $this->filtering_only_mine ) {
				return $clauses;
			}

			global $wpdb;

			$user_id = get_current_user_id();
			if ( $user_id < 1 ) {
				return $clauses;
			}

			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS qp_edit_last ON (qp_edit_last.post_id = {$wpdb->posts}.ID AND qp_edit_last.meta_key = '_edit_last') ";
			$clauses['where'] .= $wpdb->prepare(
				" AND CAST(COALESCE(qp_edit_last.meta_value, (SELECT qp_rev.post_author FROM {$wpdb->posts} AS qp_rev WHERE qp_rev.post_parent = {$wpdb->posts}.ID AND qp_rev.post_type = 'revision' ORDER BY qp_rev.ID DESC LIMIT 1), {$wpdb->posts}.post_author) AS UNSIGNED) = %d ",
				$user_id
			);

			return $clauses;
		}

		/**
		 * Display data for a recent item.
		 *
		 * @since 1.0.7
		 * @param WP_Post $post Post object.
		 * @return array
		 */
		private function get_item_display( $post ) {
			$title = get_the_title( $post );
			if ( '' === trim( $title ) ) {
				$title = __( '(no title)', 'quickpick' );
			}

			$status        = get_post_status( $post );
			$status_object = get_post_status_object( $status );
			$status_label  = '';
			if ( $status_object && 'publish' !== $status ) {
				$status_label = $status_object->label;
			}

			$view_link  = '';
			$view_label = '';
			if ( current_user_can( 'read_post', $post->ID ) ) {
				if ( 'publish' === $status ) {
					$view_link  = get_permalink( $post );
					$view_label = __( 'View', 'quickpick' );
				} else {
					$view_link  = get_preview_post_link( $post );
					$view_label = __( 'Preview', 'quickpick' );
				}
			}

			return array(
				'title'        => $title,
				'status_label' => $status_label,
				'date'         => get_the_modified_date( get_option( 'date_format' ), $post ),
				'time'         => get_the_modified_time( get_option( 'time_format' ), $post ),
				'edit_link'    => get_edit_post_link( $post->ID ),
				'view_link'    => $view_link,
				'view_label'   => $view_label,
			);
		}

		/**
		 * Whether the admin toolbar menu is enabled.
		 *
		 * @since 1.0.7
		 * @return bool
		 */
		private function is_admin_bar_menu_enabled() {
			return (bool) absint( get_option( 'quickpick_show_admin_bar', 0 ) );
		}

		private function enqueue_plugin_style() {
			wp_enqueue_style(
				'quickpick-homepage',
				plugins_url( '/assets/css/quickpick-homepage.css', QUICKPICK_PLUGIN_FILE ),
				array(),
				QUICKPICK_VERSION
			);
		}

		private function get_enabled_post_types() {
			$default = array( 'post', 'page' );
			$types   = get_option( 'quickpick_enabled_post_types', $default );
			return $this->sanitize_post_types( $types );
		}

		/**
		 * Enabled post types the current user can edit.
		 *
		 * @since 1.0.7
		 * @return array
		 */
		private function get_user_accessible_enabled_types() {
			$accessible = array();
			foreach ( $this->get_enabled_post_types() as $post_type ) {
				$post_type_obj = get_post_type_object( $post_type );
				if ( ! $post_type_obj || empty( $post_type_obj->cap->edit_posts ) ) {
					continue;
				}
				if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
					continue;
				}
				$accessible[] = $post_type;
			}
			return $accessible;
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
