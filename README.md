<p align="center"><a href="https://smartystudio.net" target="_blank"><img src="https://smartystudio.net/wp-content/uploads/2023/06/smarty-green-logo-small.png" width="100" alt="SmartyStudio Logo"></a></p>

# Smarty Studio - Auto Approve Product Reviews for WooCommerce

[![Licence](https://img.shields.io/badge/LICENSE-GPL2.0+-blue)](./LICENSE)

- Developed by: [Smarty Studio](https://smartystudio.net) | [Martin Nestorov](https://github.com/mnestorov)
- Plugin URI: https://smartystudio.net/smarty-auto-approve-product-reviews

## Overview

**Smarty Studio - Auto Approve Product Reviews for WooCommerce** is a plugin that allows you to automatically approve product reviews in your WooCommerce store based on a minimum rating threshold that you can set in the settings.

## Features

- Automatically approve product reviews based on a minimum rating.
- Easy to configure through WooCommerce settings.
- Option to disable auto-approval.
- Supports WooCommerce's built-in review system.
- Translation ready.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/smarty-auto-approve-product-reviews` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

1. Navigate to WooCommerce settings and click on the 'Products' tab.
2. Scroll down to the 'Auto Approve Rating' setting.
3. Select the minimum ratings required for automatic approval of reviews.
4. Save the settings.

## Hooks and Customization

### Filters

- `woocommerce_get_settings_products`: Modify WooCommerce product settings.
- `pre_comment_approved`: Hook to approve comments before they are saved.

### Functions

- `smarty_auto_approve_reviews_settings`: Adds settings for auto-approving reviews.
- `smarty_auto_approve_reviews_check`: Checks and approves reviews based on rating.
- `smarty_auto_approve_reviews_action_links`: Adds a settings link in the plugin list.
- `smarty_auto_approve_reviews_on_activation`: Sets default options on plugin activation.
- `smarty_auto_approve_reviews_init`: Loads the text domain for translations.

## Requirements

- WordPress 4.7+ or higher.
- WooCommerce 5.1.0 or higher.
- PHP 7.2+

## Changelog

For a detailed list of changes and updates made to this project, please refer to our [Changelog](./CHANGELOG.md).

## Contributing

Contributions are welcome. Please follow the WordPress coding standards and submit pull requests for any enhancements.

---

## License

This project is released under the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).