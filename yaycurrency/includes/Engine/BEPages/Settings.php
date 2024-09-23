<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\TranslateHelper;
use Yay_Currency\Helpers\SupportHelper;

defined( 'ABSPATH' ) || exit;
/**
 * Settings Page
 */
class Settings {
	use SingletonTrait;

	public $setting_hookfix = null;

	/**
	 * Hooks Initialization
	 *
	 * @return void
	 */
	protected function __construct() {

		// Register Custom Post Type
		add_action( 'init', array( $this, 'register_post_type' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), YAY_CURRENCY_MENU_PRIORITY );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_filter( 'plugin_action_links_' . YAY_CURRENCY_BASE_NAME, array( $this, 'addActionLinks' ) );
		add_filter( 'plugin_row_meta', array( $this, 'addDocumentSupportLinks' ), 10, 2 );
		add_filter( 'woocommerce_general_settings', array( $this, 'add_multi_currencies_button' ), 10, 1 );
	}

	public function register_post_type() {
		$labels                 = array(
			'name'          => __( 'Currencies Manage', 'yay-currency' ),
			'singular_name' => __( 'Currency Manage', 'yay-currency' ),
		);
		$yay_currency_post_type = Helper::get_post_type();
		$args                   = array(
			'labels'            => $labels,
			'description'       => __( 'Currency Manage', 'yay-currency' ),
			'public'            => false,
			'show_ui'           => false,
			'has_archive'       => true,
			'show_in_admin_bar' => false,
			'show_in_rest'      => true,
			'show_in_menu'      => false,
			'query_var'         => $yay_currency_post_type,
			'supports'          => array(
				'title',
				'thumbnail',
			),
			'capabilities'      => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'delete_posts'       => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
		);

		register_post_type( $yay_currency_post_type, $args );

	}

	public function admin_menu() {
		$page_title            = __( 'YayCurrency', 'yay-currency' );
		$menu_title            = __( 'YayCurrency', 'yay-currency' );
		$this->setting_hookfix = add_submenu_page( 'yaycommerce', $page_title, $menu_title, 'manage_woocommerce', 'yay_currency', array( $this, 'submenu_page_callback' ), 0 );
	}

	public function admin_enqueue_scripts( $hook_suffix ) {

		do_action( 'yay_currency_admin_enqueue_scripts' );

		$allow_hook_suffixes = array( 'yaycommerce_page_yay_currency', 'nav-menus.php', 'widgets.php', 'post-new.php' );
		if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
			$post_id   = get_the_ID();
			$post_type = get_post_type( $post_id );
			if ( 'product' === $post_type ) {
				array_push( $allow_hook_suffixes, $hook_suffix );
			}
		}

		if ( ! in_array( $hook_suffix, $allow_hook_suffixes ) ) {
			return;
		}

		if ( 'yaycommerce_page_yay_currency' === $hook_suffix ) {
			SupportHelper::detect_deregister_script();
		}

		wp_register_script( 'yay-currency', YAY_CURRENCY_PLUGIN_URL . 'assets/dist/js/main.js', array(), YAY_CURRENCY_VERSION, true );
		wp_localize_script(
			'yay-currency',
			'yayCurrency',
			array(
				'admin_url'                 => admin_url( 'admin.php?page=wc-settings' ),
				'plugin_url'                => YAY_CURRENCY_PLUGIN_URL,
				'ajaxurl'                   => admin_url( 'admin-ajax.php' ),
				'image_url'                 => YAY_CURRENCY_PLUGIN_URL . 'assets/images',
				'nonce'                     => wp_create_nonce( 'yay-currency-nonce' ),
				'currenciesData'            => Helper::convert_currencies_data(),
				'listCurrencies'            => Helper::woo_list_currencies(),
				'currencyCodeByCountryCode' => Helper::currency_code_by_country_code(),
				'i18n'                      => TranslateHelper::get_translations(),
			)
		);

		wp_enqueue_style(
			'yay-currency',
			YAY_CURRENCY_PLUGIN_URL . 'assets/dist/main.css',
			array(
				'woocommerce_admin_styles',
				'wp-components',
			),
			YAY_CURRENCY_VERSION
		);

		wp_enqueue_style(
			'yay-currency-admin-styles',
			YAY_CURRENCY_PLUGIN_URL . 'src/admin-styles.css',
			array(),
			YAY_CURRENCY_VERSION
		);

		wp_enqueue_script( 'yay-currency' );
	}

	public function submenu_page_callback() {
		echo '<div id="yay-currency"></div>';
	}

	public function addActionLinks( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( '/admin.php?page=yay_currency' ) ) . '">' . __( 'Settings', 'yay-currency' ) . '</a>',
		);
		$links[]      = '<a target="_blank" href="https://yaycommerce.com/yaycurrency-woocommerce-multi-currency-switcher/" style="color: #43B854; font-weight: bold">' . __( 'Go Pro', 'yay-currency' ) . '</a>';
		return array_merge( $action_links, $links );
	}

	public function addDocumentSupportLinks( $links, $file ) {
		if ( strpos( $file, YAY_CURRENCY_BASE_NAME ) !== false ) {
			$new_links = array(
				'doc'     => '<a href="https://yaycommerce.gitbook.io/yaycurrency/" target="_blank">' . __( 'Docs', 'yay-currency' ) . '</a>',
				'support' => '<a href="https://yaycommerce.com/support/" target="_blank" aria-label="' . esc_attr__( 'Visit community forums', 'yay-currency' ) . '">' . esc_html__( 'Support', 'yay-currency' ) . '</a>',
			);
			$links     = array_merge( $links, $new_links );
		}
		return $links;
	}

	public function add_multi_currencies_button( $sections ) {
		$update_sections = array();
		foreach ( $sections as $section ) {
			if ( array_key_exists( 'id', $section ) && 'pricing_options' === $section['id'] ) {
				$section['desc'] = '<a class="button" href="' . esc_url( admin_url( '/admin.php?page=yay_currency' ) ) . '">' . esc_html__( 'Configure multi-currency', 'yay-currency' ) . '</a><br>' . esc_html__( 'The following options affect how prices are displayed on the frontend', 'yay-currency' );
			}
			$update_sections[] = $section;
		}
		return $update_sections;
	}
}
