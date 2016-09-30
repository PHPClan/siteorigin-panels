<?php
/*
Plugin Name: Page Builder by SiteOrigin
Plugin URI: https://siteorigin.com/page-builder/
Description: A drag and drop, responsive page builder that simplifies building your website.
Version: dev
Author: SiteOrigin
Author URI: https://siteorigin.com
License: GPL3
License URI: http://www.gnu.org/licenses/gpl.html
Donate link: http://siteorigin.com/page-builder/#donate
*/

define('SITEORIGIN_PANELS_VERSION', 'dev');
if ( ! defined('SITEORIGIN_PANELS_JS_SUFFIX' ) ) {
	define('SITEORIGIN_PANELS_JS_SUFFIX', '');
}
define('SITEORIGIN_PANELS_VERSION_SUFFIX', '');
define('SITEORIGIN_PANELS_BASE_FILE', __FILE__);

// All the basic settings
require_once plugin_dir_path(__FILE__) . 'settings/settings.php';

// Include all the basic widgets
require_once plugin_dir_path(__FILE__) . 'widgets/basic.php';

require_once plugin_dir_path(__FILE__) . 'inc/revisions.php';
require_once plugin_dir_path(__FILE__) . 'inc/styles.php';
require_once plugin_dir_path(__FILE__) . 'inc/default-styles.php';
require_once plugin_dir_path(__FILE__) . 'inc/widgets.php';
require_once plugin_dir_path(__FILE__) . 'inc/plugin-activation.php';


class SiteOrigin_Panels {

	function __construct() {
		// Register the autoloader
		spl_autoload_register ( array( $this, 'autoloader' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );

		// This is the main filter
		add_filter( 'the_content', array( $this, 'filter_content' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );

		add_filter( 'siteorigin_panels_data', array( $this, 'process_panels_data' ), 5 );
	}

	public static function single() {
		static $single;
		if( empty( $single ) ) {
			$single = new self();
		}

		return $single;
	}

	public static function autoloader( $class ){

	}

	public function activate(){
		add_option('siteorigin_panels_initial_version', SITEORIGIN_PANELS_VERSION, '', 'no');
	}

	/**
	 * Initialize SiteOrigin Page Builder
	 *
	 * @action plugins_loaded
	 */
	public function init(){
		if(
			! is_admin() &&
			siteorigin_panels_setting( 'sidebars-emulator' ) &&
			( ! get_option('permalink_structure') || get_option('rewrite_rules') )
		) {
			// Include the sidebars emulator
			require_once plugin_dir_path(__FILE__) . 'inc/sidebars-emulator.php';
		}

		// Initialize the language
		load_plugin_textdomain('siteorigin-panels', false, dirname( plugin_basename( __FILE__ ) ). '/lang/');

		// Initialize all the extra classes
		SiteOrigin_Panels_Home::single();

		// Check if we need to initialize the admin class.
		if( is_admin() || is_preview() ) {
			SiteOrigin_Panels_Admin::single();
		}
	}

	/**
	 * @return mixed|void Are we currently viewing the home page
	 */
	public static function is_home(){
		$home = ( is_front_page() && is_page() && get_option('show_on_front') == 'page' && get_option('page_on_front') == get_the_ID() && get_post_meta( get_the_ID(), 'panels_data' ) );
		return apply_filters('siteorigin_panels_is_home', $home);
	}

	/**
	 * Check if we're currently viewing a page builder page.
	 *
	 * @param bool $can_edit Also check if the user can edit this page
	 * @return bool
	 */
	public static function is_panel( $can_edit = false ) {
		// Check if this is a panel
		$is_panel =  ( siteorigin_panels_is_home() || ( is_singular() && get_post_meta(get_the_ID(), 'panels_data', false) ) );
		return $is_panel && ( ! $can_edit || ( ( is_singular() && current_user_can( 'edit_post', get_the_ID() ) ) || ( siteorigin_panels_is_home() && current_user_can('edit_theme_options') ) ) );
	}

	/**
	 * @todo Check if this is used anywhere. It doesn't seem to be.
	 */
	public static function is_preview( ){

	}

	public static function preview_url(){
		global $post, $wp_post_types;

		if(
			empty( $post ) ||
			empty( $wp_post_types ) ||
			empty( $wp_post_types[ $post->post_type ] ) ||
			!$wp_post_types[ $post->post_type ]->public
		) {
			$preview_url = add_query_arg(
				'siteorigin_panels_live_editor',
				'true',
				admin_url( 'admin-ajax.php?action=so_panels_live_editor_preview' )
			);
			$preview_url = wp_nonce_url( $preview_url, 'live-editor-preview', '_panelsnonce' );
		}
		else {
			$preview_url = add_query_arg( 'siteorigin_panels_live_editor', 'true', set_url_scheme( get_permalink() ) );
		}

		return $preview_url;
	}

	/**
	 * Get the Page Builder data for the home page.
	 *
	 * @return bool|mixed
	 */
	public function get_home_page_data(){
		$page_id = get_option( 'page_on_front' );
		if( empty($page_id) ) $page_id = get_option( 'siteorigin_panels_home_page_id' );
		if( empty($page_id) ) return false;

		$panels_data = get_post_meta( $page_id, 'panels_data', true );
		if( is_null( $panels_data ) ){
			// Load the default layout
			$layouts = apply_filters( 'siteorigin_panels_prebuilt_layouts', array() );
			$panels_data = !empty($layouts['default_home']) ? $layouts['default_home'] : current($layouts);
		}

		return $panels_data;
	}

	/**
	 * Filter the content of the panel, adding all the widgets.
	 *
	 * @param $content
	 * @return string
	 *
	 * @filter the_content
	 */
	function filter_content( $content ) {
		global $post;

		if ( empty( $post ) ) return $content;
		if ( !apply_filters( 'siteorigin_panels_filter_content_enabled', true ) ) return $content;

		// Check if this post has panels_data
		$panels_data = get_post_meta( $post->ID, 'panels_data', true );
		if ( !empty( $panels_data ) ) {
			$panel_content = SiteOrigin_Panels_Renderer::single()->render( $post->ID );

			if ( !empty( $panel_content ) ) {
				$content = $panel_content;

				if( !is_singular() ) {
					// This is an archive page, so try strip out anything after the more text

					if ( preg_match( '/<!--more(.*?)?-->/', $content, $matches ) ) {
						$content = explode( $matches[0], $content, 2 );
						$content = $content[0];
						$content = force_balance_tags( $content );
						if ( ! empty( $matches[1] ) && ! empty( $more_link_text ) ) {
							$more_link_text = strip_tags( wp_kses_no_null( trim( $matches[1] ) ) );
						}
						else {
							$more_link_text = __('Read More', 'siteorigin-panels');
						}

						$more_link = apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-{$post->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
						$content .= '<p>' . $more_link . '</p>';
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Add all the necessary body classes.
	 *
	 * @param $classes
	 * @return array
	 */
	function body_class( $classes ){
		if( self::is_panel() ) $classes[] = 'siteorigin-panels';
		if( self::is_home() ) $classes[] = 'siteorigin-panels-home';

		return $classes;
	}

	/**
	 * Add the Edit Home Page item to the admin bar.
	 *
	 * @param WP_Admin_Bar $admin_bar
	 * @return WP_Admin_Bar
	 */
	function admin_bar_menu( $admin_bar ){
		// Add the edit home page link
		if(
			siteorigin_panels_setting('home-page') &&
			current_user_can('edit_theme_options') &&
			( is_home() ||is_front_page() )
		) {
			if( ( is_page() && get_post_meta( get_the_ID(), 'panels_data', true ) !== '' ) || !is_page() ) {
				$admin_bar->add_node( array(
					'id' => 'edit-home-page',
					'title' => __('Edit Home Page', 'siteorigin-panels'),
					'href' => admin_url('themes.php?page=so_panels_home_page')
				) );

				if( is_page() ) {
					// Remove the standard edit button
					$admin_bar->remove_node('edit');
				}
			}
		}

		// Add a Live Edit link if this is a Page Builder page that the user can edit
		if(
			siteorigin_panels_setting( 'live-editor-quick-link' ) &&
			is_singular() &&
			current_user_can( 'edit_post', get_the_ID() ) &&
			get_post_meta( get_the_ID(), 'panels_data', true )
		) {
			$admin_bar->add_node( array(
				'id'    => 'so_live_editor',
				'title' => __( 'Live Editor', 'siteorigin-panels' ),
				'href'  => add_query_arg( 'so_live_editor', 1, get_edit_post_link( get_the_ID() ) ),
				'meta'  => array(
					'class' => 'live-edit-page'
				)
			) );

			add_action( 'wp_enqueue_scripts', array( $this, 'live_edit_link_style' ) );
		}

		return $admin_bar;
	}

	function live_edit_link_style(){
		if( is_singular() && current_user_can( 'edit_post', get_the_ID() ) && get_post_meta( get_the_ID(), 'panels_data', true ) ) {
			// Add the style for the eye icon before the Live Editor link
			$css = '#wpadminbar #wp-admin-bar-so_live_editor > .ab-item:before {
			    content: "\f177";
			    top: 2px;
			}';
			wp_add_inline_style( 'siteorigin-panels-front', $css );
		}
	}

	/**
	 * Process panels data to make sure everything is properly formatted
	 *
	 * @param array $panels_data
	 *
	 * @return array
	 */
	function process_panels_data( $panels_data ){

		// Process all widgets to make sure that panels_info is properly represented
		if( !empty($panels_data['widgets']) && is_array($panels_data['widgets']) ) {

			$last_gi = 0;
			$last_ci = 0;
			$last_wi = 0;

			foreach( $panels_data['widgets'] as &$widget ) {
				// Transfer legacy content
				if( empty($widget['panels_info']) && !empty($widget['info']) ) {
					$widget['panels_info'] = $widget['info'];
					unset( $widget['info'] );
				}

				// Filter the widgets to add indexes
				if ( $widget['panels_info']['grid'] != $last_gi ) {
					$last_gi = $widget['panels_info']['grid'];
					$last_ci = 0;
					$last_wi = 0;
				}
				elseif ( $widget['panels_info']['cell'] != $last_ci ) {
					$last_ci = $widget['panels_info']['cell'];
					$last_wi = 0;
				}
				$widget['panels_info']['cell_index'] = $last_wi++;
			}

			foreach( $panels_data['grids'] as &$grid ) {
				if( !empty( $grid['style'] ) && is_string( $grid['style'] ) ) {
					$grid['style'] = array(

					);
				}
			}
		}

		// Process the IDs of the grids. Make sure that each is unique.

		if( !empty($panels_data['grids']) && is_array($panels_data['grids']) ) {
			$unique_grid_ids = array();
			foreach( $panels_data['grids'] as &$grid ) {
				// Make sure that the row ID is unique and non-numeric
				if( !empty( $grid['style']['id'] ) ) {
					if( is_numeric($grid['style']['id']) ) {
						// Numeric IDs will cause problems, so we'll ignore them
						$grid['style']['id'] = false;
					}
					else if( isset( $unique_grid_ids[ $grid['style']['id'] ] ) ) {
						// This ID already exists, so add a suffix to make sure it's unique
						$original_id = $grid['style']['id'];
						$i = 1;
						do {
							$grid['style']['id'] = $original_id . '-' . (++$i);
						} while( isset( $unique_grid_ids[ $grid['style']['id'] ] ) );
					}

					if( !empty( $grid['style']['id'] ) ) {
						$unique_grid_ids[ $grid['style']['id'] ] = true;
					}
				}
			}
		}

		return $panels_data;
	}
}
register_activation_hook( __FILE__, array( 'SiteOrigin_Panels', 'activate' ) );


// Include the live editor file if we're in live editor mode.
if( !empty($_GET['siteorigin_panels_live_editor']) ) require_once plugin_dir_path(__FILE__) . 'inc/live-editor.php';
