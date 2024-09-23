<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
$order_page = isset( $_REQUEST['page'] ) && 'wc-orders' === $_REQUEST['page'] ? true : false;
$order_id   = $order_page ? $post->get_id() : ( isset( $post->ID ) ? $post->ID : false );
if ( ! $order_id ) {
	return;
}

$yay_order   = wc_get_order( $order_id );
$order_rate  = 1;
$order_total = $yay_order->get_total();
if ( Helper::default_currency_code() !== $yay_order->get_currency() ) {
	if ( Helper::check_custom_orders_table_usage_enabled() ) {
		$order_rate = $yay_order->get_meta( 'yay_currency_order_rate', true );
		if ( ! $order_rate ) {
			$order_original = $yay_order->get_meta( 'yay_currency_checkout_original_total', true );
			$order_rate     = $order_original && floatval( $order_original ) > 0 ? $order_total / $order_original : false;
		}
	} else {
		$order_rate = get_post_meta( $order_id, 'yay_currency_order_rate', true );
		if ( ! $order_rate ) {
			$order_original = get_post_meta( $order_id, 'yay_currency_checkout_original_total', true );
			$order_rate     = $order_original && floatval( $order_original ) > 0 ? $order_total / $order_original : false;
		}
	}
	if ( ! $order_rate ) {
		$order_rate = YayCurrencyHelper::get_rate_fee_by_order( $yay_order );
	}
}
?>
<div class="inside">

<div class="customer-history order-attribution-metabox">
	<h4><?php echo esc_html__( 'Order Currency', 'yay-currency' ); ?>: <span><?php echo esc_html( $yay_order->get_currency() ); ?></span></h4>
	<h4><?php echo esc_html__( 'Order Rate', 'yay-currency' ); ?>: <span><?php echo esc_html( $order_rate ); ?></span></h4>
	<?php do_action( 'yay_currency_order_info_data', $order_id, $yay_order ); ?>
</div>
</div>