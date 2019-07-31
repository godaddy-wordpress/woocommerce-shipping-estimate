<?php
/**
 * WooCommerce Shipping Estimate
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Shipping Estimate to newer
 * versions in the future. If you wish to customize WooCommerce Shipping Estimate for your
 * needs please refer to http://skyverge.com/products/woocommerce-shipping-estimate/ for more information.
 *
 * @package   WC-Shipping-Estimate
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2015-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\ShippingEstimates;

use SkyVerge\WooCommerce\PluginUpdater as Updater;

defined( 'ABSPATH' ) or exit;

/**
 * Plugin Description
 *
 * Displays the estimated shipping length (e.g., 2-4 days) for each shipping method
 * on the cart and checkout pages.
 *
 * Can optionally use only a minimum or maximum shipping length (e.g., "up to 4 days" or "at least 2 days")
 * if only one value is set.
 */

class Plugin {


	const VERSION = '2.2.1-dev.1';

	/** @var Plugin single instance of this plugin */
	protected static $instance;

	/** @var Updater\License $license license class instance */
	protected $license;


	/**
	 * Plugin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// load translations
		add_action( 'init', [ $this, 'load_translation' ] );

		// add delivery estimates on the frontend
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this, 'render_estimate_label' ], 10, 2 );

		if ( is_admin() && ! is_ajax() ) {

			// add format selector
			add_filter( 'woocommerce_shipping_settings', [ $this, 'add_format_selector' ] );

			// add settings table
			add_action( 'woocommerce_settings_wc_shipping_estimates_end', [ $this, 'add_settings' ] );

			// save the new options
			add_action( 'woocommerce_settings_save_shipping', [ $this, 'process_method_estimate' ] );

			// add plugin links
			add_filter( 'plugin_action_links_' . plugin_basename( $this->get_plugin_file() ), [ $this, 'add_plugin_links' ] );
			add_filter( 'plugin_row_meta',[ $this, 'add_plugin_row_meta' ], 10, 2 );

			// run every time
			$this->install();
		}

		$this->includes();
	}


	/**
	 * Loads plugin files.
	 *
	 * @since 2.2.0
	 */
	public function includes() {

		if ( ! class_exists( 'Updater\\License' ) ) {
			require_once( $this->get_plugin_path() . '/lib/skyverge/updater/class-skyverge-plugin-license.php');
		}

		// item ID is from skyverge.com download WP_Post ID
		$this->license = new Updater\License( $this->get_plugin_file(), $this->get_plugin_path(), $this->get_plugin_url(), $this->get_plugin_name(), $this->get_version(), 4598 );
	}


	/** Plugin methods ***************************************/


	/**
	 * Displays the updated shipping method label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label the existing method label
	 * @param \WC_Shipping_Rate $method the shipping method
	 * @return string - the updated label
	 */
	public function render_estimate_label( $label, $method ) {

		// get the instance ID in case this is a zone method
		$instance_id = isset( $method->instance_id ) ? $method->instance_id : (int) substr( $method->id, strpos( $method->id, ':' ) + 1 );
		$method_id   = $instance_id ? (int) $instance_id : $method->method_id;

		$method_estimate_from = get_option( 'wc_shipping_method_estimate_from', [] );
		$method_estimate_to   = get_option( 'wc_shipping_method_estimate_to', [] );

		$days_from_setting = isset( $method_estimate_from[ $method_id ] ) ? $method_estimate_from[ $method_id ] : 0;
		$days_to_setting   = isset( $method_estimate_to[ $method_id ] )   ? $method_estimate_to[ $method_id ]   : 0;

		// bail if there are no changes to make to the label
		if ( ! $days_from_setting && ! $days_to_setting ) {
			return $label;
		}

		// build me a label!
		$label .= '<br /><small>';

		// Should we display days or dates to the customer?
		if ( 'dates' === get_option( 'wc_shipping_estimate_format', 'days' ) ) {
			$label = $this->generate_delivery_estimate_dates( $days_from_setting, $days_to_setting, $label, $method );
		} else {
			$label = $this->generate_delivery_estimate_days( $days_from_setting, $days_to_setting, $label, $method );
		}

		// label complete
		$label .= '</small>';

		return $label;
	}


	/**
	 * Generate the delivery estimate display for the label.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days_from_setting the minimum shipping estimate
	 * @param int $days_to_setting the maximum shipping estimate
	 * @param string $label the label we're in the process of updating
	 * @param \WC_Shipping_Rate $method the shipping method
	 * @return string $label the updated label with the delivery estimate
	 */
	private function generate_delivery_estimate_days( $days_from_setting, $days_to_setting, $label, $method ) {

		// Filter the "days" value in case you want to add a buffer or whatever ¯\_(ツ)_/¯
		$days_from = apply_filters( 'wc_shipping_estimate_days_from', $days_from_setting );
		$days_to   = apply_filters( 'wc_shipping_estimate_days_to', $days_to_setting );

		// we'll treat pickup differently than other methods for labeling
		$local_pickup   = ( 'local_pickup' === substr( $method->id, 0, 12 ) );
		$estimate_label = $this->get_estimate_label( $days_to );

		// Determine how we should format the estimate
		if ( ! empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			// Sanity check: we can't show something like "Delivery estimate: 5 - 2 days" ;)
			if ( $days_to_setting <= $days_from_setting ) {

				/* translators: %1$s (number) is maximum shipping estimate, %2$s is label (day(s)) */
				$label .= $local_pickup ? sprintf( __( 'Collection estimate: up to %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_to, $estimate_label ) : sprintf( __( 'Delivery estimate: up to %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_to, $estimate_label );

			} else {

				/* translators: %1$s (number) is minimum shipping estimate, %2$s (number) is maximum shipping estimate, %$3s is label (day(s)) */
				$label .= $local_pickup ? sprintf( __( 'Collection estimate: %1$s - %2$s %3$s', 'woocommerce-shipping-estimate' ), $days_from, $days_to, $estimate_label ) : sprintf( __( 'Delivery estimate: %1$s - %2$s %3$s', 'woocommerce-shipping-estimate' ), $days_from, $days_to, $estimate_label );
			}

		} elseif ( empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			/* translators: %1$s (number) is maximum shipping estimate, %2$s is label (day(s)) */
			$label .= $local_pickup ? sprintf( __( 'Collection estimate: up to %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_to, $estimate_label ) : sprintf( __( 'Delivery estimate: up to %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_to, $estimate_label );

		} elseif ( ! empty( $days_from_setting ) && empty( $days_to_setting ) ) {

			// change the label to be based on "days from" instead since it's the only value
			$estimate_label = $this->get_estimate_label( $days_from );

			/* translators: %1$s (number) is minimum shipping estimate, %2$s is label (day(s)) */
			$label .= $local_pickup ? sprintf( __( 'Collection estimate: at least %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_from, $estimate_label ) : sprintf( __( 'Delivery estimate: at least %1$s %2$s', 'woocommerce-shipping-estimate' ), $days_from, $estimate_label );
		}

		return $label;
	}


	/**
	 * Generate the delivery estimate display for the label using dates instead of days.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days_from_setting the minimum shipping estimate
	 * @param int $days_to_setting the maximum shipping estimate
	 * @param \WC_Shipping_Rate $method the shipping method
	 * @param string $label the label we're in the process of updating
	 * @return string $label the updated label with the delivery dates
	 */
	private function generate_delivery_estimate_dates( $days_from_setting, $days_to_setting, $label, $method ) {

		// Filter the "dates" value so it can be changed
		$days_from = apply_filters( 'wc_shipping_estimate_dates_from', date_i18n( 'F j', strtotime( $days_from_setting . 'days' ) ) );
		$days_to   = apply_filters( 'wc_shipping_estimate_dates_to', date_i18n( 'F j', strtotime( $days_to_setting . 'days' ) ) );

		// we'll treat pickup differently than other methods for labeling
		$local_pickup = ( 'local_pickup' === substr( $method->id, 0, 12 ) );

		// Determine how we should format the estimate
		if ( ! empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			// Sanity check: we can't show something like "Estimated delivery: January 5 to January 1" ;)
			if ( $days_to_setting <= $days_from_setting ) {

				/* translators: %s (date) is latest shipping estimate */
				$label .= $local_pickup ? sprintf( __( 'Estimated collection by %s', 'woocommerce-shipping-estimate' ), $days_to ) : sprintf( __( 'Estimated delivery by %s', 'woocommerce-shipping-estimate' ), $days_to );

			} else {

				/* translators: %1$s (date) is earliest shipping estimate, %2$s (date) is latest shipping estimate */
				$label .= $local_pickup ? sprintf( __( 'Estimated collection: %1$s - %2$s', 'woocommerce-shipping-estimate' ), $days_from, $days_to ) : sprintf( __( 'Estimated delivery: %1$s - %2$s', 'woocommerce-shipping-estimate' ), $days_from, $days_to );
			}

		} elseif ( empty( $days_from_setting ) && ! empty( $days_to_setting ) ) {

			/* translators: %s (date) is latest shipping estimate */
			$label .= $local_pickup ? sprintf( __( 'Estimated collection by %s', 'woocommerce-shipping-estimate' ), $days_to ) : sprintf( __( 'Estimated delivery by %s', 'woocommerce-shipping-estimate' ), $days_to );

		} elseif ( ! empty( $days_from_setting ) && empty( $days_to_setting ) ) {

			/* translators: %s (date) is earliest shipping estimate */
			$label .= $local_pickup ? sprintf( __( 'Collection on or after %s', 'woocommerce-shipping-estimate' ), $days_from ) : sprintf( __( 'Delivery on or after %s', 'woocommerce-shipping-estimate' ), $days_from );
		}

		return $label;
	}


	/**
	 * Gets the label for the shipping estimate; defaults to "day(s)".
	 *
	 * @since 1.0.0
	 *
	 * @param int $days the maximum shipping estimate
	 * @return string $estimate_label the label for the estimate
	 */
	private function get_estimate_label( $days ) {

		$estimate_label = _n( 'day', 'days', $days, 'woocommerce-shipping-estimate' );
		return apply_filters( 'wc_shipping_estimate_label', $estimate_label, $days );
	}


	/**
	 * Add format selector for estimates.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings the WooCommerce shipping settings
	 * @return array $updated_settings the updated settings with ours added
	 */
	public function add_format_selector( $settings ) {

		$new_settings = [
			[
				'title' => __( 'Shipping Estimates', 'woocommerce-shipping-estimate' ),
				'type'  => 'title',
				'id'    => 'wc_shipping_estimates',
			],
			[
				'id'       => 'wc_shipping_estimate_format',
				'type'     => 'radio',
				'title'    => __( 'Shipping Estimate Format', 'woocommerce-shipping-estimate' ),
				'options'  => [
					'days'  => __( 'Display estimate in days', 'woocommerce-shipping-estimate' ),
					'dates' => __( 'Display estimate using dates', 'woocommerce-shipping-estimate' ),
				],
				'default'  => 'days',
				'desc'     => __( 'This changes the way estimates are shown to customers.', 'woocommerce-shipping-estimate' ),
				'desc_tip' => true,
			],
			[
				'type' => 'sectionend',
				'id'   => 'wc_shipping_estimates',
			],
		];

		return array_merge( $settings, $new_settings );
	}


	/**
	 * Create WC Shipping Estimate settings table.
	 *
	 * Thanks WC core! This is modeled off "Shipping Methods" table.
	 *
	 * @since 1.0.0
	 */
	public function add_settings() {

		// Get the estimates if they're saved already
		$method_estimate_from = get_option( 'wc_shipping_method_estimate_from', [] );
		$method_estimate_to   = get_option( 'wc_shipping_method_estimate_to', [] );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Estimate Ranges', 'woocommerce-shipping-estimate' ) ?></th>
			<td class="forminp">
				<table class="wc_shipping widefat wp-list-table" cellspacing="0">
				<?php $zones = \WC_Shipping_Zones::get_zones(); ?>
				<?php if ( ! empty( $zones ) ) : ?>
				<?php foreach ( $zones as $zone_id => $zone_data ) : ?>
					<?php
						$zone = \WC_Shipping_Zones::get_zone( $zone_id );
						$zone_methods = $zone->get_shipping_methods();
						if ( ! empty( $zone_methods ) ) :
					?>
					<thead>
						<tr style="background: #e9e9e9;">
							<th colspan="4" style="text-align: center; border: 1px solid #e1e1e1;">
								<?php echo sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone->get_id() ) ), $zone->get_zone_name() ); ?>
								<?php esc_html_e( 'Methods', 'woocommerce-shipping-estimate' ); ?>
							</th>
						</tr>
						<tr>
							<th class="name" style="padding-left: 2% !important"><?php esc_html_e( 'Name', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="type"><?php esc_html_e( 'Type', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="day-from"><?php esc_html_e( 'From (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The earliest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
							<th class="day-to"><?php esc_html_e( 'To (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The latest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $zone->get_shipping_methods() as $instance_id => $method ) : ?>
						<tr>
							<td style="padding-left: 2%" class="name">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $instance_id ) ); ?>"><?php echo esc_html( $method->get_title() ); ?></a>
							</td>
							<td class="type">
								<?php echo esc_html( $method->get_method_title() ); ?>
							</td>
							<td class="day-from">
								<input type="number" step="1" min="0" name="method_estimate_from[<?php echo esc_attr( $instance_id ); ?>]" value="<?php echo isset( $method_estimate_from[ $instance_id ] ) ? $method_estimate_from[ $instance_id ] : ''; ?>" />
							</td>
							<td class="day-to">
								<input type="number" step="1" min="0" name="method_estimate_to[<?php echo esc_attr( $instance_id ); ?>]" value="<?php echo isset( $method_estimate_to[ $instance_id ] ) ? $method_estimate_to[ $instance_id ] : ''; ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php endif; ?>

				<?php $world_zone = \WC_Shipping_Zones::get_zone( 0 ); ?>
				<?php $world_zone_methods = $world_zone->get_shipping_methods(); ?>
				<?php if ( ! empty( $world_zone_methods ) ) : ?>
					<thead>
						<tr style="background: #e9e9e9;">
							<th colspan="4" style="text-align: center; border: 1px solid #e1e1e1;">
								<?php $zone_name = __( 'Rest of the World', 'woocommerce-shipping-estimate' ); ?>
								<?php echo sprintf( '<a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=0' ) ), $zone_name ); ?>
								<?php esc_html_e( 'Methods', 'woocommerce-shipping-estimate' ); ?>
							</th>
						</tr>
						<tr>
							<th class="name" style="padding-left: 2% !important"><?php esc_html_e( 'Name', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="type"><?php esc_html_e( 'Type', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="day-from"><?php esc_html_e( 'From (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The earliest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
							<th class="day-to"><?php esc_html_e( 'To (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The latest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $world_zone_methods as $instance_id => $method ) : ?>
						<tr>
							<td style="padding-left: 2%" class="name">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $instance_id ) ); ?>"><?php echo esc_html( $method->get_title() ); ?></a>
							</td>
							<td class="type">
								<?php echo esc_html( $method->get_method_title() ); ?>
							</td>
							<td class="day-from">
								<input type="number" step="1" min="0" name="method_estimate_from[<?php echo esc_attr( $instance_id ); ?>]" value="<?php echo isset( $method_estimate_from[ $instance_id ] ) ? $method_estimate_from[ $instance_id ] : ''; ?>" />
							</td>
							<td class="day-to">
								<input type="number" step="1" min="0" name="method_estimate_to[<?php echo esc_attr( $instance_id ); ?>]" value="<?php echo isset( $method_estimate_to[ $instance_id ] ) ? $method_estimate_to[ $instance_id ] : ''; ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<?php endif; ?>
					<?php
						$methods = WC()->shipping->get_shipping_methods();
						unset( $methods['flat_rate'], $methods['free_shipping'], $methods['local_pickup'] );
						if ( ! empty( $methods ) ) :
					?>
					<thead>
						<tr style="background: #e9e9e9;">
							<th colspan="4" style="text-align: center; border: 1px solid #e1e1e1;"><?php esc_html_e( 'Other Methods', 'woocommerce-shipping-estimate' ); ?></th>
						</tr>
						<tr>
							<th class="name" style="padding-left: 2% !important"><?php esc_html_e( 'Name', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="id"><?php esc_html_e( 'ID', 'woocommerce-shipping-estimate' ); ?></th>
							<th class="day-from"><?php esc_html_e( 'From (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The earliest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
							<th class="day-to"><?php esc_html_e( 'To (days)', 'woocommerce-shipping-estimate' ); ?> <?php echo wc_help_tip( __( 'The latest estimated arrival. Can be left blank.', 'woocommerce-shipping-estimate' ) ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $methods as $method_id => $method ) : ?>
							<tr>
								<td style="padding-left: 2%" class="name">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . $method_id ) ); ?>">
									<?php echo esc_html( $method->get_title() ); ?>
									</a>
								</td>
								<td class="id">
									<?php echo esc_attr( $method->id ); ?>
								</td>
								<td class="day-from">
									<input type="number" step="1" min="0" name="method_estimate_from[<?php echo esc_attr( $method_id ); ?>]" value="<?php echo isset( $method_estimate_from[ $method_id ] ) ? $method_estimate_from[ $method_id ] : ''; ?>" />
								</td>
								<td width="1%" class="day-to">
									<input type="number" step="1" min="0" name="method_estimate_to[<?php echo esc_attr( $method_id ); ?>]" value="<?php echo isset( $method_estimate_to[ $method_id ] ) ? $method_estimate_to[ $method_id ] : ''; ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<?php endif; ?>
					<tfoot>
						<tr>
							<th colspan="4" style="padding-left: 2% !important"><span class="description"><?php esc_html_e( 'Set the estimated range of days required for each method.', 'woocommerce-shipping-estimate' ); ?></span></th>
						</tr>
					</tfoot>
				</table>
			</td>
		</tr>
		<?php
	}


	/**
	 * Processes and saves the method's "from" and "to" values for the estimate.
	 *
	 * @since 1.0.0
	 */
	public function process_method_estimate() {
		global $current_section;

		// Bail if we're not in the "Shipping Options" section
		if ( 'options' !== $current_section ) {
			return;
		}

		$estimate_from = isset( $_POST['method_estimate_from'] ) ? $_POST['method_estimate_from'] : '';
		$estimate_to   = isset( $_POST['method_estimate_to'] )   ? $_POST['method_estimate_to']   : '';

		$method_estimate_from = [];
		$method_estimate_to   = [];

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

		update_option( 'wc_shipping_method_estimate_from', $method_estimate_from );
		update_option( 'wc_shipping_method_estimate_to', $method_estimate_to );
	}


	/** Helper methods ***************************************/


	/**
	 * Main Plugin Instance, ensures only one instance is/can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @see wc_shipping_estimate()
	 * @return Plugin
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 2.0.0
	 */
	public function __clone() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot clone instances of %s.', 'woocommerce-shipping-estimate' ), 'WooCommerce Shipping Estimates' ), '2.0.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot unserialize instances of %s.', 'woocommerce-shipping-estimate' ), 'WooCommerce Shipping Estimates' ), '2.0.0' );
	}


	/**
	 * Gets the updater class instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Updater\License
	 */
	public function get_license_instance() {
		return $this->license;
	}


	/**
	 * Adds plugin page links.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links all plugin links
	 * @return array $links all plugin links + our custom links (i.e., "Settings")
	 */
	public function add_plugin_links( $links ) {

		$license_text = $this->get_license_instance()->is_license_valid() ? __( 'License', 'woocommerce-shipping-estimate' ) : __( 'Get updates', 'woocommerce-shipping-estimate' );

		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ) . '">' . __( 'Configure', 'woocommerce-shipping-estimate' ) . '</a>',
			'<a href="' . $this->get_license_instance()->get_license_settings_url() . '">' . $license_text . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}


	/**
	 * Show row meta on the plugin screen.
	 *
	 * @since 2.2.0
	 *
	 * @param array $links plugin row meta
	 * @param string $file plugin base file
	 * @return array updated links
	 */
	public function add_plugin_row_meta( $links, $file ) {

		if ( plugin_basename( $this->get_plugin_file() ) === $file ) {
			$links['github'] = '<a href="https://github.com/skyverge/woocommerce-shipping-estimate/issues/" target="_blank" aria-label="' . esc_attr__( 'Report bugs on GitHub', 'woocommerce-shipping-estimate' ) . '">' . esc_html__( 'Report bug', 'woocommerce-shipping-estimate' ) . '</a>';
		}

		return $links;
	}


	/**
	 * Load Translations.
	 *
	 * @since 1.0.0
	 */
	public function load_translation() {
		// localization
		load_plugin_textdomain( 'woocommerce-shipping-estimate', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
	}


	/**
	 * Helper to return the plugin name.
	 *
	 * @since 1.0.0
	 *
	 * @return string plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Shipping Estimate', 'woocommerce-shipping-estimate' );
	}


	/**
	 * Helper to get the plugin path.
	 *
	 * @since 1.0.0
	 *
	 * @return string the plugin path
	 */
	public function get_plugin_path() {
		return untrailingslashit( plugin_dir_path( $this->get_file() ) );
	}


	/**
	 * Helper to get the plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @return string the plugin version
	 */
	public function get_file() {
		return __FILE__;
	}


	/**
	 * Helper to get the plugin URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string the plugin URL
	 */
	public function get_plugin_url() {
		return untrailingslashit( plugins_url( '/', $this->get_file() ) );
	}


	/**
	 * Gets the main plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_plugin_file() {

		$slug = dirname( plugin_basename( $this->get_file() ) );
		return trailingslashit( $slug ) . $slug . '.php';
	}


	/**
	 * Helper to get the plugin version.
	 *
	 * @since 1.0.0
	 *
	 * @return string the plugin version
	 */
	public function get_version() {
		return self::VERSION;
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
	 *
	 * @param int $installed_version the currently installed version of the plugin
	 */
	private function upgrade( $version ) {

		// update the installed version option
		update_option( 'wc_shipping_estimate_version', $version );
	}


}
