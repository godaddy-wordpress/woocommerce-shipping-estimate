=== WooCommerce Shipping Estimates ===
Contributors: skyverge, beka.rice
Tags: woocommerce, shipping, shipping time, shipping estimate, woocommerce shipping
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal@skyverge.com&item_name=Donation+for+WooCommerce+Shipping+Estimates
Requires at least: 4.4
Tested up to: 5.3.2
Requires PHP: 5.6
Stable Tag: 2.3.3-dev.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Displays shipping time estimates for WooCommerce shipping methods on the cart and checkout pages.

== Description ==

> **Requires: WooCommerce 3.0.9** or newer

This plugin displays an estimated number of days or estimated delivery dates for any shipping method on the cart and checkout pages. This can let you add some text to let your customers know when an order will arrive.

For example, if you enter "2-5 days" for Flat Rate shipping, the cart and checkout will display the rate / cost, and show "Delivery Estimate: 2-5 days" below it.

If you enable display by date instead of day, this will automatically show the correct dates, such as "Delivery estimate: January 1 - January 3" instead.

= Features =

WooCommerce Shipping Estimates can:

 - add delivery / shipping estimates to some or all shipping methods
 - **supports shipping zones** and lets you set estimates per zone / method
 - show a range of days when the order might arrive with the selected method (e.g., "4 - 7 days")
 - show a range of dates when the order might arrive (e.g., "January 1 - January 3")
 - show a minimum delivery estimate (e.g., "at least 2 days" or "on or after January 1")
 - show a maximum delivery estimate (e.g., "up to 7 days" or "estimated by January 1")
 - set the estimated delivery time for any individual shipping method

= Supported Plugins =

Any plugin that adds an individual shipping method to WooCommerce (methods you can see under Settings &gt; Shipping) can accept shipping estimates. This plugin **does not support** Table Rate Shipping or other methods that are added outside of the core shipping method settings.

= Getting Help / Support =

We do support our free plugins and extensions, but please understand that support for premium products takes priority. We typically check the forums every few days (with a maximum delay of one week).

Support includes help with configuration questions and bug fixes, and does not include customizations to the plugin or the guarantee that we'll add requested features. It's a free plugin with no upsells, just enjoy :)

= More Details =

 - See the [product page](http://www.skyverge.com/product/woocommerce-shipping-estimates/) for full details and documentation
 - View more of SkyVerge's [free WooCommerce extensions](http://profiles.wordpress.org/skyverge/)
 - View all [WooCommerce extensions](http://www.skyverge.com/shop/) from SkyVerge

== Installation ==

1. Be sure you're running WooCommerce 3.0.9+ in your shop.
2. You can: (1) upload the entire `woocommerce-shipping-estimate` folder to the `/wp-content/plugins/` directory, (2) upload the .zip file with the plugin under **Plugins &gt; Add New &gt; Upload**, or (3) Search for "WooCommerce Shipping Estimate" under Plugins &gt; Add New
3. Activate the plugin through the **Plugins** menu in WordPress
4. Click the "Configure" plugin link or go to **WooCommerce &gt; Settings &gt; Shipping** and scroll down to the "Shipping Estimate" section. Enter the days required for each shipping method. You may leave any blank as needed.
5. View [documentation on the product page](http://www.skyverge.com/product/woocommerce-shipping-estimates/) for more help if needed.

== Other Notes ==

= For Developers =

There are filters in the plugin that can be used to change the label output. Here's a list of available filters:

 - `wc_shipping_estimate_days_from`
 Filters the **minimum** shipping estimate. Can be used to change the "day" display, such as altering it to show dates instead.

 - `wc_shipping_estimate_dates_from`
 Does pretty much the same thing -- filters the **minimum** shipping estimate. Can be used to change the "date" display, such as changing the format.

 - `wc_shipping_estimate_days_to`
 Filters the **maximum** shipping estimate. Can be used to change the "day" display, such as altering it to show dates instead.

 - `wc_shipping_estimate_dates_to`
 Does pretty much the same thing -- filters the **maximum** shipping estimate. Can be used to change the "date" display, such as changing the format.

 - `wc_shipping_estimate_label`
 Filters the label used to describe the estimate. Defaults to "day/days". Also passes in the number of days being used to generate the label.

Need another hook? We're happy to add it, just let us know. We also welcome contributions! Check out the [GitHub repository](https://github.com/skyverge/woocommerce-shipping-estimate/).

== Frequently Asked Questions ==

= Does this plugin support "X" shipping method? =

The plugin should support any individual shipping method added to WooCommerce properly. It **does not** support "combined" methods, like setting individual estimates for Table Rate Shipping. We do not plan to add support for combined methods.

= This is handy! Can I contribute? =

Please do! Join in on our [GitHub repository](https://github.com/skyverge/woocommerce-shipping-estimate/) and submit a report or pull request :)

== Screenshots ==

1. Plugin Settings
2. Shipping estimate with day range
3. Open-ended shipping estimates
4. Date range estimate

== Changelog ==

[See changelog](https://github.com/skyverge/woocommerce-shipping-estimate/blob/master/changelog.txt)
