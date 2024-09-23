<?php
namespace Yay_Currency\Engine\Appearance;

use Yay_Currency\Helpers\Helper;
use Yay_Currency\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class MenuDropdown {
	use SingletonTrait;

	protected function __construct() {
		add_action(
			'admin_init',
			function () {
				add_meta_box(
					'yaycurrency-switcher',
					'YayCurrency',
					array( $this, 'nav_menu_currency_dropdown' ),
					'nav-menus',
					'side',
					'high'
				);
			}
		);
		add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'menu_item_custom_fields' ), 10, 2 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save_menu_item_custom_fields' ), 10, 2 );
		add_filter( 'walker_nav_menu_start_el', array( $this, 'render_currency_switcher_as_menu_item' ), 10, 4 );
	}

	public function render_currency_switcher_as_menu_item( $item_output, $menu_item, $depth, $args ) {
		if ( isset( $menu_item->classes ) && is_array( $menu_item->classes ) && in_array( 'yay-currency-dropdown', $menu_item->classes, true ) ) {
			$item_output = do_shortcode( '[yaycurrency-menu-item-switcher]' );
		}
		return $item_output;
	}

	public function save_menu_item_custom_fields( $menu_id, $menu_item_db_id ) {
		if ( isset( $_REQUEST['yay-currency-nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['yay-currency-nonce'] ), 'yay-currency-check-nonce' ) ) {
			$is_show_flag_in_menu_item            = isset( $_POST['show-flag'] ) ? sanitize_text_field( $_POST['show-flag'] ) : 0;
			$is_show_currency_name_in_menu_item   = isset( $_POST['show-currency-name'] ) ? sanitize_text_field( $_POST['show-currency-name'] ) : 0;
			$is_show_currency_symbol_in_menu_item = isset( $_POST['show-currency-symbol'] ) ? sanitize_text_field( $_POST['show-currency-symbol'] ) : 0;
			$is_show_currency_code_in_menu_item   = isset( $_POST['show-currency-code'] ) ? sanitize_text_field( $_POST['show-currency-code'] ) : 0;
			$menu_item_size                       = isset( $_POST['menu-item-size'] ) ? sanitize_text_field( $_POST['menu-item-size'] ) : 'small';

			update_option( 'yay_currency_show_flag_in_menu_item', $is_show_flag_in_menu_item );
			update_option( 'yay_currency_show_currency_name_in_menu_item', $is_show_currency_name_in_menu_item );
			update_option( 'yay_currency_show_currency_symbol_in_menu_item', $is_show_currency_symbol_in_menu_item );
			update_option( 'yay_currency_show_currency_code_in_menu_item', $is_show_currency_code_in_menu_item );
			update_option( 'yay_currency_menu_item_size', $menu_item_size );
		}

	}

	public function menu_item_custom_fields( $item_id, $item ) {
		if ( 'yaycurrency-switcher' === $item->post_name || 'YayCurrency Switcher' === $item->post_title ) {
			Helper::create_nonce_field();
			require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/appearance/menuItemCustomFields.php';
		}
	}

	public function nav_menu_currency_dropdown() {
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/appearance/menuItem.php';
	}
}
