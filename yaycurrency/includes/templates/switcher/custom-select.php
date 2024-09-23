<?php
use Yay_Currency\Helpers\Helper;

$no_currency_name_class                 = ! $is_show_currency_name ? ' no-currency-name' : '';
$only_currency_name_class               = $is_show_currency_name && ! $is_show_flag && ! $is_show_currency_symbol && ! $is_show_currency_code ? ' only-currency-name' : '';
$only_currency_name_and_something_class = $is_show_currency_name && 2 === Helper::count_display_elements_in_switcher( $is_show_flag, $is_show_currency_name, $is_show_currency_symbol, $is_show_currency_code ) ? ' only-currency-name-and-something' : '';
$currency_custom_class                  = array( $switcher_size, $no_currency_name_class, $only_currency_name_class, $only_currency_name_and_something_class );
?>
<div class="yay-currency-custom-select-wrapper <?php echo esc_attr( implode( ' ', $currency_custom_class ) ); ?>">
	<div class="yay-currency-custom-select">
		<?php require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/currency-selected.php'; ?>
		<?php require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/list-currencies.php'; ?>
	</div>
</div>
