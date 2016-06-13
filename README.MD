## WooCommerce Shipping Estimates

This plugin displays shipping time estimates for WooCommerce shipping methods on the cart and checkout pages. You can show estimates as a day or day range, such as "up to 7 days", or "Delivery estimate: 3-5 days".

You can also display date ranges instead (which are calculated from the days you set), such as "Estimated delivery by January 1", or "Estimated delivery: January 1 - January 3".

### Notes

To use version 2.0+, you must be using **WooCommerce 2.6** or newer. This adds support for shipping methods within a zone.

Want just the plugin files to install? [Check out the releases](https://github.com/skyverge/woocommerce-shipping-estimate/releases) -- there should be an installable .zip attached to each release.

This plugin supports any individual WooCommerce shipping method and can let the user set the estimated delivery time or range in days for each. It **does not** support combined methods like Table Rate Shipping. We do not plan to add this.

You can set a minimum delivery estimate, maximum estimate, or both for any / all shipping methods. The delivery label will adjust accordingly.

### Translating

The plugin is translation ready, and the text domain is `woocommerce-shipping-estimate`. 

### Contributing

We're happy to accept contributions! Feel free to add an issue or submit a PR :) Please follow WordPress code standards in your commits.

### Changelog

**2015.06.14 - version 2.0.0**
 * Feature: Methods within a shipping zone can have a unique estimate saved
 * Feature: Adds support for the [GitHub updater plugin](https://github.com/afragen/github-updater)
 * Misc: Added support for WooCommerce 2.6
 * Misc: Removed support for WooCommerce versions older than 2.6

**2015.12.14 - version 1.0.2**
 * Fix: remove escaping due to WooCommerce core change in label handling

**2015.10.15 - version 1.0.1**
 * Misc: cleaned up escaping
 * Misc: folder & text domain naming consistency

**2015.10.13 - version 1.0.0**
 * Initial Release

### Helpful Links

 - The [screenshots](https://github.com/skyverge/woocommerce-shipping-estimate/tree/master/screenshots) will show you the plugin in action.
 - Found it useful? We love hearing feedback, and we always [appreciate donations](https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal@skyverge.com&item_name=Donation+for+WooCommerce+Shipping+Estimates) to fund more free development.
