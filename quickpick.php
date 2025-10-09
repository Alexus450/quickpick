<?php
/**
 * Plugin Name:       QuickPick
 * Plugin URI:        https://wordpress.org/plugins/quickpick
 * Description:       QuickPick is a tiny WordPress plugin that will help you to save time on finding just recently editing posts.
 * Version:           1.0.3
 * Author:            Alexei Samarschi
 * Author URI:        https://profiles.wordpress.org/alexus450/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quickpick
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      5.6
 * Tested up to:      6.8
 * Update URI:        https://wordpress.org/plugins/quickpick/
 */

//Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) 
	exit;

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
		
		add_filter( 'views_edit-post', array( $this, 'quickpick_button_posts' ), 99 );
		add_filter( 'views_edit-page', array( $this, 'quickpick_button_pages' ), 99 );

		add_action( 'admin_head', array( $this, 'quickpick_css') );
		
		// Add "Set as Homepage" feature
		add_filter( 'page_row_actions', array( $this, 'filter_admin_row_actions' ), 11, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
		add_action( 'wp_ajax_quickpick_set_homepage', array( $this, 'set_page_as_homepage' ) );

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
		$out = esc_html__( 'No Homepage', 'quickpick' ); 
		if( empty( $homepage_id ) ) {
			return $out;
		}
		$homepage_link = get_edit_post_link( $homepage_id );
		$homepage_title = get_the_title( $homepage_id );
		$out = sprintf( '<a href="%s">%s</a>', $homepage_link, $homepage_title );

		return $out;

	}

		/**
		 * Add post's list to the QuickPick
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function quickpick_button_posts( $views ) {

			if( ! current_user_can( 'edit_posts' ) ) {
				return $views;
			}

			$out = '<label class="qp-dropdown">
						<div class="qp-button">QuikPick</div>
						<input type="checkbox" class="qp-input" id="quickpick-input">
						<ul class="qp-menu">
							<li>' . $this->last_updated_posts() . '</li>
							<li class="divider"></li>
						</ul>
						</label>';

			$views['quickpick'] = $out;

			return $views;
		
		}

		/**
		 * Add page's list to the QuickPick
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function quickpick_button_pages( $views ) {

			if( ! current_user_can( 'edit_pages' ) ) {
				return $views;
			}

		$desc = '<small>' . esc_html__( 'this page is set as homepage', 'quickpick' ) . '</small>';
		$homepage_id = get_option( 'page_on_front' );
		if( empty( $homepage_id ) ) {
			$desc = '';
		}

		$out = '<label class="qp-dropdown">
					<div class="qp-button">QuikPick</div>
					<input type="checkbox" class="qp-input" id="quickpick-input">
					<ul class="qp-menu">
						<li class="homepage-link">' . $this->get_homepage_edit_link() . $desc . '</li>
							<li>' . $this->last_updated_pages() . '</li>
							<li class="divider"></li>
						</ul>
						</label>';

			$views['quickpick'] = $out;

			return $views;
		
		}

		/**
		 * Get 5 last modified/edited posts
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function last_updated_posts() { 

			// Query Arguments
			$args = array(
				'orderby'             => 'modified',
				'ignore_sticky_posts' => '1',
				'posts_per_page'      => '5'
			);
			 
			//Loop to display 5 recently updated posts
			$query = new WP_Query( $args );
			
			$out = '<ul>';	

			while( $query->have_posts() ) : $query->the_post();

				$out .= '<li>
							<a href="' . get_edit_post_link( $query->post->ID ) . '"> 
								' . get_the_title( $query->post->ID ) . '  <span>' . esc_html__( 'edited:', 'quickpick' ) . ' ' . get_the_modified_date() . ' ' . esc_html__( 'at', 'quickpick' ) . ' ' . get_the_modified_time() . '</span>
							</a>
						</li>';
			endwhile; 

			$out .= '</ul>';
			return $out;
			
			wp_reset_postdata(); 

		}

		/**
		 * Get 5 last modified/edited pages
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function last_updated_pages() { 

			// Query Arguments
			$args = array(
				'post_type'      => 'page',
				'orderby'        => 'modified',
				'posts_per_page' => '5'
			);
			 
			//Loop to display 5 recently updated pages
			$query = new WP_Query( $args );

			$out = '<ul>';	

			while( $query->have_posts() ) : $query->the_post();

				$out .= '<li>
							<a href="' . get_edit_post_link( $query->post->ID ) . '"> 
								' . get_the_title( $query->post->ID ) . '  <span>' . esc_html__( 'edited:', 'quickpick' ) . ' ' . get_the_modified_date() . ' ' . esc_html__( 'at', 'quickpick' ) . ' ' . get_the_modified_time() . '</span>
							</a>
						</li>';
			endwhile; 

			$out .= '</ul>';
			return $out;
			
			wp_reset_postdata(); 

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
			'<a class="quickpick-set-homepage" href="#" data-page-id="%1$d" data-nonce="%2$s" title="%3$s" aria-label="%3$s"><span class="dashicons dashicons-admin-home"></span></a>',
			$post->ID,
			wp_create_nonce( 'quickpick-set-homepage' ),
			esc_attr__( 'Set as Homepage', 'quickpick' )
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

		if( ! wp_verify_nonce( $_POST['nonce'], 'quickpick-set-homepage' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'quickpick' ) ) );
				return;
			}

			if( isset( $_POST['page_id'] ) ) {
				$page_id = absint( $_POST['page_id'] );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid page ID', 'quickpick' ) ) );
				return;
			}

			if( !current_user_can( 'edit_pages' ) ) {
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
		public function enqueue_scripts() {
			
			// Only enqueue on pages list screen
			$screen = get_current_screen();
			if ( ! $screen || 'edit-page' !== $screen->id ) {
				return;
			}

		// Enqueue CSS
		wp_enqueue_style( 
			'quickpick-homepage', 
			plugins_url( '/assets/css/quickpick-homepage.css', __FILE__ ), 
			array(),
			'1.0.3'
		);

		// Enqueue JS
		wp_enqueue_script( 
			'quickpick-homepage-js', 
			plugins_url( '/assets/js/quickpick-homepage.js', __FILE__ ), 
			array( 'jquery' ), 
			'1.0.3',
			true 
		);

		wp_localize_script(
			'quickpick-homepage-js',
			'QuickPickHomepage',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => wp_create_nonce( 'quickpick-set-homepage' ),
			)
		);

		}

		/**
		 * Add style to the quickpick button and dropdown block
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function quickpick_css() {
			
			$assets_url = plugins_url( '/assets', __FILE__ );

			echo "<style type='text/css'>
						.qp-dropdown {
							display: inline-block;
							position: relative;							
						}
						.qp-button {
							display: inline-block;
							border-radius: 5px;
							padding: 5px 30px 5px 15px;
							background-color: #ffffff;
							cursor: pointer;
							white-space: nowrap;
							margin-left:5px;
							background-image:url( {$assets_url}/images/quickpick.png );
							background-position:10px center;
							background-repeat:no-repeat;
							background-size:20px;
							padding-left:35px;

						}
						.qp-button:after {
							content: '';
							position: absolute;
							top: 50%;
							right: 10px;
							transform: translateY(-50%);
							width: 0; 
							height: 0; 
							border-left: 5px solid transparent;
							border-right: 5px solid transparent;
							border-top: 5px solid black;
						}
						.qp-button:hover:after {
							border-top-color:#fff;
						}
						.qp-button:hover {
							background-color: #444857;
							color:#fff;
						}
						.qp-input {
							display: none !important;
						}
						.qp-menu {
							position: absolute;
							top: 100%;
							border-radius: 5px;
							padding: 0;
							margin: 2px 0 0 0;
							box-shadow: 1px 2px 5px 1px rgba(178.5, 178.5, 178.5, 0.5607843137254902);
							background-color: #ffffff;
							list-style-type: none;
						}
						.qp-input + .qp-menu {
							display: none;
						} 
						.qp-input:checked + .qp-menu {
							display: block;
						} 
						.qp-menu li {
							white-space: nowrap;
							display:block;
						}
						.qp-menu li.homepage-link {
							padding:15px 20px;
							font-weight:bold;
						}
						.qp-menu li.homepage-link small {
							font-weight:normal;
							padding-left:.2em;
						}
						.qp-menu li.homepage-link a {
							padding-bottom:0px;
							line-height:15px;
						}
						.qp-menu li a {
							display: block;
						}
						.qp-menu li.divider{
							padding: 0;
							border-bottom: 1px solid #cccccc;
						}
						.qp-menu li ul li {
							display:block;
							padding: 13px 20px;
						}
						.qp-menu li ul > :nth-child(2n+1) {
							background-color:#f6f7f7;
						}
						.qp-menu li ul li a {
							display:block;
							margin:0px;
							padding:0px;
							line-height:1.45em;
						}
						.qp-menu li ul li a > span {
							color:#50575e;
							display:block;
						}
					</style>";

		}

	}
	
	add_action( 'init', 'QuickPick::instance' );

}