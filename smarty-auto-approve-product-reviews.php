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
            if (isset($_POST['rating'])) {
                $rating = intval($_POST['rating']);
                $minRating = intval(get_option('woocommerce_reviews_auto_approve_rating', 5));

                if ($rating >= $minRating) {
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
        $settings_link = '<a href="' . menu_page_url('wc-settings', false) . '&tab=products">Settings</a>';
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
            wp_schedule_event(time(), 'hourly', 'smarty_auto_approve_pending_reviews');
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

if (!function_exists('smarty_auto_approve_pending_reviews')) {
    /**
     * Approve existing pending reviews based on the selected ratings.
     */
    function smarty_auto_approve_pending_reviews() {
        // Get the selected ratings for auto-approval
        $minRatings = get_option('woocommerce_reviews_auto_approve_rating', [5]);

        // Fetch all pending reviews
        $args = [
            'status' => 'hold',
            'type'   => 'review',
            'number' => -1,
        ];
        $comments = get_comments($args);

        foreach ($comments as $comment) {
            // Get the rating from the comment meta
            $rating = get_comment_meta($comment->comment_ID, 'rating', true);

            // Approve the comment if it meets the criteria
            if (in_array($rating, (array) $minRatings)) {
                wp_set_comment_status($comment->comment_ID, 'approve', true);
            }
        }
    }
    add_action('smarty_auto_approve_pending_reviews', 'smarty_auto_approve_pending_reviews');
}
