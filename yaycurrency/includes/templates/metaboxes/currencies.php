<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
$default_currency   = Helper::default_currency_code();
$converted_currency = YayCurrencyHelper::converted_currency();
$list_currencies    = Helper::woo_list_currencies();
?>
<div class='yay-currency-manual-order-wrapper'>
	<input type="hidden" name="yay_currency_manual_order_nonce" value="<?php echo esc_attr( wp_create_nonce( 'yay-currency-manual-order-nonce' ) ); ?>">
	<select name="yay_currency_code" class="regular-text">
	<?php
	foreach ( $converted_currency  as $value ) {
		$currency_code = $value['currency'];
		$currency_text = $list_currencies[ $currency_code ] . ' (' . YayCurrencyHelper::get_symbol_by_currency_code( $currency_code ) . ')';
		?>
		<option value="<?php echo esc_attr( $currency_code ); ?>"><?php echo isset( $list_currencies[ $currency_code ] ) ? esc_html( $currency_text ) : esc_html( $currency_code ); ?></option>
	<?php } ?>
	</select> 
 
</div>
