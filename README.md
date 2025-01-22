# SM - Auto Approve Product Reviews for WooCommerce

[![Licence](https://img.shields.io/badge/LICENSE-GPL2.0+-blue)](./LICENSE)

- **Developed by:** Martin Nestorov 
    - Founder and Lead Developer at [Smarty Studio](https://smartystudio.net) | Explore more at [nestorov.dev](https://github.com/mnestorov)
- **Plugin URI:** https://github.com/mnestorov/smarty-auto-approve-product-reviews

## Overview

**SM - Auto Approve Product Reviews for WooCommerce** is a plugin that allows you to automatically approve product reviews in your WooCommerce store based on minimum ratings you can set in the settings.

## Features

- Auto-Approve by Rating: Automatically approve product reviews that meet a specified minimum rating.
- Batch Approval via Cron: Approve all pending reviews matching your criteria via a scheduled task.
- Enhanced Spam Checks:
    - Honeypot Field: A hidden field to fool bots, marking them as spam if filled.
    - URL Detection: Automatically marks reviews containing URLs as spam.
    - Content Filtering: Flags extremely short or suspicious keyword-laden content.
    - Frequency Throttling: Blocks spam by detecting excessive reviews from the same IP within a short time.
    - WordPress Blacklist Integration: Leverages WP’s built-in blacklist for known spam terms.
- Log File: A debug.log file is generated in the plugin folder, capturing all approvals and spam actions.
- Dedicated Settings Page: View the debug log directly in your WordPress admin under Settings > Auto Approve Reviews.
- Translation Ready: Utilize WordPress standards to easily translate the plugin.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/smarty-auto-approve-product-reviews` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

1. Go to WooCommerce > Settings and choose the Products tab.
2. Scroll to find Auto Approve Rating in the product ratings settings section.
3. Select which rating(s) should trigger automatic approval of reviews (e.g., 4 or 5 stars).
4. (Optional) Visit Settings > Auto Approve Reviews to view the debug.log contents, which provide a record of spam checks and approvals.

## Detailed Function Descriptions

Below is a breakdown of all major functions used in this plugin and how they contribute to its functionality.

- `smarty_aar_log_error($message)` - Logs messages to the debug.log file in the plugin directory. Useful for debugging and ensuring all actions (spam marking, approvals, etc.) are tracked.
- `smarty_aar_review_settings($settings, $current_section)` - Hooks into `woocommerce_get_settings_products` to add a “Auto Approve Rating” multiselect field in WooCommerce’s Products tab.Allows store owners to choose which ratings automatically get approved (e.g., only 5 stars, or 4–5 stars).

### Spam-Check Functions

- `smarty_aar_contains_urls($content)` - Checks if comment content contains URLs (using regex). If found, the comment is flagged as spam.
- `smarty_aar_check_spammy_content($content)`- Detects extremely short comments or known spam keywords (e.g., “viagra”, “cialis”, etc.).
- `smarty_aar_check_comment_frequency($user_ip)` - Counts how many comments a single IP has posted in the last hour. If it exceeds a threshold, flags it as spam.
- `smarty_aar_check_wordpress_blacklist($commentdata)` - Leverages the built-in `wp_blacklist_check()` function to see if the content or IP matches known spam triggers in the WordPress blacklist settings.
- `smarty_aar_check_based_on_rating($approved, $commentdata)` - Hooks into pre_comment_approved to inspect each incoming review. Sequentially checks for honeypot triggers, WordPress blacklist matches, suspicious frequency, URLs, and spammy content. Finally, if the comment passes all spam checks and meets the rating criteria (from the Auto Approve Rating option), the comment is approved automatically.
- `smarty_aar_action_links($links)` - Adds a shortcut link to WooCommerce’s product settings page on the Plugins screen, so you can jump directly to the relevant settings.

### Activation & Deactivation Hooks

- `smarty_aar_on_activation()` - Creates the default woocommerce_reviews_auto_approve_rating option (default is [5]). Schedules the cron job (`smarty_aar_pending_reviews`) to run every minute (configurable via the plugin’s custom cron schedule).
- `smarty_aar_on_deactivation()` - Unschedules the cron job to avoid cluttering the WP cron system when the plugin is inactive.
- `smarty_aar_init()`- Loads the plugin’s text domain for translation support. Invoked on `plugins_loaded` action, ensuring everything needed by WordPress is ready.
- `smarty_aar_add_cron_schedules($schedules)` - Adds a new custom cron schedule for “every minute,” which the plugin uses to approve pending reviews automatically in batch.
- `smarty_aar_pending_reviews()` - Invoked by the cron job to handle all pending reviews in bulk. Looks for reviews marked “hold,” checks their rating (and again, whether they contain suspicious URLs), and approves them if they meet the criteria. Randomizes the comment date to spread out the published times for a more natural look.
- `smarty_aar_generate_unique_date(&$used_dates)` - Helper function for generating a random date/time within the past 30 days and ensures it’s not duplicated within the same cron run. Useful for making approved comments appear more organic.
- `smarty_aar_honeypot_field()` - Outputs a hidden <input> field on the comment form. If a bot fills it in, the comment is flagged as spam. This drastically reduces automated spam.

### Settings Page & Log Viewer

- `smarty_aar_menu()` - Registers a new admin page under Settings -> Auto Approve Reviews.
- `smarty_aar_settings_page()` - Displays the contents of the debug.log file in an HTML <pre> block, allowing quick access to debugging info without needing FTP or cPanel.

## Hooks and Customization

### Filters

- `woocommerce_get_settings_products` - Used to inject custom fields into WooCommerce’s “Products” settings tab.
- `pre_comment_approved` - Used to intercept and evaluate new reviews, deciding whether to approve or spam them based on rating and spam checks.

### Actions

- `register_activation_hook` and `register_deactivation_hook` - Manage scheduled tasks and default options on plugin activation/deactivation.
- `plugins_loaded` - Loads translations or additional initialization code.
- `smarty_aar_pending_reviews` (custom cron hook) - Processes pending reviews in bulk.
- `comment_form_logged_in_after` and `comment_form_after_fields` - Insert the honeypot field in the comment form (for both logged-in and guest visitors).

## Requirements

- WordPress 4.7+ or higher.
- WooCommerce 5.1.0 or higher.
- PHP 7.2+

## Changelog

For a detailed list of changes and updates made to this project, please refer to our [Changelog](./CHANGELOG.md).

## Contributing

Contributions are welcome. Please follow the WordPress coding standards and submit pull requests for any enhancements.

## Support The Project

If you find this script helpful and would like to support its development and maintenance, please consider the following options:

- **_Star the repository_**: If you're using this script from a GitHub repository, please give the project a star on GitHub. This helps others discover the project and shows your appreciation for the work done.

- **_Share your feedback_**: Your feedback, suggestions, and feature requests are invaluable to the project's growth. Please open issues on the GitHub repository or contact the author directly to provide your input.

- **_Contribute_**: You can contribute to the project by submitting pull requests with bug fixes, improvements, or new features. Make sure to follow the project's coding style and guidelines when making changes.

- **_Spread the word_**: Share the project with your friends, colleagues, and social media networks to help others benefit from the script as well.

- **_Donate_**: Show your appreciation with a small donation. Your support will help me maintain and enhance the script. Every little bit helps, and your donation will make a big difference in my ability to keep this project alive and thriving.

Your support is greatly appreciated and will help ensure all of the projects continued development and improvement. Thank you for being a part of the community!
You can send me money on Revolut by following this link: https://revolut.me/mnestorovv

---

## License

This project is released under the [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).
