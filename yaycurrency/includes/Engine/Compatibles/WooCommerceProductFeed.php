<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;
class WooCommerceProductFeed {

	use SingletonTrait;

	public function __construct() {

		// CTXFeed & CTXFeed Pro. Link plugin: https://wordpress.org/plugins/webappick-product-feed-for-woocommerce/
		if ( defined( 'WOO_FEED_API_VERSION' ) ) {
			add_filter( 'yay_currency_detect_reload_with_ajax', array( $this, 'yay_currency_detect_reload_with_ajax' ), 10, 1 );
			add_action( 'before_woo_feed_generate_batch_data', array( $this, 'before_woo_feed_generate_batch_data' ), 10, 1 );
			add_filter( 'yay_currency_detect_current_currency', array( $this, 'yay_ctxfeed_get_apply_currency_by_feed' ), 10, 1 );
		}

		//Product Feed PRO for WooCommerce. Link plugin: https://wordpress.org/plugins/woo-product-feed-pro/
		if ( defined( 'WOOCOMMERCESEA_PLUGIN_VERSION' ) && class_exists( '\WooSEA_Update_Project' ) ) {
			add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );
			add_filter( 'yay_currency_detect_reload_with_ajax', array( $this, 'yay_currency_detect_reload_with_ajax' ), 10, 1 );
			add_filter( 'yay_currency_detect_current_currency', array( $this, 'get_apply_currency_by_feed' ), 10, 1 );
		}

	}

	// CTXFeed & CTXFeed Pro.
	protected function get_apply_currency_make_per_batch_feed( $feedrules ) {
		$apply_currency = array();

		if ( isset( $feedrules['attributes'] ) && is_array( $feedrules['attributes'] ) && isset( $feedrules['suffix'] ) && is_array( $feedrules['suffix'] ) ) {
			$attributes = $feedrules['attributes'];
			$suffix     = $feedrules['suffix'];

			$convert_currency = false;

			// Find Currency code
			$key_current_price = array_search( 'current_price', $attributes );
			if ( $key_current_price && isset( $suffix[ $key_current_price ] ) && ! empty( $suffix[ $key_current_price ] ) ) {
				$convert_currency = trim( $suffix[ $key_current_price ] );
			} else {
				$key_price = array_search( 'price', $attributes );
				if ( $key_price && isset( $suffix[ $key_price ] ) && ! empty( $suffix[ $key_price ] ) ) {
					$convert_currency = trim( $suffix[ $key_price ] );
				}
			}

			if ( ! $convert_currency ) {
				$convert_currency = isset( $feedrules['feedCurrency'] ) ? $feedrules['feedCurrency'] : Helper::default_currency_code();
			}

			$apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $convert_currency );
		}

		return $apply_currency;
	}

	public function before_woo_feed_generate_batch_data( $feed_info ) {
		if ( ! is_object( $feed_info ) ) {
			return;
		}

		$feedrules                          = $feed_info->get_config();
		$apply_currency                     = self::get_apply_currency_make_per_batch_feed( $feedrules );
		$apply_currency                     = $apply_currency ? $apply_currency : reset( YayCurrencyHelper::converted_currency() );
		$_REQUEST['ctxfeed_apply_currency'] = wp_json_encode( $apply_currency );

	}

	public function yay_ctxfeed_get_apply_currency_by_feed( $apply_currency ) {

		if ( isset( $_REQUEST['ctxfeed_apply_currency'] ) ) {
			$new_apply_currency = sanitize_text_field( $_REQUEST['ctxfeed_apply_currency'] );
			$new_apply_currency = json_decode( $new_apply_currency, true );
			return $new_apply_currency ? $new_apply_currency : $apply_currency;
		}

		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'make_per_batch_feed' === $_REQUEST['action'] ) {
			$feed_info = isset( $_REQUEST['feed_info'] ) && ! empty( $_REQUEST['feed_info'] ) ? map_deep( wp_unslash( $_REQUEST['feed_info'] ), 'sanitize_text_field' ) : false;
			if ( $feed_info ) {
				$feedrules      = $feed_info['option_value']['feedrules'];
				$apply_currency = self::get_apply_currency_make_per_batch_feed( $feedrules );
			}
		}

		return $apply_currency;

	}


	//Product Feed PRO for WooCommerce.

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'woosea_project_refresh' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}

	public function yay_currency_detect_reload_with_ajax( $flag = false ) {

		if ( defined( 'WOO_FEED_API_VERSION' ) ) {
			if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'make_per_batch_feed' === $_REQUEST['action'] ) {
				$flag = true;
			}
		}

		if ( defined( 'WOOCOMMERCESEA_PLUGIN_VERSION' ) ) {
			if ( isset( $_REQUEST['woosea_page'] ) && 'analytics' === $_REQUEST['woosea_page'] && isset( $_REQUEST['page'] ) && 'woo-product-feed-pro/woocommerce-sea.php' === $_REQUEST['page'] && isset( $_REQUEST['project_hash'] ) ) {
				$flag = true;
			}
		}

		return $flag;
	}

	public function get_apply_currency_by_feed( $apply_currency ) {
		$flag = false;
		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'woosea_project_refresh' === $_REQUEST['action'] ) {
			if ( isset( $_REQUEST['project_hash'] ) && ! empty( $_REQUEST['project_hash'] ) ) {
				$flag    = true;
				$project = \WooSEA_Update_Project::get_project_data( sanitize_text_field( $_REQUEST['project_hash'] ) );
			}
		}
		if ( self::yay_currency_detect_reload_with_ajax() ) {
			if ( get_option( 'cron_projects' ) ) {
				$project = get_option( 'cron_projects' );
				$project = array_shift( $project );
				$flag    = true;
			}
			if ( isset( $_REQUEST['project_hash'] ) && ! empty( $_REQUEST['project_hash'] ) ) {
				if ( isset( $_REQUEST['project_hash'] ) && ! empty( $_REQUEST['project_hash'] ) ) {
					$project = \WooSEA_Update_Project::get_project_data( sanitize_text_field( $_REQUEST['project_hash'] ) );
				}
				if ( ! $project ) {
					if ( get_option( 'channel_project' ) ) {
						$project = get_option( 'channel_project' );
						$flag    = true;
					}
				}
			}
		}
		if ( ! $flag ) {
			return $apply_currency;
		}

		if ( $project && isset( $project['attributes'] ) && ! empty( $project['attributes'] ) ) {
			$findKeyWord = 'g:price';
			$results     = array_filter(
				$project['attributes'],
				function ( $data ) use ( $findKeyWord ) {
					if ( $data['attribute'] === $findKeyWord ) {
						return true;
					}
					return false;
				}
			);
			if ( $results ) {
				$result = array_shift( $results );
				if ( isset( $result['prefix'] ) && ! empty( $result['prefix'] ) ) {
					$currency_code      = trim( $result['prefix'] );
					$new_apply_currency = YayCurrencyHelper::get_currency_by_currency_code( $currency_code );
					if ( $new_apply_currency ) {
						return $new_apply_currency;
					}
				}
			}
		}

		return $apply_currency;

	}
}
