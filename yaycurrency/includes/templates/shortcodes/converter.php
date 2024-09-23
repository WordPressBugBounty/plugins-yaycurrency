<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;
$woo_currencies       = Helper::woo_list_currencies();
$selected_currencies  = apply_filters( 'yay_currency_get_currencies_posts', Helper::get_currencies_post_type() );
$selected_currency_id = apply_filters( 'yay_currency_get_id_selected_currency', YayCurrencyHelper::get_id_selected_currency() );
$default_currency     = Helper::default_currency_code();
$heading              = ! empty( $atts['heading'] ) ? $atts['heading'] : __( 'Currency converter', 'yay-currency' );
if ( ! empty( $atts['hide_heading'] ) && 'yes' === $atts['hide_heading'] ) {
	$heading = '';
}
?>
<div class="yay-currency-converter-container">
	<h3 class="yay-currency-converter-heading"><?php echo esc_html( $heading ); ?></h3>
	<div>
		<div class="yay-currency-converter-row">
			<div class="yay-currency-converter-label">
				<?php echo esc_html( $atts['amount_text'] ); ?>
			</div>
			<div class="yay-currency-converter-input-amount">
				<input type="number" value="1" placeholder="" class="yay-currency-converter-amount">
			</div>
		</div>

		<div class="yay-currency-converter-row">
			<div class="yay-currency-converter-label">
				<?php echo esc_html( $atts['from_text'] ); ?>
			</div>
			<div class="yay-currency-converter-input">
				<select class="yay-currency-converter-wrapper yay-currency-converter-from-currency">
						<?php
						foreach ( $selected_currencies as $currency ) {
							$currency_code = $currency->post_title;
							if ( ! isset( $woo_currencies[ $currency_code ] ) || empty( $currency_code ) ) {
								continue;
							}
							$currency_symbol = YayCurrencyHelper::get_symbol_by_currency_code( $currency_code );
							$currency_value  = $woo_currencies[ $currency_code ] . ' (' . $currency_symbol . ') - ' . esc_html( $currency_code );
							echo '<option value="' . esc_attr( $currency_code ) . '" ' . ( $default_currency === $currency->post_title ? 'selected' : '' ) . '>' . wp_kses_post( $currency_value ) . '</option>';
						}
						?>
				</select>
			</div>
		</div>

		<div class="yay-currency-converter-row">
			<div class="yay-currency-converter-label">
				<?php echo esc_html( $atts['to_text'] ); ?>
			</div>
			<div class="yay-currency-converter-input">
				<select class="yay-currency-converter-wrapper yay-currency-converter-to-currency">
					<?php
					foreach ( $selected_currencies as $currency ) {
						$currency_code = $currency->post_title;
						if ( ! isset( $woo_currencies[ $currency_code ] ) || empty( $currency_code ) ) {
							continue;
						}
						$currency_symbol = YayCurrencyHelper::get_symbol_by_currency_code( $currency_code );
						$currency_value  = $woo_currencies[ $currency_code ] . '(' . $currency_symbol . ') - ' . esc_html( $currency_code );
						echo '<option value="' . esc_attr( $currency_code ) . '" ' . ( $selected_currency_id === $currency->ID ? 'selected' : '' ) . '>' . wp_kses_post( $currency_value ) . '</option>';
					}
					?>
				</select>
			</div>
		</div>

		<div class="yay-currency-converter-row">
			<div class="yay-currency-converter-result-wrapper"><span class="yay-currency-converter-amount-value"></span> <span class="yay-currency-converter-from-currency-code"></span> = <span class="yay-currency-converter-result-value"></span> <span class="yay-currency-converter-to-currency-code"></span></div>
		</div>

	</div>
</div>