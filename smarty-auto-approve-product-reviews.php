<?php
/**
 * Plugin Name: SM - Auto Approve Product Reviews for WooCommerce
 * Plugin URI:  https://smartystudio.net/smarty-auto-approve-product-reviews
 * Description: Auto approve product reviews with a minimum rating chosen by you.
 * Version:     1.0.0
 * Author:      Smarty Studio | Martin Nestorov
 * Author URI:  https://smartystudio.net
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: smarty-auto-approve-reviews
 * WC requires at least: 3.5.0
 * WC tested up to: 9.0.2
 * Requires Plugins: woocommerce
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

if (!function_exists('smarty_auto_approve_reviews_settings')) {
    /**
     * Add settings to change auto-approve options.
     */
    function smarty_auto_approve_reviews_settings($settings, $current_section) {
        if (!$current_section || $current_section === 'general') {

            // Basic detection for Reviews section end
            $productRatingEnd = count($settings) - 1;

            // Search the position of Reviews section end in Settings
            foreach ($settings as $index => $setting) {
                if ($setting['type'] === 'sectionend' && $setting['id'] === 'product_rating_options') {
                    $productRatingEnd = $index;
                }
            }

            array_splice($settings, $productRatingEnd, 0, [[
                'title'     => __('Auto approve rating', 'smarty-auto-approve-reviews'),
                'desc'      => __('Auto approve reviews with these minimum ratings.', 'smarty-auto-approve-reviews'),
                'id'        => 'woocommerce_reviews_auto_approve_rating',
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
    add_filter('woocommerce_get_settings_products', 'smarty_auto_approve_reviews_settings', 10, 2);
}

if (!function_exists('smarty_auto_approve_reviews_check')) {
    /**
     * Check and approve review based on rating.
     */
    function smarty_auto_approve_reviews_check($approved, $commentdata) {
        if ($commentdata['comment_type'] === 'review' && $approved == 0) {
            // Check if the review content contains URLs or links
            if (preg_match('/https?:\/\/[^\s]+/', $commentdata['comment_content'])) {
                return 'spam';
            }

            if (isset($_POST['rating'])) {
                $rating = intval($_POST['rating']);
                $minRatings = get_option('woocommerce_reviews_auto_approve_rating', [5]);

                if (in_array($rating, (array) $minRatings)) {
                    $approved = 1;
                }
            } else {
                error_log(__('Auto Approve Reviews: Rating not set in review submission.', 'smarty-auto-approve-reviews'));
            }
        }

        return $approved;
    }
    add_filter('pre_comment_approved', 'smarty_auto_approve_reviews_check', 500, 2);
}

if (!function_exists('smarty_auto_approve_reviews_action_links')) {
    /**
     * Display a shortcut Settings link on Plugin line.
     */
    function smarty_auto_approve_reviews_action_links($links) {
        $settings_link = '<a href="' . menu_page_url('wc-settings', false) . '&tab=products">' . __('Settings', 'smarty-auto-approve-reviews') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'smarty_auto_approve_reviews_action_links');
}

if (!function_exists('smarty_auto_approve_reviews_on_activation')) {
    /**
     * Set default option on plugin activation.
     */
    function smarty_auto_approve_reviews_on_activation() {
        add_option('woocommerce_reviews_auto_approve_rating', [5]);
        if (!wp_next_scheduled('smarty_auto_approve_pending_reviews')) {
            wp_schedule_event(time(), 'every_minute', 'smarty_auto_approve_pending_reviews');
        }
    }
    register_activation_hook(__FILE__, 'smarty_auto_approve_reviews_on_activation');
}

if (!function_exists('smarty_auto_approve_reviews_on_deactivation')) {
    /**
     * Clear the scheduled event on plugin deactivation.
     */
    function smarty_auto_approve_reviews_on_deactivation() {
        $timestamp = wp_next_scheduled('smarty_auto_approve_pending_reviews');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'smarty_auto_approve_pending_reviews');
        }
    }
    register_deactivation_hook(__FILE__, 'smarty_auto_approve_reviews_on_deactivation');
}

if (!function_exists('smarty_auto_approve_reviews_init')) {
    /**
     * Initialize plugin.
     */
    function smarty_auto_approve_reviews_init() {
        // Load plugin textdomain for translations
        load_plugin_textdomain('smarty-auto-approve-reviews', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    add_action('plugins_loaded', 'smarty_auto_approve_reviews_init');
}

if (!function_exists('smarty_add_cron_schedules')) {
    /**
     * Add custom cron schedules.
     */
    function smarty_add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'smarty-auto-approve-reviews'),
        );
        return $schedules;
    }
    add_filter('cron_schedules', 'smarty_add_cron_schedules');
}

if (!function_exists('smarty_auto_approve_pending_reviews')) {
    /**
     * Approve existing pending reviews based on the selected ratings and randomize the dates.
     */
    function smarty_auto_approve_pending_reviews() {
        // Add logging for debugging
        //error_log(__('Auto Approve Reviews: Running cron job to approve pending reviews.', 'smarty-auto-approve-reviews'));

        // Get the selected ratings for auto-approval
        $minRatings = get_option('woocommerce_reviews_auto_approve_rating', [5]);

        // Fetch all pending reviews
        $args = [
            'status' => 'hold',
            'type'   => 'review',
            'number' => -1,
        ];
        $comments = get_comments($args);

        // Array to keep track of used dates
        $used_dates = [];

        foreach ($comments as $comment) {
            // Get the rating from the comment meta
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);

            // Check if the review content contains URLs or links
            if (preg_match('/https?:\/\/[^\s]+/', $comment->comment_content)) {
                wp_spam_comment($comment->comment_ID);
                continue;
            }

            // Approve the comment if it meets the criteria
            if (in_array($rating, (array) $minRatings)) {
                // Ensure unique dates
                $random_date = smarty_generate_unique_date($used_dates);
                wp_update_comment([
                    'comment_ID' => $comment->comment_ID,
                    'comment_date' => $random_date,
                    'comment_date_gmt' => get_gmt_from_date($random_date)
                ]);
                wp_set_comment_status($comment->comment_ID, 'approve', true);
                // Log each approved review
                //error_log(sprintf(__('Auto Approve Reviews: Approved review ID %d with rating %d on %s.', 'smarty-auto-approve-reviews'), $comment->comment_ID, $rating, $random_date));
            }
        }
    }
    add_action('smarty_auto_approve_pending_reviews', 'smarty_auto_approve_pending_reviews');
}

if (!function_exists('smarty_generate_unique_date')) {
    /**
     * Generate a unique date that hasn't been used yet.
     *
     * @param array $used_dates An array of dates that have already been used.
     * @return string A unique date string.
     */
    function smarty_generate_unique_date(&$used_dates) {
        do {
            $random_timestamp = rand(strtotime('-30 days'), time());
            $random_date = date('Y-m-d H:i:s', $random_timestamp);
        } while (in_array($random_date, $used_dates));

        $used_dates[] = $random_date;
        return $random_date;
    }
}