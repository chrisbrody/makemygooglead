<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HELPER COMMENT START
 * 
 * This class is used to bring your plugin to life. 
 * All the other registered classed bring features which are
 * controlled and managed by this class.
 * 
 * Within the add_hooks() function, you can register all of 
 * your WordPress related actions and filters as followed:
 * 
 * add_action( 'my_action_hook_to_call', array( $this, 'the_action_hook_callback', 10, 1 ) );
 * or
 * add_filter( 'my_filter_hook_to_call', array( $this, 'the_filter_hook_callback', 10, 1 ) );
 * or
 * add_shortcode( 'my_shortcode_tag', array( $this, 'the_shortcode_callback', 10 ) );
 * 
 * Once added, you can create the callback function, within this class, as followed: 
 * 
 * public function the_action_hook_callback( $some_variable ){}
 * or
 * public function the_filter_hook_callback( $some_variable ){}
 * or
 * public function the_shortcode_callback( $attributes = array(), $content = '' ){}
 * 
 * 
 * HELPER COMMENT END
 */

/**
 * Class Buymyad_Run
 *
 * Thats where we bring the plugin to life
 *
 * @package		BUYMYAD
 * @subpackage	Classes/Buymyad_Run
 * @author		Chris Brody
 * @since		1.0.0
 */
class Buymyad_Run{

	/**
	 * Our Buymyad_Run constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.0
	 */
	function __construct(){
		$this->add_hooks();
	}

	/**
	 * ######################
	 * ###
	 * #### WORDPRESS HOOKS
	 * ###
	 * ######################
	 */

	/**
	 * Registers all WordPress and plugin related hooks
	 *
	 * @access	private
	 * @since	1.0.0
	 * @return	void
	 */
	private function add_hooks(){
	
		add_action( 'plugin_action_links_' . BUYMYAD_PLUGIN_BASE, array( $this, 'add_plugin_action_link' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_backend_scripts_and_styles' ), 20 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_items' ), 100, 1 );
		add_filter( 'edd_settings_sections_extensions', array( $this, 'add_edd_settings_section' ), 20 );
		add_filter( 'edd_settings_extensions', array( $this, 'add_edd_settings_section_content' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'add_wp_webhooks_integrations' ), 9 );
	
	}

	/**
	 * ######################
	 * ###
	 * #### WORDPRESS HOOK CALLBACKS
	 * ###
	 * ######################
	 */

	/**
	* Adds action links to the plugin list table
	*
	* @access	public
	* @since	1.0.0
	*
	* @param	array	$links An array of plugin action links.
	*
	* @return	array	An array of plugin action links.
	*/
	public function add_plugin_action_link( $links ) {

		$links['our_shop'] = sprintf( '<a href="%s" title="Custom Link" style="font-weight:700;">%s</a>', 'https://test.test', __( 'Custom Link', 'buymyad' ) );

		return $links;
	}

	/**
	 * Enqueue the backend related scripts and styles for this plugin.
	 * All of the added scripts andstyles will be available on every page within the backend.
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @return	void
	 */
	public function enqueue_backend_scripts_and_styles() {
		wp_enqueue_style( 'buymyad-backend-styles', BUYMYAD_PLUGIN_URL . 'core/includes/assets/css/backend-styles.css', array(), BUYMYAD_VERSION, 'all' );
		wp_enqueue_script( 'buymyad-backend-scripts', BUYMYAD_PLUGIN_URL . 'core/includes/assets/js/backend-scripts.js', array(), BUYMYAD_VERSION, false );
		wp_localize_script( 'buymyad-backend-scripts', 'buymyad', array(
			'plugin_name'   	=> __( BUYMYAD_NAME, 'buymyad' ),
		));
	}

	/**
	 * Add a new menu item to the WordPress topbar
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @param	object $admin_bar The WP_Admin_Bar object
	 *
	 * @return	void
	 */
	public function add_admin_bar_menu_items( $admin_bar ) {

		$admin_bar->add_menu( array(
			'id'		=> 'buymyad-id', // The ID of the node.
			'title'		=> __( 'Demo Menu Item', 'buymyad' ), // The text that will be visible in the Toolbar. Including html tags is allowed.
			'parent'	=> false, // The ID of the parent node.
			'href'		=> '#', // The ‘href’ attribute for the link. If ‘href’ is not set the node will be a text node.
			'group'		=> false, // This will make the node a group (node) if set to ‘true’. Group nodes are not visible in the Toolbar, but nodes added to it are.
			'meta'		=> array(
				'title'		=> __( 'Demo Menu Item', 'buymyad' ), // The title attribute. Will be set to the link or to a div containing a text node.
				'target'	=> '_blank', // The target attribute for the link. This will only be set if the ‘href’ argument is present.
				'class'		=> 'buymyad-class', // The class attribute for the list item containing the link or text node.
				'html'		=> false, // The html used for the node.
				'rel'		=> false, // The rel attribute.
				'onclick'	=> false, // The onclick attribute for the link. This will only be set if the ‘href’ argument is present.
				'tabindex'	=> false, // The tabindex attribute. Will be set to the link or to a div containing a text node.
			),
		));

		$admin_bar->add_menu( array(
			'id'		=> 'buymyad-sub-id',
			'title'		=> __( 'My sub menu title', 'buymyad' ),
			'parent'	=> 'buymyad-id',
			'href'		=> '#',
			'group'		=> false,
			'meta'		=> array(
				'title'		=> __( 'My sub menu title', 'buymyad' ),
				'target'	=> '_blank',
				'class'		=> 'buymyad-sub-class',
				'html'		=> false,    
				'rel'		=> false,
				'onclick'	=> false,
				'tabindex'	=> false,
			),
		));

	}

	/**
	 * Add the custom settings section under
	 * Downloads -> Settings -> Extensions
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @param	array	$sections	The currently registered EDD settings sections
	 *
	 * @return	void
	 */
	public function add_edd_settings_section( $sections ) {
		
		$sections['buymyad'] = __( BUYMYAD()->settings->get_plugin_name(), 'buymyad' );

		return $sections;
	}

	/**
	 * Add the custom settings section content
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @param	array	$settings	The currently registered EDD settings for all registered extensions
	 *
	 * @return	array	The extended settings 
	 */
	public function add_edd_settings_section_content( $settings ) {
		
		// Your settings reamain registered as they were in EDD Pre-2.5
		$custom_settings = array(
			array(
				'id'   => 'my_header',
				'name' => '<strong>' . __( BUYMYAD()->settings->get_plugin_name() . 'Settings', 'buymyad' ) . '</strong>',
				'desc' => '',
				'type' => 'header',
				'size' => 'regular'
			),
			array(
				'id'    => 'my_example_setting',
				'name'  => __( 'Example checkbox', 'buymyad' ),
				'desc'  => __( 'Check this to turn on a setting', 'buymyad' ),
				'type'  => 'checkbox'
			),
			array(
				'id'    => 'my_example_text',
				'name'  => __( 'Example text', 'buymyad' ),
				'desc'  => __( 'A Text setting', 'buymyad' ),
				'type'  => 'text',
				'std'   => __( 'Example default text', 'buymyad' )
			),
		);

		// If EDD is at version 2.5 or later...
		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$custom_settings = array( 'buymyad' => $custom_settings );
		}

		return array_merge( $settings, $custom_settings );
	}

	/**
	 * ####################
	 * ### WP Webhooks 
	 * ####################
	 */

	/*
	 * Register dynamically all integrations
	 * The integrations are available within core/includes/integrations.
	 * A new folder is considered a new integration.
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @return	void
	 */
	public function add_wp_webhooks_integrations(){

		// Abort if WP Webhooks is not active
		if( ! function_exists('WPWHPRO') ){
			return;
		}

		$custom_integrations = array();
		$folder = BUYMYAD_PLUGIN_DIR . 'core' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'integrations';

		try {
			$custom_integrations = WPWHPRO()->helpers->get_folders( $folder );
		} catch ( Exception $e ) {
			WPWHPRO()->helpers->log_issue( $e->getTraceAsString() );
		}

		if( ! empty( $custom_integrations ) ){
			foreach( $custom_integrations as $integration ){
				$file_path = $folder . DIRECTORY_SEPARATOR . $integration . DIRECTORY_SEPARATOR . $integration . '.php';
				WPWHPRO()->integrations->register_integration( array(
					'slug' => $integration,
					'path' => $file_path,
				) );
			}
		}
	}

}
