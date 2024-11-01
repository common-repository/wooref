<?php
/*
* Plugin Name:       WooRef
* Description:       WooRef is a Wordpress plugin that allows you to view and track WooCommerce sales comes from any referral site.
* Version:           1.0.1
* Author:            Leo Pizzolante
* Author URI:        https://www.pizzolante.biz
* Developer:			Leo Pizzolante
* Developer URI:		https://www.pizzolante.biz
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       wooref
* Domain Path:       /languages
*/
?>

<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

final class WooRef {

	private static $wooref_donation_url = "https://paypal.me/leoz/";
	private static $wooref_url_schema = "http://";
	public static  $wooref_default_cookie_expire =  60;

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'wooref_install' ) );
		register_uninstall_hook( __FILE__, array( $this, 'wooref_uninstall' ) );
		add_action( 'plugins_loaded', array( $this, 'wooref_load_plugin_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'wooref_register_menu' ) );
		add_action( 'admin_init', array( $this, 'wooref_register_settings' ) );
		add_filter( 'plugin_row_meta', array( $this, 'wooref_description_more' ), 10, 2 );
		add_action( 'init',array( $this, 'wooref_check_cookie' ) );
		add_action( "woocommerce_checkout_update_order_meta", array( $this, 'wooref_woo_addmeta_order' ), 10, 1);
		add_action( "woocommerce_admin_order_data_after_order_details",  array( $this, 'wooref_woo_viewmeta_order' ), 10, 1);
		add_action( "woocommerce_order_status_pending_to_processing_notification",  array( $this, 'wooref_load_email_settings' ), 10, 1);
		add_action( "woocommerce_order_status_pending_to_on-hold_notification",  array( $this, 'wooref_load_email_settings' ), 10, 1);
	}


	static function wooref_install() {
		add_option( 'wooref_cookie_expire', self::$wooref_default_cookie_expire);
		add_option( 'wooref_trackme', false);
		add_option( 'wooref_track_admin_email', true);
		add_option( 'wooref_track_user_email', false);

	}
	static function wooref_uninstall() {
		delete_option( 'wooref_cookie_expire' );
		delete_option( 'wooref_trackme' );
		delete_option( 'wooref_track_user_email' );
		delete_option( 'wooref_track_admin_email' );
	}

	public function wooref_register_menu() {
		add_menu_page( 
			__( 'WooRef', 'wooref' ), 
			__( 'WooRef', 'wooref' ), 
			'manage_options', 
			'wooref-settings', 
			array( $this, 'wooref_page_settings' ),
			'dashicons-networking',
			58
			);	
	}

	public function wooref_page_settings() {
		self::wooref_check_woocommerce_isdisabiled();
		self::wooref_check_self_isdisabiled();
		?>
		<div class="wrap">
			<h1><?php _e( 'WooRef - Configuration', 'wooref' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wooref_activation_group' ); ?>
				<?php do_settings_sections( 'wooref_activation_group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Enable track', 'wooref' ); ?></th>
						<td>
							<input type="checkbox" id="wooref_trackme" name="wooref_trackme" value="1" <?php checked( 1, get_option( 'wooref_trackme' ), true ); ?> />
							<label for="wooref_trackme"><?php _e( 'Record incoming visits', 'wooref' ); ?></label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Cookie Expiration (days)', 'wooref' ); ?></th>
						<td><input type="number" id="wooref_cookie_expire" min="1" max="60" name="wooref_cookie_expire" value="<?php echo esc_attr( get_option('wooref_cookie_expire') ); ?>" /></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Track WooCommerce (admin)', 'wooref' ); ?></th>
						<td>
							<input type="checkbox" id="wooref_track_admin_email" name="wooref_track_admin_email" value="1"<?php checked( 1, get_option( 'wooref_track_admin_email' ), true ); ?> />
							<label for="wooref_track_admin_email"><?php _e( 'Displays the referral in the order summary mail for the administrator', 'wooref' ); ?></label>
						</td>
					</tr>        
					<tr valign="top">
						<th scope="row"><?php _e( 'Track WooCommerce (user)', 'wooref' ); ?></th>
						<td>
							<input type="checkbox" id="wooref_track_user_email" name="wooref_track_user_email" value="1"<?php checked( 1, get_option( 'wooref_track_user_email' ), true ); ?> />
							<label for="wooref_track_user_email"><?php _e( 'Displays the referral in the order summary mail for the user', 'wooref' ); ?></label>

						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php 
	}

	public function wooref_register_settings() {
		register_setting( 'wooref_activation_group', 'wooref_trackme' );
		register_setting( 'wooref_activation_group', 'wooref_track_user_email' );
		register_setting( 'wooref_activation_group', 'wooref_track_admin_email' );
		register_setting( 'wooref_activation_group', 'wooref_cookie_expire' );
	}

	public function wooref_check_cookie() {
		if ( get_option('wooref_trackme') == true ) {
			$site_ref = preg_replace('#^www\.(.+\.)#i', '$1', parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST));
			$my_site = preg_replace('#^www\.(.+\.)#i', '$1', parse_url(network_site_url(), PHP_URL_HOST));

			if ( ( !is_admin() ) && ( !isset($_COOKIE['wooref']) ) && ( $site_ref != $my_site ) ) {
				if (strpos($_SERVER["HTTP_REFERER"], 'http') !== false) {
					$cookie_day_duration = (int) get_option('wooref_cookie_expire');
					setcookie( 'wooref', $site_ref, time()+3600*24*$cookie_day_duration, COOKIEPATH, COOKIE_DOMAIN, false);
				}
			}
		}

	}

	public function wooref_woo_addmeta_order( $order, $posted ) {
		$site_ref = preg_replace('#^www\.(.+\.)#i', '$1', parse_url(self::$wooref_url_schema.$_COOKIE['wooref'], PHP_URL_HOST));
		if ( isset( $_COOKIE['wooref'] ) ) {
			update_post_meta( $order, '_wooref_site_ref', $site_ref );
		}
	}

	public function wooref_woo_viewmeta_order( $order ){ 
		if (!empty(get_post_meta( $order->id, '_wooref_site_ref', true ))) {
			?>
			<div class="order_data_column">
				<h4><?php _e( 'Referral', 'wooref' ); ?></h4>
				<?php 
				_e( self::$wooref_url_schema.get_post_meta( $order->id, '_wooref_site_ref', true ), 'wooref' ); ?>
			</div>
			<?php
		}
	}

	public function wooref_load_email_settings() {
		add_action( 'woocommerce_email_before_order_table', array( $this, 'wooref_add_email_filter' ), 10, 2 );
	}

	public function wooref_add_email_filter( $order, $sent_to_admin ) {
		$is_admin_email	= get_option( 'wooref_track_admin_email' );
		$is_user_email		= get_option( 'wooref_track_user_email' );
		if (!empty(get_post_meta( $order->id, '_wooref_site_ref', true ))) {
			$ref = self::$wooref_url_schema.get_post_meta( $order->id, '_wooref_site_ref', true );
			if ( ( $is_admin_email == true ) && ( $sent_to_admin ) ) {
				echo '<p><strong>'. __('Referral:', 'wooref') . '</strong> ' . esc_html_e( $ref, 'wooref') . '</p>';
			} 
			if ( ( $is_user_email == true ) && ( ! $sent_to_admin ) ) {
				echo '<p><strong>'. __('Referral:', 'wooref') . '</strong> ' . esc_html_e( $ref, 'wooref') . '</p>';
			}
		}
	}

	public function wooref_description_more( $links, $file ) {
		if ( strpos( $file, plugin_basename( __FILE__ ) ) !== false ) {
			$new_links = array(
				'donate' => '<a href="'.self::$wooref_donation_url.'" target="_blank">'. __( 'Make a donation!', 'wooref' ) .'</a>'
			// 'doc' => '<a href="#" target="_blank">Documentazione</a>'
				);

			$links = array_merge( $links, $new_links );
		}
		return $links;
	}

	public function wooref_load_plugin_textdomain() {
		load_plugin_textdomain( 'wooref', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	public function wooref_check_woocommerce_isdisabiled() {
		if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			self::wooref_message_woocommerce_isdisabled();
		}
	}

	public function wooref_check_self_isdisabiled() {
		if ( get_option('wooref_trackme') != true ) {
			self::wooref_message_isdisabled();
		}
	}

	public function wooref_message_woocommerce_isdisabled() {
		$class = 'notice notice-error';
		$message = __( 'WooRef cannot trace orders as WooCommerce seems to be disabled.', 'wooref' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 

	}

	public function wooref_message_isdisabled() {
		$class = 'notice notice-error';
		$message = __( 'WooRef does not seem active. You will not receive notification about the origin of your orders.', 'wooref' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) ); 
	}

}

$wooref = new WooRef();
