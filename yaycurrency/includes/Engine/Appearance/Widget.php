<?php
namespace Yay_Currency\Engine\Appearance;

use Yay_Currency\Utils\SingletonTrait;
use Yay_Currency\Helpers\Helper;
use Yay_Currency\Helpers\YayCurrencyHelper;

defined( 'ABSPATH' ) || exit;

use WP_Widget;

class Widget extends WP_Widget {

	use SingletonTrait;

	public $widget_ID;
	public $widget_name;
	public $widget_options  = array();
	public $control_options = array();
	public $country_info;
	public $settings_data;

	protected function __construct() {

		$cookie_name = YayCurrencyHelper::get_cookie_name();

		$this->widget_ID = $cookie_name;

		$this->widget_name = 'Currency Switcher - YayCurrency (Legacy)';

		$this->widget_options = array(
			'classname'                   => $this->widget_ID,
			'description'                 => $this->widget_name,
			'customize_selective_refresh' => true,
		);

		$this->control_options = array(
			'width'  => 300,
			'height' => 350,
		);
		parent::__construct( $this->widget_ID, $this->widget_name, $this->widget_options, $this->control_options );

		add_action( 'widgets_init', array( $this, 'widgetsInit' ) );
	}

	public function widgetsInit() {
		register_widget( $this );
	}

	// widget() FE
	public function widget( $args, $instance ) {

		if ( YayCurrencyHelper::detect_allow_hide_dropdown_currencies() ) {
			return;
		}

		echo wp_kses_post( $args['before_widget'] );

		$is_show_flag            = get_option( 'yay_currency_show_flag_in_widget', 1 );
		$is_show_currency_name   = get_option( 'yay_currency_show_currency_name_in_widget', 1 );
		$is_show_currency_symbol = get_option( 'yay_currency_show_currency_symbol_in_widget', 1 );
		$is_show_currency_code   = get_option( 'yay_currency_show_currency_code_in_widget', 1 );
		$switcher_size           = get_option( 'yay_currency_widget_size', 'small' );

		?>
		<div class='yay-currency-widget-switcher'>
		<h4><?php echo esc_html_e( 'Currency Switcher', 'yay-currency' ); ?></h4>
		<?php require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/switcher/template.php'; ?>
		</div>
		<?php
		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		Helper::create_nonce_field();
		require YAY_CURRENCY_PLUGIN_DIR . 'includes/templates/appearance/widgetForm.php';
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		if ( isset( $_REQUEST['yay-currency-nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['yay-currency-nonce'] ), 'yay-currency-check-nonce' ) ) {
			$is_show_flag_in_widget            = isset( $_POST['show-flag'] ) ? sanitize_text_field( $_POST['show-flag'] ) : 0;
			$is_show_currency_name_in_widget   = isset( $_POST['show-currency-name'] ) ? sanitize_text_field( $_POST['show-currency-name'] ) : 0;
			$is_show_currency_symbol_in_widget = isset( $_POST['show-currency-symbol'] ) ? sanitize_text_field( $_POST['show-currency-symbol'] ) : 0;
			$is_show_currency_code_in_widget   = isset( $_POST['show-currency-code'] ) ? sanitize_text_field( $_POST['show-currency-code'] ) : 0;
			$widget_size                       = isset( $_POST['widget-size'] ) ? sanitize_text_field( $_POST['widget-size'] ) : 'small';

			update_option( 'yay_currency_show_flag_in_widget', $is_show_flag_in_widget );
			update_option( 'yay_currency_show_currency_name_in_widget', $is_show_currency_name_in_widget );
			update_option( 'yay_currency_show_currency_symbol_in_widget', $is_show_currency_symbol_in_widget );
			update_option( 'yay_currency_show_currency_code_in_widget', $is_show_currency_code_in_widget );
			update_option( 'yay_currency_widget_size', $widget_size );
		}
		return $new_instance;
	}
}
