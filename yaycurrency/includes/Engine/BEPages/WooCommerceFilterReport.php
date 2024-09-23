<?php
namespace Yay_Currency\Engine\BEPages;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

class WooCommerceFilterReport {

	use SingletonTrait;

	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_report_script' ) );
		add_action( 'wc_reports_tabs', array( $this, 'wc_reports_currencies_dropdown' ) );
		add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'custom_admin_report_query' ) );

	}

	public function enqueue_report_script() {
		$current_screen = get_current_screen();
		if ( 'woocommerce_page_wc-reports' === $current_screen->id ) {
			wp_enqueue_script( 'yay-currency-report', YAY_CURRENCY_PLUGIN_URL . 'src/report.js', array(), YAY_CURRENCY_VERSION, true );
		}
	}

	public function wc_reports_currencies_dropdown() {
		$current_url        = wc_get_current_admin_url();
		$all_currencies     = Helper::woo_list_currencies();
		$apply_currencies   = Helper::get_currencies_post_type();
		$converted_currency = YayCurrencyHelper::converted_currency( $apply_currencies );
		remove_filter( 'woocommerce_currency_symbol', array( $this, 'custom_admin_report_currency_symbol' ), 10, 2 );
		?>
		<div id="yay-currency-dropdown-reports">
			<span><?php echo esc_html_e( 'Sales by currency:', 'yay-currency' ); ?></span>
			<select class="widget-currencies-dropdown" name='currency'>
				<?php foreach ( $apply_currencies as $currency ) { ?>
					<option data-url="<?php echo esc_url( add_query_arg( array( 'currency' => $currency->post_title ), $current_url ) ); ?>" value='<?php echo esc_attr__( $currency->post_title, 'yay-currency' ); ?>'>
						<?php echo wp_kses_post( html_entity_decode( esc_html__( $all_currencies[ $currency->post_title ], 'yay-currency' ) . ' (' . YayCurrencyHelper::get_symbol_by_currency( $currency->post_title, $converted_currency ) . ') - ' . esc_html( $currency->post_title ) ) ); ?>
					</option>
				<?php } ?>
			</select>
		</div>
		<?php
		add_filter( 'woocommerce_currency_symbol', array( $this, 'custom_admin_report_currency_symbol' ), 10, 2 );
	}

	public function custom_admin_report_query( $query ) {
		global $wpdb;

		$currency = isset( $_GET['currency'] ) ? sanitize_text_field( $_GET['currency'] ) : Helper::default_currency_code();

		$currency = apply_filters( 'yay_currency_report_query_by_currency', $currency );

		$pattern = '/^[a-zA-Z]{3}+$/';

		if ( preg_match( $pattern, $currency ) ) {

			$query['join']  .= " LEFT JOIN {$wpdb->postmeta} AS meta_checkout_currency ON meta_checkout_currency.post_id = posts.ID";
			$query['where'] .= $wpdb->prepare( " AND meta_checkout_currency.meta_key='_order_currency' AND meta_checkout_currency.meta_value = %s", $currency );

		}

		return $query;
	}

	public function custom_admin_report_currency_symbol( $currency_symbol, $currency ) {
		$converted_currency = YayCurrencyHelper::converted_currency();
		$selected_currency  = isset( $_GET['currency'] ) ? sanitize_text_field( $_GET['currency'] ) : Helper::default_currency_code();
		$currency_symbol    = YayCurrencyHelper::get_symbol_by_currency( $selected_currency, $converted_currency );
		return $currency_symbol;
	}
}
