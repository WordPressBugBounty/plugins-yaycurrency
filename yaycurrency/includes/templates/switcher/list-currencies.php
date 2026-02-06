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
		$flag_url     = apply_filters( 'YayCurrency/Frontend/Switcher/ByCurrencyCode/GetFlagUrl', $flag_url, $currency->post_title );
		$html_flag    = '<span style="background-image: url(' . $flag_url . ')" class="yay-currency-flag ' . $switcher_size . '" data-country_code="' . $country_code . '"></span>';
	}
	$currency_name          = $is_show_currency_name ? $woo_currencies[ $currency->post_title ] : null;
	$get_symbol_by_currency = YayCurrencyHelper::get_symbol_by_currency( $currency->post_title, $converted_currency );
	$currency_symbol        = $is_show_currency_symbol ? ( $is_show_currency_name ? ' (' . $get_symbol_by_currency . ')' : $get_symbol_by_currency . ' ' ) : null;
	$hyphen                 = ( $is_show_currency_name && $is_show_currency_code ) ? ' - ' : null;
	$currency_code          = $is_show_currency_code ? apply_filters( 'YayCurrency/Frontend/Switcher/GetCurrencyCode', $currency->post_title ) : null;
	?>
	<li class="yay-currency-id-<?php echo esc_attr( $currency->ID ); ?> yay-currency-custom-option-row <?php echo $currency->ID === $selected_currency_id ? 'selected' : ''; ?> <?php echo $is_show_flag ? 'yay-currency-row-with-flag' : ''; ?>" data-currency-id="<?php echo esc_attr( $currency->ID ); ?>">
		<?php
		if ( $html_flag ) {
			echo wp_kses_post( $html_flag );
		}
		?>
		<div class="yay-currency-custom-option <?php echo esc_attr( $switcher_size ); ?>">
			<?php
				echo wp_kses_post( Helper::decode_html_entity( $currency_name . $currency_symbol . $hyphen . $currency_code ) );
			?>
		</div>
		<?php if ( $currency->ID === $selected_currency_id ) { ?>
			<span class="yay-currency-selected-checked-icon"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#737373" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-4" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg></span></span>
		<?php } ?>
	</li>
<?php } ?>
</ul>
