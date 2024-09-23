<?php
namespace Yay_Currency\Engine\Compatibles;

use Yay_Currency\Helpers\SupportHelper;
use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

// Link plugin: https://woocommerce.com/products/request-a-quote-plugin-for-woocommerce/

class WooCommerceRequestAQuote {

	use SingletonTrait;

	private $apply_currency  = array();
	private $detect_currency = array();

	public function __construct() {

		if ( ! class_exists( 'Addify_Request_For_Quote' ) ) {
			return;
		}

		$this->apply_currency = YayCurrencyHelper::detect_current_currency();

		add_filter( 'yay_currency_detect_action_args', array( $this, 'yay_currency_detect_action_args' ), 10, 1 );
		add_filter( 'yay_currency_detect_reload_with_ajax', array( $this, 'yay_currency_detect_reload_with_ajax' ), 10, 1 );
		add_filter( 'yay_currency_detect_current_currency', array( $this, 'yay_currency_detect_current_currency' ), 10, 1 );

		add_action( 'wp_loaded', array( $this, 'set_quote_items_when_switcher_currency' ), 9999 );

		add_filter( 'woocommerce_currency_symbol', array( $this, 'change_existing_currency_symbol' ), 9999, 2 );
		add_filter( 'wc_price_args', array( $this, 'custom_wc_price_args' ), 10, 1 );

		add_filter( 'addify_add_quote_item', array( $this, 'addify_add_quote_item' ), 99, 2 );
		add_action( 'addify_quote_contents_updated', array( $this, 'update_quote_addon_price' ), 99, 1 );

		add_filter( 'addify_rfq_quote_converted_to_order', array( $this, 'update_quote_order_currency' ), 99, 2 );

		add_action( 'addify_quote_session_changed', array( $this, 'update_session_quote_addon_price' ), 99 );

		add_filter( 'addify_quote_item_price', array( $this, 'addify_quote_item_price' ), 10, 3 );
		add_filter( 'addify_quote_item_subtotal', array( $this, 'addify_quote_item_subtotal' ), 10, 3 );
		add_filter( 'addify_rfq_quote_totals', array( $this, 'addify_rfq_quote_totals' ), 10, 1 );

		add_filter( 'yay_currency_cart_item_addon_data', array( $this, 'yay_currency_cart_item_addon_data' ), 9999, 5 );

	}

	public function yay_currency_detect_action_args( $action_args ) {
		$ajax_args   = array( 'update_quote_items', 'remove_quote_item' );
		$action_args = array_unique( array_merge( $action_args, $ajax_args ) );
		return $action_args;
	}

	public function yay_currency_detect_reload_with_ajax( $flag = false ) {
		if ( is_admin() ) {
			$post_id = isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) ? intval( sanitize_text_field( $_REQUEST['post'] ) ) : false;

			if ( get_post_meta( $post_id, 'quote_contents', true ) ) {
				$quote_contents = get_post_meta( $post_id, 'quote_contents', true );
				$quote_content  = array_shift( $quote_contents );
				if ( isset( $quote_content['yay_currency_added'] ) ) {
					$this->detect_currency = $quote_content['yay_currency_added'];
				}
				return true;
			}
		}
		return $flag;
	}

	public function yay_currency_detect_current_currency( $apply_currency ) {
		if ( $this->detect_currency ) {
			return $this->detect_currency;
		}

		return $apply_currency;

	}

	public function set_quote_items_when_switcher_currency() {
		if ( ! is_admin() && isset( $_REQUEST['yay-currency-nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['yay-currency-nonce'] ), 'yay-currency-check-nonce' ) ) {
			$currency_code = $this->apply_currency['currency'];
			if ( $currency_code ) {
				$updated_quote = array();
				$quotes        = WC()->session->get( 'quotes' );
				if ( $quotes ) {
					foreach ( WC()->session->get( 'quotes' ) as $quote_item_key => $cart_item_data ) {
						if ( ! isset( $cart_item_data['data'] ) || ! is_object( $cart_item_data['data'] ) ) {
							continue;
						}
						// Place Quote
						$before_quote_currency = isset( $cart_item_data['yay_currency_added'] ) && ! empty( $cart_item_data['yay_currency_added'] ) ? $cart_item_data['yay_currency_added'] : false;
						if ( $before_quote_currency ) {
							$rate_fee = YayCurrencyHelper::get_rate_fee( $before_quote_currency );
							if ( defined( 'WC_PRODUCT_ADDONS_VERSION' ) ) {
								if ( ! empty( $cart_item_data['addons_price_before_calc'] ) ) {
									$addons_price_before_calc                   = $cart_item_data['addons_price_before_calc'] / $rate_fee;
									$cart_item_data['addons_price_before_calc'] = YayCurrencyHelper::calculate_price_by_currency( $addons_price_before_calc, false, $this->apply_currency );
								}
								if ( ! empty( $cart_item_data['addons_price'] ) ) {
									$addons_price                   = $cart_item_data['addons_price'] / $rate_fee;
									$cart_item_data['addons_price'] = YayCurrencyHelper::calculate_price_by_currency( $addons_price, false, $this->apply_currency );
								}
							}
							if ( ! empty( $cart_item_data['offered_price'] ) ) {
								$offered_price                   = $cart_item_data['offered_price'] / $rate_fee;
								$cart_item_data['offered_price'] = YayCurrencyHelper::calculate_price_by_currency( $offered_price, false, $this->apply_currency );
							}

							$cart_item_data['yay_currency_code_added'] = $this->apply_currency['currency'];
							$cart_item_data['yay_currency_added']      = $this->apply_currency;

							$updated_quote[ $quote_item_key ] = $cart_item_data;
						}
					}
				}

				if ( $updated_quote ) {
					wc()->session->set( 'quotes', $updated_quote );
				}
			}
		}
	}

	public function addify_add_quote_item( $quote_item, $quote_key ) {

		if ( isset( $quote_item['offered_price'] ) && ! empty( $quote_item['offered_price'] ) ) {
			$quote_item['offered_price'] = YayCurrencyHelper::calculate_price_by_currency( $quote_item['offered_price'], false, $this->apply_currency );
		}

		$quote_item['yay_currency_code_added'] = $this->apply_currency['currency'];
		$quote_item['yay_currency_added']      = $this->apply_currency;

		return $quote_item;
	}

	public function update_quote_addon_price( $quote_id, $post_data = array() ) {
		if ( ! class_exists( 'WC_Product_Addons_Helper' ) || empty( $quote_id ) ) {
			return;
		}
		$quote_contents = get_post_meta( $quote_id, 'quote_contents', true );
		if ( ! $quote_contents ) {
			return;
		}
		$updated_quote = array();
		foreach ( $quote_contents as $quote_item_key => $cart_item_data ) {
			if ( ! isset( $cart_item_data['data'] ) || ! is_object( $cart_item_data['data'] ) || ! isset( $cart_item_data['yay_currency_added'] ) ) {
				continue;
			}
			if ( ! empty( $cart_item_data['addons'] ) && apply_filters( 'woocommerce_product_addons_adjust_price', true, $cart_item_data ) ) {
				$original_price = (float) $cart_item_data['data']->get_price( 'edit' );
				$price          = YayCurrencyHelper::calculate_price_by_currency( $original_price, false, $cart_item_data['yay_currency_added'] );

				// Save the price before price type calculations to be used later.
				$cart_item_data['addons_price_before_calc'] = (float) $price;

				foreach ( $cart_item_data['addons'] as $addon ) {
					$price_type  = $addon['price_type'];
					$addon_price = $addon['price'];

					switch ( $price_type ) {
						case 'percentage_based':
							$price += (float) ( $price * ( $addon_price / 100 ) );
							break;
						case 'flat_fee':
							$price += (float) ( $addon_price / $cart_item_data['quantity'] );
							break;
						default:
							$price += (float) $addon_price;
							break;
					}
				}

				$cart_item_data['data']->set_price( $price );

				$cart_item_data['offered_price'] = $cart_item_data['data']->get_price( 'edit' );
				$cart_item_data['addons_price']  = $cart_item_data['data']->get_price( 'edit' );
			}

			$updated_quote[ $quote_item_key ] = $cart_item_data;
		}

		update_post_meta( $quote_id, 'quote_contents', $updated_quote );
	}

	public function update_quote_order_currency( $quote_order_id, $post_id ) {
		$quote_contents = get_post_meta( $post_id, 'quote_contents', true );
		if ( $quote_contents ) {
			$quote_content = array_shift( $quote_contents );
			if ( isset( $quote_content['yay_currency_code_added'] ) ) {
				$order = wc_get_order( $quote_order_id );
				if ( $order ) {
					$order->set_currency( $quote_content['yay_currency_code_added'] );
					$order->save();
				}
			}
		}
	}

	public function update_session_quote_addon_price() {
		$updated_quote = array();
		foreach ( WC()->session->get( 'quotes' ) as $quote_item_key => $cart_item_data ) {
			if ( ! is_admin() && ! isset( $cart_item_data['data'] ) || ! is_object( $cart_item_data['data'] ) ) {
				continue;
			}
			// Place Quote
			$quote_currency_code = isset( $cart_item_data['yay_currency_code_added'] ) && ! empty( $cart_item_data['yay_currency_code_added'] ) ? $cart_item_data['yay_currency_code_added'] : false;
			if ( $quote_currency_code ) {
				if ( defined( 'WC_PRODUCT_ADDONS_VERSION' ) ) {

					if ( ! empty( $cart_item_data['addons_price_before_calc'] ) && ! isset( $cart_item_data['yay_currency_converted'] ) ) {
						$cart_item_data['addons_price_before_calc'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_data['addons_price_before_calc'], false, $this->apply_currency );
					}

					if ( ! empty( $cart_item_data['addons_price'] ) && ! isset( $cart_item_data['yay_currency_converted'] ) ) {
						$cart_item_addons_price         = $cart_item_data['addons_price'];
						$cart_item_data['addons_price'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_addons_price, false, $this->apply_currency );
					}

					$args_update_quote = [ 'update_quote_items', 'remove_quote_item' ];
					if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $args_update_quote, true ) || ( isset( $_REQUEST['afrfq_action'] ) && 'save_afrfq' === $_REQUEST['afrfq_action'] ) ) {
						if ( ! empty( $cart_item_data['addons_price_before_calc'] ) ) {
							$cart_item_data['addons_price_before_calc'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_data['addons_price_before_calc'], false, $this->apply_currency );
							if ( ! empty( $cart_item_data['addons_price'] ) ) {
								$cart_item_data['addons_price'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_data['addons_price'], false, $this->apply_currency );
							}
						}
					}

					$args_add_quote = [ 'add_to_quote_single', 'add_to_quote_single_vari' ];
					if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $args_add_quote, true ) ) {
						if ( isset( $cart_item_data['offered_price'] ) && $cart_item_data['offered_price'] !== $cart_item_data['addons_price'] ) {
							if ( ! empty( $cart_item_data['addons_price_before_calc'] ) ) {
								$cart_item_data['addons_price_before_calc'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_data['addons_price_before_calc'], false, $this->apply_currency );
								if ( ! empty( $cart_item_data['addons_price'] ) ) {
									$cart_item_data['addons_price'] = YayCurrencyHelper::calculate_price_by_currency( $cart_item_data['addons_price'], false, $this->apply_currency );
								}
							}
						}
					}

					if ( ! empty( $cart_item_data['addons'] ) && isset( $_REQUEST['afrfq_action'] ) && 'save_afrfq' === $_REQUEST['afrfq_action'] ) {
						$addons = $cart_item_data['addons'];
						foreach ( $addons as $key => $addon ) {
							if ( isset( $addon['price'] ) && ! empty( $addon['price'] ) ) {
								$addons[ $key ]['price'] = YayCurrencyHelper::calculate_price_by_currency( $addon['price'], false, $this->apply_currency );
							}
						}
						$cart_item_data['addons'] = $addons;
					}
				}
				$cart_item_data['yay_currency_converted'] = true;
				$updated_quote[ $quote_item_key ]         = $cart_item_data;
			}
		}

		if ( $updated_quote ) {
			wc()->session->set( 'quotes', $updated_quote );
		}

	}

	public function change_existing_currency_symbol( $currency_symbol, $currency ) {
		if ( wp_doing_ajax() ) {
			$currency_symbol = wp_kses_post( html_entity_decode( $this->apply_currency['symbol'] ) );
		} else {
			$post_id = false;
			if ( ! is_admin() ) {
				$afrfq_id = get_query_var( 'request-quote' );
				if ( $afrfq_id ) {
					$quote = get_post( $afrfq_id );
					if ( ! empty( $afrfq_id ) && is_a( $quote, 'WP_Post' ) ) {
						$post_id = $afrfq_id;
					}
				}
				if ( isset( $_REQUEST['addify_convert_to_order'] ) && isset( $_REQUEST['post_ID'] ) ) {
					$post_id = sanitize_text_field( $_REQUEST['post_ID'] );
				}
			}
			if ( $post_id && get_post_meta( $post_id, 'quote_contents', true ) ) {
				$quote_contents = get_post_meta( $post_id, 'quote_contents', true );
				$quote_content  = array_shift( $quote_contents );
				if ( isset( $quote_content['yay_currency_added'] ) ) {
					$currency_symbol = $quote_content['yay_currency_added']['symbol'];
				}
			}
		}
		return $currency_symbol;
	}

	public function custom_wc_price_args( $args ) {
		if ( doing_action( 'save_post_addify_quote' ) || isset( $_REQUEST['addify_convert_to_order'] ) ) {
			if ( isset( $_REQUEST['addify_convert_to_order'] ) ) {
				$post_id = isset( $_REQUEST['post_ID'] ) ? sanitize_text_field( $_REQUEST['post_ID'] ) : false;
				if ( $post_id && get_post_meta( $post_id, 'quote_contents', true ) ) {
					$quote_contents = get_post_meta( $post_id, 'quote_contents', true );
					$quote_content  = array_shift( $quote_contents );
					if ( isset( $quote_content['yay_currency_added'] ) ) {
						$args = YayCurrencyHelper::get_apply_currency_format_info( $quote_content['yay_currency_added'] );
					}
				}
			}
		}

		return $args;
	}

	public function addify_quote_item_price( $price, $quote_item, $quote_item_key ) {
		if ( wp_doing_ajax() ) {
			$price = SupportHelper::get_product_price( $quote_item['data']->get_id() );
			$price = YayCurrencyHelper::calculate_price_by_currency( $price, false, $this->apply_currency );
			$price = YayCurrencyHelper::format_price( $price );
		}
		return $price;
	}

	public function addify_quote_item_subtotal( $subtotal, $quote_item, $quote_item_key ) {
		if ( wp_doing_ajax() ) {
			if ( isset( $quote_item['addons_price'] ) ) {
				$subtotal = $quote_item['addons_price'] * $quote_item['quantity'];
			} else {
				$price_option = 0;
				if ( isset( $quote_item['addons_price_before_calc'] ) && $quote_item['addons_price_before_calc'] !== $quote_item['addons_price'] && isset( $quote_item['yay_currency_added'] ) ) {
					$price_option = $quote_item['addons_price'] - $quote_item['addons_price_before_calc'];
					$price_option = (float) $price_option / YayCurrencyHelper::get_rate_fee( $quote_item['yay_currency_added'] );
				}
				$subtotal = ( SupportHelper::get_product_price( $quote_item['data']->get_id() ) + $price_option ) * $quote_item['quantity'];
				$subtotal = YayCurrencyHelper::calculate_price_by_currency( $subtotal, false, $this->apply_currency );
			}
			$subtotal = YayCurrencyHelper::format_price( $subtotal );
		}
		return $subtotal;
	}

	public function addify_rfq_quote_totals( $quote_totals ) {
		if ( wp_doing_ajax() ) {
			$quote_totals['_subtotal']       = YayCurrencyHelper::calculate_price_by_currency( $quote_totals['_subtotal'], false, $this->apply_currency );
			$quote_totals['_offered_total']  = YayCurrencyHelper::calculate_price_by_currency( $quote_totals['_offered_total'], false, $this->apply_currency );
			$quote_totals['_tax_total']      = YayCurrencyHelper::calculate_price_by_currency( $quote_totals['_tax_total'], false, $this->apply_currency );
			$quote_totals['_shipping_total'] = YayCurrencyHelper::calculate_price_by_currency( $quote_totals['_shipping_total'], false, $this->apply_currency );
			$quote_totals['_total']          = YayCurrencyHelper::calculate_price_by_currency( $quote_totals['_total'], false, $this->apply_currency );
		}
		return $quote_totals;
	}

	public function yay_currency_cart_item_addon_data( $cart_item_addon_data_value, $args, $cart_item, $addon, $apply_currency ) {
		if ( isset( $cart_item['yay_currency_added'] ) ) {
			$item_fee = (float) $addon['price'];
			$afrfq_id = get_query_var( 'request-quote' );
			// is Myaccount
			if ( $afrfq_id ) {
				$quote = get_post( $afrfq_id );
				if ( ! empty( $afrfq_id ) && is_a( $quote, 'WP_Post' ) ) {
					$item_fee              = $args['price_options_default_currency'];
					$this->detect_currency = $cart_item['yay_currency_added'];
				}
			}
			$args_action = [ 'update_quote_items', 'remove_quote_item' ];
			if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $args_action, true ) ) {
				$item_fee = isset( $args['price_options_current_currency'] ) ? $args['price_options_current_currency'] : YayCurrencyHelper::calculate_price_by_currency( $item_fee, true, $apply_currency );
			}
			if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) && isset( $_REQUEST['action'] ) ) {
				$quote_id = intval( sanitize_text_field( $_REQUEST['post'] ) );
				$quote    = get_post( $quote_id );
				if ( is_a( $quote, 'WP_Post' ) ) {
					$item_fee = $args['price_options_default_currency'];
				}
			}
			if ( doing_action( 'addify_rfq_email_quote_details' ) || doing_action( 'addify_rfq_send_quote_email_to_customer' ) || doing_action( 'do_action' ) ) {
				$item_fee = $args['price_options_default_currency'];
			}

			$formatted_item_fee         = wc_price(
				$item_fee,
				YayCurrencyHelper::get_apply_currency_format_info( $cart_item['yay_currency_added'] )
			);
			$cart_item_addon_data_value = $addon['value'] . ' (+ ' . $formatted_item_fee . ')';
		}
		return $cart_item_addon_data_value;
	}
}
