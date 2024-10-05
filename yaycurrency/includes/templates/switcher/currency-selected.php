<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

$selected_currency  = YayCurrencyHelper::get_currency_by_ID( $selected_currency_id );
$currency_name      = ! empty( $selected_currency ) && isset( $selected_currency['currency'] ) ? $selected_currency['currency'] : Helper::default_currency_code();
$selected_html_flag = false;
if ( $is_show_flag ) {
	$selected_country_code = $countries_code[ $currency_name ];
	$selected_flag_url     = Helper::get_flag_by_country_code( $selected_country_code );
	$selected_flag_url     = apply_filters( 'yay_currency_get_flag_url_by_currency_code', $selected_flag_url, $selected_currency['currency'] );
	$selected_html_flag    = '<span style="background-image: url(' . $selected_flag_url . ')" class="yay-currency-flag selected ' . $switcher_size . '" data-country_code="' . $selected_country_code . '"></span>';
}
$get_symbol_by_currency   = YayCurrencyHelper::get_symbol_by_currency( $currency_name, $converted_currency );
$selected_currency_name   = $is_show_currency_name ? $woo_currencies[ $currency_name ] : null;
$selected_currency_symbol = $is_show_currency_symbol ? ( $is_show_currency_name ? ' (' . $get_symbol_by_currency . ')' : $get_symbol_by_currency . ' ' ) : null;
$hyphen                   = ( $is_show_currency_name && $is_show_currency_code ) ? ' - ' : null;
$selected_currency_code   = $is_show_currency_code ? apply_filters( 'yay_currency_switcher_change_currency_code', $currency_name ) : null;
?>
<div class="yay-currency-custom-select__trigger <?php echo esc_attr( $switcher_size ); ?>">
	<div class="yay-currency-custom-selected-option">
		<?php
		if ( $selected_html_flag ) {
			echo wp_kses_post( $selected_html_flag );
		}
		?>
		<span class="yay-currency-selected-option">
			<?php
				echo wp_kses_post(
					html_entity_decode(
						esc_html__( $selected_currency_name, 'yay-currency' ) . esc_html__( $selected_currency_symbol, 'yay-currency' ) . esc_html( $hyphen ) . esc_html__(
							$selected_currency_code,
							'yay-currency'
						)
					)
				);
				?>
		</span>
	</div>
	<div class="yay-currency-custom-arrow"></div>
	<div class="yay-currency-custom-loader"></div>
</div>
