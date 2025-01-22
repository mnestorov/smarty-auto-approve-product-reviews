<?php
/**
 * Plugin Name:             SM - Auto Approve Product Reviews for WooCommerce
 * Plugin URI:              https://github.com/mnestorov/smarty-auto-approve-product-reviews
 * Description:             Auto approve product reviews with a minimum rating chosen by you.
 * Version:                 1.0.1
 * Author:                  Martin Nestorov
 * Author URI:              https://github.com/mnestorov
 * License:                 GPL-2.0+
 * License URI:             http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:             smarty-auto-approve-reviews
 * WC requires at least:    3.5.0
 * WC tested up to:         9.4.2
 * Requires Plugins:        woocommerce
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!function_exists('smarty_aar_log_error')) {
    /**
     * Log error messages to a custom file in the plugin root directory.
     *
     * @param string $message The error message to log.
     */
    function smarty_aar_log_error($message) {
        $log_file = plugin_dir_path(__FILE__) . 'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $log_file);
    }
}

if (!function_exists('smarty_aar_settings')) {
    /**
     * Add settings to change auto-approve options in WooCommerce "Products" tab.
     */
    function smarty_aar_settings($settings, $current_section) {
        if (!$current_section || $current_section === 'general') {
            $productRatingEnd = count($settings) - 1;
            foreach ($settings as $index => $setting) {
                if ($setting['type'] === 'sectionend' && $setting['id'] === 'product_rating_options') {
                    $productRatingEnd = $index;
                }
            }
            array_splice($settings, $productRatingEnd, 0, [[
                'title'     => __('Auto approve rating', 'smarty-auto-approve-reviews'),
                'desc'      => __('Auto approve reviews with these minimum ratings.', 'smarty-auto-approve-reviews'),
                'id'        => 'woocommerce_reviews_aar_rating',
                'class'     => 'wc-enhanced-select',
                'default'   => [5],
                'type'      => 'multiselect',
                'options'   => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                ],
                'autoload'  => false,
            ]]);
        }
        return $settings;
    }
    add_filter('woocommerce_get_settings_products', 'smarty_aar_settings', 10, 2);
}

if (!function_exists('smarty_aar_contains_urls')) {
    /**
     * Check if the comment content has suspicious URLs.
     *
     * @param string $content
     * @return bool True if suspicious URLs found, false otherwise.
     */
    function smarty_aar_contains_urls($content) {
        // A robust pattern that checks for links
        $pattern = '/(https?:\/\/|www\.)[^\s]+/i';
        return (bool) preg_match($pattern, $content);
    }
}

if (!function_exists('smarty_aar_check_spammy_content')) {
    /**
     * Check if comment content is too short or contains common spam keywords.
     *
     * @param string $content
     * @return bool True if it looks like spam, false otherwise.
     */
    function smarty_aar_check_spammy_content($content) {
        $trimmed = trim($content);

        // If under 10 characters, mark as spammy (example threshold).
        if (strlen($trimmed) < 10) {
            return true;
        }

        // Common spammy keywords (can expand this list).
        $spam_keywords = [
            'viagra',
            'cialis',
            'payday loans',
            'casino',
            'xxx',
            'bitcoin',
        ];
        foreach ($spam_keywords as $keyword) {
            if (stripos($trimmed, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('smarty_aar_check_comment_frequency')) {
    /**
     * Check how many comments were posted from the same IP in the past hour.
     * If more than 5, consider it spammy (arbitrary threshold).
     *
     * @param string $user_ip
     * @return bool True if suspicious (spammy), false otherwise.
     */
    function smarty_aar_check_comment_frequency($user_ip) {
        if (empty($user_ip)) {
            return false;
        }
        $time_range = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $args = [
            'author_ip'  => $user_ip,
            'date_query' => [
                'after' => $time_range,
            ],
            'count'      => true,
        ];

        $comment_count = get_comments($args);

        // Example threshold: more than 5 comments in the past hour = suspicious
        return ($comment_count > 5);
    }
}

if (!function_exists('smarty_aar_check_wordpress_blacklist')) {
    /**
     * Manually check comment content against WordPress' internal blacklist.
     *
     * @param array $commentdata
     * @return bool True if blacklisted, false otherwise.
     */
    function smarty_aar_check_wordpress_blacklist($commentdata) {
        // WordPress function to check known blacklists
        return wp_blacklist_check(
            $commentdata['comment_author'],
            $commentdata['comment_author_email'],
            $commentdata['comment_author_url'],
            $commentdata['comment_content'],
            $commentdata['comment_author_IP'],
            $commentdata['comment_agent']
        );
    }
}

if (!function_exists('smarty_aar_check_based_on_rating')) {
    /**
     * Check and approve review based on rating and advanced spam checks.
     */
    function smarty_aar_check_based_on_rating($approved, $commentdata) {
        // Only apply for product reviews if it’s currently unapproved.
        if ($commentdata['comment_type'] === 'review' && $approved == 0) {

            // 1) Honeypot check: if a hidden field is filled, mark as spam
            if (isset($_POST['honeypot']) && !empty($_POST['honeypot'])) {
                smarty_aar_log_error('Review marked as spam due to honeypot being filled.');
                return 'spam';
            }

            // 2) Check WP built-in blacklist
            if (smarty_aar_check_wordpress_blacklist($commentdata)) {
                smarty_aar_log_error('Review marked as spam by WordPress blacklist. Content: ' . $commentdata['comment_content']);
                return 'spam';
            }

            // 3) Check for suspicious frequency from same IP
            $user_ip = isset($commentdata['comment_author_IP']) ? $commentdata['comment_author_IP'] : '';
            if (smarty_aar_check_comment_frequency($user_ip)) {
                smarty_aar_log_error("Marking review as spam due to suspicious frequency from IP: $user_ip");
                return 'spam';
            }

            // 4) Check for URLs or spammy content
            if (smarty_aar_contains_urls($commentdata['comment_content'])) {
                smarty_aar_log_error('Review marked as spam due to URL: ' . $commentdata['comment_content']);
                return 'spam';
            }
            if (smarty_aar_check_spammy_content($commentdata['comment_content'])) {
                smarty_aar_log_error('Review marked as spam due to spammy content or short length: ' . $commentdata['comment_content']);
                return 'spam';
            }

            // 5) Rating-based auto-approve logic (we are NOT checking verified owner, per your request)
            if (isset($_POST['rating'])) {
                $rating = intval($_POST['rating']);
                $minRatings = get_option('woocommerce_reviews_aar_rating', [5]);
                if (in_array($rating, (array) $minRatings)) {
                    smarty_aar_log_error('Review auto-approved with rating: ' . $rating);
                    return 1;
                }
            } else {
                smarty_aar_log_error('Auto Approve Reviews: Rating not set in review submission.');
            }
        }
        return $approved;
    }
    add_filter('pre_comment_approved', 'smarty_aar_check_based_on_rating', 500, 2);
}

if (!function_exists('smarty_aar_action_links')) {
    /**
     * Display a shortcut Settings link on the Plugin line (linking to WooCommerce settings).
     */
    function smarty_aar_action_links($links) {
        $settings_link = '<a href="' . menu_page_url('wc-settings', false) . '&tab=products">' . __('WC Settings', 'smarty-auto-approve-reviews') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'smarty_aar_action_links');
}

if (!function_exists('smarty_aar_on_activation')) {
    /**
     * Set default option on plugin activation.
     */
    function smarty_aar_on_activation() {
        add_option('woocommerce_reviews_aar_rating', [5]);
        if (!wp_next_scheduled('smarty_aar_pending_reviews')) {
            wp_schedule_event(time(), 'every_minute', 'smarty_aar_pending_reviews');
        }
        smarty_aar_log_error('Auto Approve Reviews: Plugin activated and cron job scheduled.');
    }
    register_activation_hook(__FILE__, 'smarty_aar_on_activation');
}

if (!function_exists('smarty_aar_on_deactivation')) {
    /**
     * Clear the scheduled event on plugin deactivation.
     */
    function smarty_aar_on_deactivation() {
        $timestamp = wp_next_scheduled('smarty_aar_pending_reviews');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'smarty_aar_pending_reviews');
        }
        smarty_aar_log_error('Auto Approve Reviews: Plugin deactivated and cron job unscheduled.');
    }
    register_deactivation_hook(__FILE__, 'smarty_aar_on_deactivation');
}

if (!function_exists('smarty_aar_init')) {
    /**
     * Initialize plugin (textdomain, etc.)
     */
    function smarty_aar_init() {
        load_plugin_textdomain('smarty-auto-approve-reviews', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    add_action('plugins_loaded', 'smarty_aar_init');
}

if (!function_exists('smarty_aar_add_cron_schedules')) {
    /**
     * Add custom cron schedules (every minute).
     */
    function smarty_aar_add_cron_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute', 'smarty-auto-approve-reviews'),
        ];
        return $schedules;
    }
    add_filter('cron_schedules', 'smarty_aar_add_cron_schedules');
}

if (!function_exists('smarty_aar_pending_reviews')) {
    /**
     * Approve existing pending reviews based on the selected ratings and randomize the dates.
     */
    function smarty_aar_pending_reviews() {
        smarty_aar_log_error('Auto Approve Reviews: Running cron job to approve pending reviews.');

        $minRatings = get_option('woocommerce_reviews_aar_rating', [5]);

        $args = [
            'status' => 'hold',
            'type'   => 'review',
            'number' => -1,
        ];
        $comments = get_comments($args);

        // Array to keep track of used dates (for random date generation)
        $used_dates = [];

        foreach ($comments as $comment) {
            smarty_aar_log_error('Processing review ID: ' . $comment->comment_ID);

            // Get the rating from the comment meta
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);
            smarty_aar_log_error('Review ID ' . $comment->comment_ID . ' has rating: ' . $rating);

            // If the review content contains a URL, mark as spam
            if (smarty_aar_contains_urls($comment->comment_content)) {
                wp_spam_comment($comment->comment_ID);
                smarty_aar_log_error('Review ID ' . $comment->comment_ID . ' marked as spam due to URL.');
                continue;
            }

            // Approve only if rating meets criteria
            if (in_array($rating, (array) $minRatings)) {
                $random_date = smarty_aar_generate_unique_date($used_dates);
                wp_update_comment([
                    'comment_ID'       => $comment->comment_ID,
                    'comment_date'     => $random_date,
                    'comment_date_gmt' => get_gmt_from_date($random_date)
                ]);
                wp_set_comment_status($comment->comment_ID, 'approve', true);
                smarty_aar_log_error('Review ID ' . $comment->comment_ID . ' approved with rating ' . $rating);
            } else {
                smarty_aar_log_error('Review ID ' . $comment->comment_ID . ' not approved. Rating does not meet criteria.');
            }
        }
    }
    add_action('smarty_aar_pending_reviews', 'smarty_aar_pending_reviews');
}

if (!function_exists('smarty_aar_generate_unique_date')) {
    /**
     * Generate a unique date that hasn't been used yet.
     *
     * @param array $used_dates An array of dates that have already been used.
     * @return string A unique date string.
     */
    function smarty_aar_generate_unique_date(&$used_dates) {
        do {
            $random_timestamp = rand(strtotime('-30 days'), time());
            $random_date = date('Y-m-d H:i:s', $random_timestamp);
        } while (in_array($random_date, $used_dates));

        $used_dates[] = $random_date;
        return $random_date;
    }
}

/**
 * HONEYPOT FIELD (display on comment form)
 *
 * We hook into comment_form for both logged-in and non-logged-in users.
 * We'll add a hidden field that real humans won't see/fill, but bots might.
 */
if (!function_exists('smarty_aar_honeypot_field')) {
    function smarty_aar_honeypot_field() {
        // Hidden style, so users won't see it
        echo '<p class="comment-form-honeypot" style="display:none;">';
        echo '<label for="honeypot">' . __('Leave this field empty', 'smarty-auto-approve-reviews') . '</label>';
        echo '<input type="text" name="honeypot" id="honeypot" value="" />';
        echo '</p>';
    }
}
add_action('comment_form_logged_in_after', 'smarty_aar_honeypot_field');
add_action('comment_form_after_fields', 'smarty_aar_honeypot_field');

/**
 * ADD A SETTINGS PAGE UNDER 'SETTINGS' -> 'Auto Approve Reviews'
 * This page displays the debug.log contents.
 */
if (!function_exists('smarty_aar_menu')) {
    /**
     * Register the custom settings page under 'Settings'.
     */
    function smarty_aar_menu() {
        add_options_page(
            __('Smarty Auto Approve Reviews Settings', 'smarty-auto-approve-reviews'),
            __('Auto Approve Reviews', 'smarty-auto-approve-reviews'),
            'manage_options',
            'smarty-auto-approve-reviews',
            'smarty_aar_settings_page'
        );
    }
    add_action('admin_menu', 'smarty_aar_menu');
}

if (!function_exists('smarty_aar_settings_page')) {
    /**
     * Callback function to render our custom settings page (showing debug.log).
     */
    function smarty_aar_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'smarty-auto-approve-reviews'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . __('Smarty Auto Approve Reviews - Debug Log', 'smarty-auto-approve-reviews') . '</h1>';

        $log_file = plugin_dir_path(__FILE__) . 'debug.log';
        if (file_exists($log_file)) {
            $log_contents = file_get_contents($log_file);

            // Display log in a safe manner
            echo '<pre style="background: #f9f9f9; border: 1px solid #ccc; padding: 10px;">';
            echo esc_html($log_contents);
            echo '</pre>';
        } else {
            echo '<p>' . __('No log file found.', 'smarty-auto-approve-reviews') . '</p>';
        }

        echo '</div>';
    }
}