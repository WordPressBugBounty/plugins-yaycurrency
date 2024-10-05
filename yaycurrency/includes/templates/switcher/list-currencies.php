<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
$country_code = null;
$html_flag    = false;
?>
<ul class="yay-currency-custom-options">
<?php
foreach ( $selected_currencies as $currency ) {
	if ( $is_show_flag ) {
		$country_code = $countries_code[ $currency->post_title ];
		$flag_url     = Helper::get_flag_by_country_code( $country_code );
		$flag_url     = apply_filters( 'yay_currency_get_flag_url_by_currency_code', $flag_url, $currency->post_title );
		$html_flag    = '<span style="background-image: url(' . $flag_url . ')" class="yay-currency-flag ' . $switcher_size . '" data-country_code="' . $country_code . '"></span>';
	}
	$currency_name          = $is_show_currency_name ? $woo_currencies[ $currency->post_title ] : null;
	$get_symbol_by_currency = YayCurrencyHelper::get_symbol_by_currency( $currency->post_title, $converted_currency );
	$currency_symbol        = $is_show_currency_symbol ? ( $is_show_currency_name ? ' (' . $get_symbol_by_currency . ')' : $get_symbol_by_currency . ' ' ) : null;
	$hyphen                 = ( $is_show_currency_name && $is_show_currency_code ) ? ' - ' : null;
	$currency_code          = $is_show_currency_code ? apply_filters( 'yay_currency_switcher_change_currency_code', $currency->post_title, $currency ) : null;
	?>
	<li class="yay-currency-custom-option-row <?php echo $currency->ID === $selected_currency_id ? 'selected' : ''; ?>" data-currency-id="<?php echo esc_attr( $currency->ID ); ?>">
		<?php
		if ( $html_flag ) {
			echo wp_kses_post( $html_flag );
		}
		?>
		<div class="yay-currency-custom-option <?php echo esc_attr( $switcher_size ); ?>">
			<?php
				echo wp_kses_post(
					html_entity_decode(
						esc_html__( $currency_name, 'yay-currency' ) . esc_html__( $currency_symbol, 'yay-currency' ) . esc_html( $hyphen ) . esc_html__(
							$currency_code,
							'yay-currency'
						)
					)
				);
			?>
		</div>
	</li>
<?php } ?>
</ul>
