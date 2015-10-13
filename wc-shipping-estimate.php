<?php
/**
 * Plugin Name: WooCommerce Shipping Estimates
 * Plugin URI: http://www.skyverge.com/product/woocommerce-shipping-estimates/
 * Description: Displays a shipping estimate for each method on the cart / checkout page
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com/
 * Version: 1.0.0
 * Text Domain: wc-shipping-estimate
 *
 * Copyright: (c) 2015 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Shipping-Estimate
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2015, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Description
 *
 * Displays the estimated shipping length (e.g., 2-4 days) for each shipping method
 * on the cart and checkout pages.
 *
 * Can optionally use only a minimum or maximum shipping length (e.g., "up to 4 days" or "at least 2 days")
 * if only one value is set.
 */


class WC_Shipping_Estimate {

	const VERSION = '1.0.0';


	/** @var WC_Shipping_Estimate single instance of this plugin */
	protected static $instance;


	public function __construct() {

		// load translations
		add_action( 'init', array( $this, 'load_translation' ) );

		// add delivery estimates on the frontend
		add_action( 'woocommerce_cart_shipping_method_full_label', array( $this, 'render_estimate_label' ), 10, 2 );

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {

			// add format selector
			add_filter( 'woocommerce_shipping_settings', array( $this, 'add_format_selector' ) );

			// add settings table
			add_action( 'woocommerce_settings_shipping_options_end', array( $this, 'add_settings' ) );

			// save the new options
			add_action( 'woocommerce_settings_save_shipping', array( $this, 'process_method_estimate' ) );

			// add plugin links
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_links' ) );

			// run every time
			$this->install();
		}
	}


	/** Helper methods ***************************************/


	/**
	 * Main WC_Shipping_Estimate Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wc_shipping_estimate()
	 * @return WC_Shipping_Estimate
 	*/
	public static function instance() {
    	if ( is_null( self::$instance ) ) {
        	self::$instance = new self();
    	}
    	return self::$instance;
	}


	/**
	 * Adds plugin page links
	 *
	 * @since 1.0.0
	 * @param array $links all plugin links
	 * @return array $links all plugin links + our custom links (i.e., "Settings")
	 */
	public function add_plugin_links( $links ) {

		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . '">' . __( 'Configure', 'wc-shipping-estimate' ) . '</a>',
			'<a href="http://github.com/skyverge/woocommerce-shipping-estimate/">' . __( 'Support', 'wc-shipping-estimate' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}


	/**
	 * Load Translations
	 *
	 * @since 1.0.0
	 */
	public function load_translation() {
		// localization
		load_plugin_textdomain( 'wc-shipping-estimate', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}


	/** Plugin methods ***************************************/


	/**
	 * Displays the updated shipping method label
	 *
	 * @since 1.0.0
	 * @param string $label the existing method label
	 * @param \WC_Shipping_Rate $method the shipping method
	 * @return string $label the updated label
	 */
	public function render_estimate_label( $label, $method ) {

		$method_estimate_from = get_option( 'woocommerce_shipping_method_estimate_from', array() );
		$method_estimate_to = get_option( 'woocommerce_shipping_method_estimate_to', array() );

		$days_from_setting = $method_estimate_from[ $method->method_id ];
		$days_to_setting = $method_estimate_to[ $method->method_id ];

		// build me a label!
		$label .= '<br /><small>';

		// Should we display days or dates to the customer?
		if ( 'dates' === get_option( 'wc_shipping_estimate_format', 'days' ) ) {
			$label = $this->generate_delivery_estimate_dates( $days_from_setting, $days_to_setting, $label );
		} else {
			$label = $this->generate_delivery_estimate_days( $days_from_setting, $days_to_setting, $label );
		}

		// label complete
		$label .= '</small>';

		return $label;
	}


	/**
	 * Generate the delivery estimate display for the label
	 *
	 * @since 1.0.0
	 * @param int $days_from_setting the minimum shipping estimate
	 * @param int $days_to_setting the maximum shipping estimate
	 * @param string $label the label we're in the process of updating
	 * @return string $label the updated label with the delivery estimate
	 */
	private function generate_delivery_estimate_days( $days_from_setting, $days_to_setting, $label ) {

		// Filter the "days" value so it can be changed (i.e., use a date instead)
		$days_from = apply_filters( 'wc_shipping_estimate_days_from', $days_from_setting  );
		$days_to = apply_filters( 'wc_shipping_estimate_days_to', $days_to_setting );

		// Determine how we should format the estimate
		if ( ! empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			// Sanity check: we can't show something like "Delivery estimate: 5 - 2 days" ;)
			if ( $days_to_setting <= $days_from_setting ) {

				/* translators: %1$s (number) is maximum shipping estimate, %2$s is label (day(s)) */
				$label .= esc_html( sprintf( __( 'Delivery estimate: up to %1$s %2$s', 'wc-shipping-estimate' ), $days_to, $this->get_estimate_label( $days_to ) ) );

			} else {

				/* translators: %1$s (number) is minimum shipping estimate, %2$s (number) is maximum shipping estimate, %$3s is label (day(s)) */
				$label .= esc_html( sprintf( __( 'Delivery estimate: %1$s - %2$s %3$s', 'wc-shipping-estimate' ), $days_from, $days_to, $this->get_estimate_label( $days_to ) ) );

			}

		} elseif ( empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			/* translators: %1$s (number) is maximum shipping estimate, %2$s is label (day(s)) */
			$label .= esc_html( sprintf( __( 'Delivery estimate: up to %1$s %2$s', 'wc-shipping-estimate' ), $days_to, $this->get_estimate_label( $days_to ) ) );

		} elseif ( ! empty( $days_from_setting ) && empty( $days_to_setting ) ) {

			/* translators: %1$s (number) is minimum shipping estimate, %2$s is label (day(s)) */
			$label .= esc_html( sprintf( __( 'Delivery estimate: at least %1$s %2$s', 'wc-shipping-estimate' ), $days_from, $this->get_estimate_label( $days_from ) ) );

		}

		return $label;
	}


	/**
	 * Generate the delivery estimate display for the label using dates instead of days
	 *
	 * @since 1.0.0
	 * @param int $days_from_setting the minimum shipping estimate
	 * @param int $days_to_setting the maximum shipping estimate
	 * @param string $label the label we're in the process of updating
	 * @return string $label the updated label with the delivery dates
	 */
	private function generate_delivery_estimate_dates( $days_from_setting, $days_to_setting, $label ) {

		// Filter the "dates" value so it can be changed
		$days_from = apply_filters( 'wc_shipping_estimate_dates_from', date_i18n( 'F j', strtotime( $days_from_setting . 'days' ) )  );
		$days_to = apply_filters( 'wc_shipping_estimate_dates_to', date_i18n( 'F j', strtotime( $days_to_setting . 'days' ) ) );

		// Determine how we should format the estimate
		if ( ! empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			// Sanity check: we can't show something like "Delivery estimate: 5 - 2 days" ;)
			if ( $days_to_setting <= $days_from_setting ) {

				/* translators: %s (date) is latest shipping estimate */
				$label .= esc_html( sprintf( __( 'Estimated delivery by %s', 'wc-shipping-estimate' ), $days_to ) );

			} else {

				/* translators: %1$s (date) is earliest shipping estimate, %2$s (date) is latest shipping estimate */
				$label .= esc_html( sprintf( __( 'Estimated delivery: %1$s - %2$s', 'wc-shipping-estimate' ), $days_from, $days_to ) );

			}

		} elseif ( empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			/* translators: %s (date) is latest shipping estimate */
			$label .= esc_html( sprintf( __( 'Estimated delivery by %s', 'wc-shipping-estimate' ), $days_to ) );

		} elseif ( ! empty( $days_from_setting ) && empty( $days_to_setting ) ) {

			/* translators: %s (date) is earliest shipping estimate */
			$label .= esc_html( sprintf( __( 'Delivery on or after %s', 'wc-shipping-estimate' ), $days_from ) );

		}

		return $label;
	}


	/**
	 * Gets the label for the shipping estimate; defaults to "day(s)"
	 *
	 * @since 1.0.0
	 * @param int $days_to the maximum shipping estimate
	 * @return string $estimate_label the label for the estimate
	 */
	private function get_estimate_label( $days ) {

		$estimate_label = $days > 1 ? __( 'days', 'wc-shipping-estimate' ) : __( 'day', 'wc-shipping-estimate' );
		return apply_filters( 'wc_shipping_estimate_label', $estimate_label, $days );
	}


	/**
	 * Add format selector for estimates
	 *
	 * @since 1.0.0
	 * @param array $settings the WooCommerce shipping settings
	 * @return array $updated_settings the updated settings with ours added
	 */
	public function add_format_selector( $settings ) {

		$updated_settings = array();

		$new_settings = array(
			array(
				'id'		=> 'wc_shipping_estimate_format',
				'type'		=> 'radio',
				'title'		=> __( 'Shipping Estimate Format', 'wc-shipping-estimate' ),
				'options'	=> array(
					'days'	=> __( 'Display estimate in days' , 'wc-shipping-estimate' ),
					'dates'	=> __( 'Display estimate using dates' , 'wc-shipping-estimate' ),
				),
				'default'		=> 'days',
				'desc'		=> __( 'This changes the way estimates are shown to customers.', 'wc-shipping-estimate' ),
				'desc_tip'	=> true,
			),
		);

		foreach ( $settings as $setting ) {

			$updated_settings[] = $setting;

			if ( isset( $setting['type'] ) && 'shipping_methods' === $setting['type'] ) {
				$updated_settings = array_merge( $updated_settings, $new_settings );
			}
		}

		return $updated_settings;
	}


	/**
	 * Create WC Shipping Estimate settings table
	 *
	 * @since 1.0.0
	 */
	public function add_settings() {

		// Get the estimates if they're saved already
		$method_estimate_from = get_option( 'woocommerce_shipping_method_estimate_from', array() );
		$method_estimate_to = get_option( 'woocommerce_shipping_method_estimate_to', array() );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Shipping Estimate', 'wc-shipping-estimate' ) ?></th>
			<td class="forminp">
				<table class="wc_shipping widefat wp-list-table" cellspacing="0">
					<thead>
						<tr>
							<th class="name" style="padding-left: 2% !important"><?php esc_html_e( 'Name', 'wc-shipping-estimate' ); ?></th>
							<th class="id"><?php esc_html_e( 'ID', 'wc-shipping-estimate' ); ?></th>
							<th class="day-from"><?php esc_html_e( 'From (days)', 'wc-shipping-estimate' ); ?> <span class="tips" data-tip="<?php echo esc_attr( __( 'The earliest estimated arrival. Can be left blank.', 'wc-shipping-estimate' ) ); ?>">[?]</span></th>
							<th class="day-to"><?php esc_html_e( 'To (days)', 'wc-shipping-estimate' ); ?> <span class="tips" data-tip="<?php echo esc_attr( __( 'The latest estimated arrival. Can be left blank.', 'wc-shipping-estimate' ) ); ?>">[?]</span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( WC()->shipping->load_shipping_methods() as $key => $method ) : ?>
							<tr>
								<td style="padding-left: 2%" class="name">
									<?php if ( $method->has_settings ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . strtolower( get_class( $method ) ) ) ); ?>"><?php endif; ?>
									<?php echo esc_html( $method->get_title() ); ?>
									<?php if ( $method->has_settings ) : ?></a><?php endif; ?>
								</td>
								<td class="id">
									<?php echo esc_attr( $method->id ); ?>
								</td>
								<td class="day-from">
									<input type="number" step="1" min="0" name="method_estimate_from[<?php echo esc_attr( $method->id ); ?>]" value="<?php echo isset( $method_estimate_from[ $method->id ] ) ? $method_estimate_from[ $method->id ] : ''; ?>" />
								</td>
								<td width="1%" class="day-to">
									<input type="number" step="1" min="0" name="method_estimate_to[<?php echo esc_attr( $method->id ); ?>]" value="<?php echo isset( $method_estimate_to[ $method->id ] ) ? $method_estimate_to[ $method->id ] : ''; ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="4" style="padding-left: 2% !important"><span class="description"><?php esc_html_e( 'Set the estimated range of days required for each method.', 'wc-shipping-estimate' ); ?></span></th>
						</tr>
					</tfoot>
				</table>
			</td>
		</tr>
		<?php
	}


	/**
	 * Processes and saves the method's "from" and "to" values for the estimate
	 *
	 * @since 1.0.0
	 */
	public function process_method_estimate() {

		global $current_section;

		// Bail if we're not in the "Shipping Options" section
		if ( $current_section ) {
			return;
		}

		$estimate_from = isset( $_POST['method_estimate_from'] ) ? $_POST['method_estimate_from'] : '';
		$estimate_to = isset( $_POST['method_estimate_to'] ) ? $_POST['method_estimate_to'] : '';

		$method_estimate_from = array();
		$method_estimate_to = array();

		if ( is_array( $estimate_from ) && count( $estimate_from ) > 0 ) {

			foreach ( $estimate_from as $method_id => $value ) {
				if ( ! empty( $value ) ) {
					$value = absint( $value );
				}

				$method_estimate_from[ $method_id ] = $value;
			}
		}

		if ( is_array( $estimate_to ) && count( $estimate_to ) > 0 ) {

			foreach ( $estimate_to as $method_id => $value ) {
				if ( ! empty( $value ) ) {
					$value = absint( $value );
				}

				$method_estimate_to[ $method_id ] = $value;
			}
		}

		update_option( 'woocommerce_shipping_method_estimate_from', $method_estimate_from );
		update_option( 'woocommerce_shipping_method_estimate_to', $method_estimate_to );
	}


	/** Lifecycle methods ***************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @since 1.0.0
	 */
	private function install() {

		// get current version to check for upgrade
		$installed_version = get_option( 'wc_shipping_estimate_version' );

		// force upgrade to 1.0.0
		if ( ! $installed_version ) {
			$this->upgrade( '1.0.0' );
		}

		// upgrade if installed version lower than plugin version
		if ( -1 === version_compare( $installed_version, self::VERSION ) ) {
			$this->upgrade( self::VERSION );
		}
	}


	/**
	 * Perform any version-related changes.
	 *
	 * @since 1.0.0
	 * @param int $installed_version the currently installed version of the plugin
	 */
	private function upgrade( $version ) {

		// update the installed version option
		update_option( 'wc_shipping_estimate_version', $version );
	}

} // end \WC_Shipping_Estimate class


/**
 * Returns the One True Instance of WC_Shipping_Estimate
 *
 * @since 1.0.0
 * @return WC_Shipping_Estimate
 */
function wc_shipping_estimate() {
    return WC_Shipping_Estimate::instance();
}

// fire it up!
wc_shipping_estimate();
