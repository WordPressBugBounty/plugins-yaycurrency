<?php
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

$woo_currencies          = Helper::woo_list_currencies();
$countries_code          = Helper::currency_code_by_country_code();
$selected_currencies     = apply_filters( 'yay_currency_get_currencies_posts', Helper::get_currencies_post_type() );
$converted_currency      = YayCurrencyHelper::converted_currency( $selected_currencies );
$selected_currency_id    = apply_filters( 'yay_currency_get_id_selected_currency', YayCurrencyHelper::get_id_selected_currency() );
$yay_currency_use_params = Helper::use_yay_currency_params();
$name                    = $yay_currency_use_params ? 'yay_currency' : 'currency';
?>
<div class='yay-currency-single-page-switcher'>

	<form action-xhr="<?php echo esc_url( get_site_url() ); ?>" method='POST' class='yay-currency-form-switcher'>
		<?php Helper::create_nonce_field(); ?>
		<select class='yay-currency-switcher' name='<?php echo esc_attr( $name ); ?>' onchange='this.form.submit()'>
			<?php
			foreach ( $selected_currencies as $currency ) {
				echo '<option value="' . esc_attr( $currency->ID ) . '" ' . selected( $selected_currency_id, $currency->ID, false ) . '></option>';
			}
			?>
		</select>
	</form>
	
	<?php require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/custom-select.php'; ?>
</div>
