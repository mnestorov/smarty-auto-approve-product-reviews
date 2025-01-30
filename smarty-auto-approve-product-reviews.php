<?php
/**
 * Plugin Name:             SM - Auto Approve Product Reviews for WooCommerce
 * Plugin URI:              https://github.com/mnestorov/smarty-auto-approve-product-reviews
 * Description:             Auto approve product reviews with a minimum rating chosen by you.
 * Version:                 1.0.2
 * Author:                  Martin Nestorov
 * Author URI:              https://github.com/mnestorov
 * License:                 GPL-2.0+
 * License URI:             http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:             smarty-auto-approve-reviews
 * WC requires at least:    3.5.0
 * WC tested up to:         9.6.0
 * Requires Plugins:        woocommerce
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * HPOS Compatibility Declaration.
 *
 * This ensures that the plugin explicitly declares compatibility with 
 * WooCommerce's High-Performance Order Storage (HPOS).
 * 
 * HPOS replaces the traditional `wp_posts` and `wp_postmeta` storage system 
 * for orders with a dedicated database table structure, improving scalability 
 * and performance.
 * 
 * More details:
 * - WooCommerce HPOS Documentation: 
 *   https://developer.woocommerce.com/2022/09/12/high-performance-order-storage-in-woocommerce/
 * - Declaring Plugin Compatibility: 
 *   https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#how-to-declare-compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

if (!function_exists('smarty_aar_enqueue_admin_scripts')) {
    /**
     * Enqueues admin scripts and styles for the settings page.
     *
     * This function enqueues the necessary JavaScript and CSS files for the
     * admin settings pages of the Google Feed Generator plugin.
     * It also localizes the script to pass AJAX-related data to the JavaScript file.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    function smarty_aar_enqueue_admin_scripts($hook_suffix) {
        wp_enqueue_script('smarty-aar-admin-js', plugin_dir_url(__FILE__) . 'js/smarty-aar-admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('smarty-aar-admin-css', plugin_dir_url(__FILE__) . 'css/smarty-aar-admin.css', array(), '1.0.0');
        wp_localize_script(
            'smarty-aar-admin-js',
            'smartyAutoApproveReviews',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'siteUrl' => site_url(),
                'nonce'   => wp_create_nonce('smarty_auto_approve_reviews_nonce'),
            )
        );
    }
    add_action('admin_enqueue_scripts', 'smarty_aar_enqueue_admin_scripts');
}

if (!function_exists('smarty_aar_log_error')) {
    function smarty_aar_log_error($message) {
        // Convert all whitespace to single space
        $message = preg_replace('/\s+/', ' ', $message);
        // Or just do trim() if you only want to remove leading/trailing spaces
        // $message = trim($message);

        $log_file = plugin_dir_path(__FILE__) . 'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $log_file);
    }
}

if (!function_exists('smarty_aar_review_settings')) {
    /**
     * Add settings to change auto-approve options in WooCommerce "Products" tab.
     */
    function smarty_aar_review_settings($settings, $current_section) {
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
    add_filter('woocommerce_get_settings_products', 'smarty_aar_review_settings', 10, 2);
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
        $wp_version   = get_bloginfo('version');
        $php_version  = PHP_VERSION;
        $memory_usage = size_format(memory_get_usage(true));

        //smarty_aar_log_error("WP: {$wp_version}, PHP: {$php_version}, Mem: {$memory_usage}");
        smarty_aar_log_error(trim('Running cron job to approve pending reviews.'));

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
            smarty_aar_log_error(trim('Review ID ' . $comment->comment_ID . ' has rating: ' . $rating));

            // If the review content contains a URL, mark as spam
            if (smarty_aar_contains_urls($comment->comment_content)) {
                wp_spam_comment($comment->comment_ID);
                smarty_aar_log_error(trim('Review ID ' . $comment->comment_ID . ' marked as spam due to URL.'));
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
                smarty_aar_log_error(trim('Review ID ' . $comment->comment_ID . ' approved with rating ' . $rating));
            } else {
                smarty_aar_log_error(trim('Review ID ' . $comment->comment_ID . ' not approved. Rating does not meet criteria.'));
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
            __('Auto Approve Reviews | Settings', 'smarty-auto-approve-reviews'),
            __('Auto Approve Reviews', 'smarty-auto-approve-reviews'),
            'manage_options',
            'smarty-aar-settings',
            'smarty_aar_settings_page'
        );
    }
    add_action('admin_menu', 'smarty_aar_menu');
}

if (!function_exists('smarty_aar_register_settings')) {
    /**
     * Register plugin settings.
     */
    function smarty_aar_register_settings() {
        add_settings_section(
            'smarty_aar_section_general',
            __('General', 'smarty-auto-approve-reviews'),
            'smarty_aar_section_general_callback',
            'smarty-aar-settings'
        );
    }
    add_action('admin_init', 'smarty_aar_register_settings');
}

if (!function_exists('smarty_aar_section_general_callback')) {
    /**
     * Display the description for the general settings section.
     *
     * This function outputs the description text for the "General" section
     * in the plugin's settings page.
     *
     * @return void
     */
    function smarty_aar_section_general_callback() {
        echo '<p>' . esc_html__('Below is the plugin debug log. No other settings are currently available.', 'smarty-auto-approve-reviews') . '</p>';
    }
}

if (!function_exists('smarty_aar_settings_page')) {
    /**
     * Callback function to render our custom settings page (showing debug.log).
     */
    function smarty_aar_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'smarty-auto-approve-reviews'));
        }

        // HTML
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Auto Approve Reviews | Settings', 'smarty-auto-approve-reviews'); ?></h1>
            <div id="smarty-aar-settings-container">
                <div>
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('smarty-aar-settings');
                        do_settings_sections('smarty-aar-settings');
                        ?>
                    </form>
        
                    <?php $log_file = plugin_dir_path(__FILE__) . 'debug.log'; // debug log display ?>
                    <?php 
                    if (file_exists($log_file)) { ?>
                        <?php 
                        $log_contents = file_get_contents($log_file); 
                        // Remove unwanted indentation/spaces/blank lines
                        $log_contents = ltrim($log_contents); 
                        $log_contents = preg_replace('/^[ \t]+/m', '', $log_contents); 
                        $log_contents = preg_replace('/^[ \t]*[\r\n]+/m', '', $log_contents);
                        ?>
                        <h2><?php esc_html_e('Debug Log', 'smarty-auto-approve-reviews'); ?></h2>
                        <pre>
                            <?php echo esc_html($log_contents); ?>
                        </pre>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('smarty_aar_delete_log_nonce'); ?>
                            <input type="hidden" name="action" value="smarty_aar_delete_log" />
                            <button type="submit" class="button button-secondary">
                                <?php esc_html_e('Delete Log', 'smarty-auto-approve-reviews'); ?>
                            </button>
                        </form>
                    <?php } else { ?>
                        <p class=""><?php esc_html_e('No log file found.', 'smarty-auto-approve-reviews'); ?></p>
                    <?php } ?>
                </div>
                <div id="smarty-aar-tabs-container">
                    <div>
                        <h2 class="smarty-aar-nav-tab-wrapper">
                            <a href="#smarty-aar-documentation" class="smarty-aar-nav-tab smarty-aar-nav-tab-active"><?php esc_html_e('Documentation', 'smarty-auto-approve-reviews'); ?></a>
                            <a href="#smarty-aar-changelog" class="smarty-aar-nav-tab"><?php esc_html_e('Changelog', 'smarty-auto-approve-reviews'); ?></a>
                        </h2>
                        <div id="smarty-aar-documentation" class="smarty-aar-tab-content active">
                            <div class="smarty-aar-view-more-container">
                                <p><?php esc_html_e('Click "View More" to load the plugin documentation.', 'smarty-auto-approve-reviews'); ?></p>
                                <button id="smarty-aar-load-readme-btn" class="button button-primary">
                                    <?php esc_html_e('View More', 'smarty-auto-approve-reviews'); ?>
                                </button>
                            </div>
                            <div id="smarty-aar-readme-content" style="margin-top: 20px;"></div>
                        </div>
                        <div id="smarty-aar-changelog" class="smarty-aar-tab-content">
                            <div class="smarty-aar-view-more-container">
                                <p><?php esc_html_e('Click "View More" to load the plugin changelog.', 'smarty-auto-approve-reviews'); ?></p>
                                <button id="smarty-aar-load-changelog-btn" class="button button-primary">
                                    <?php esc_html_e('View More', 'smarty-auto-approve-reviews'); ?>
                                </button>
                            </div>
                            <div id="smarty-aar-changelog-content" style="margin-top: 20px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div><?php
    }
}

if (!function_exists('smarty_aar_delete_log')) {
    /**
     * Handles the "Delete Log" form submission from plugin settings.
     *
     * - Checks the user capability
     * - Verifies the nonce
     * - Deletes the debug.log file if it exists
     * - Redirects back to the settings page with a success or error message
     */
    function smarty_aar_delete_log() {
        // 1. Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'smarty-auto-approve-reviews'));
        }

        // 2. Validate nonce
        check_admin_referer('smarty_aar_delete_log_nonce');

        // 3. Attempt to delete the file
        $log_file = plugin_dir_path(__FILE__) . 'debug.log';
        if (file_exists($log_file)) {
            $deleted = @unlink($log_file);
            if ($deleted) {
                // 4. Redirect with success
                wp_redirect(
                    add_query_arg('smarty_aar_deleted', 'true', admin_url('options-general.php?page=smarty-aar-settings'))
                );
                exit;
            } else {
                // 5. Redirect with error
                wp_redirect(
                    add_query_arg('smarty_aar_deleted', 'false', admin_url('options-general.php?page=smarty-aar-settings'))
                );
                exit;
            }
        } else {
            // If the file doesn't exist, also consider that "success" or handle differently
            wp_redirect(
                add_query_arg('smarty_aar_deleted', 'notfound', admin_url('options-general.php?page=smarty-aar-settings'))
            );
            exit;
        }
    }
    add_action('admin_post_smarty_aar_delete_log', 'smarty_aar_delete_log');
}

if (!function_exists('smarty_aar_admin_notices')) {
    /**
     * Display an admin notice when the debug.log deletion is successful or fails.
     */
    function smarty_aar_admin_notices() {
        // Only show notices on the settings page "smarty-aar-settings"
        if (!isset($_GET['page']) || $_GET['page'] !== 'smarty-aar-settings') {
            return;
        }
        
        if (isset($_GET['smarty_aar_deleted'])) {
            $status = sanitize_text_field($_GET['smarty_aar_deleted']);
            
            if ($status === 'true') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html__('Log file deleted successfully.', 'smarty-auto-approve-reviews') . '</p>';
                echo '</div>';
            } elseif ($status === 'false') {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html__('Error: could not delete the log file.', 'smarty-auto-approve-reviews') . '</p>';
                echo '</div>';
            } elseif ($status === 'notfound') {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . esc_html__('Log file not found.', 'smarty-auto-approve-reviews') . '</p>';
                echo '</div>';
            }
        }
    }
    add_action('admin_notices', 'smarty_aar_admin_notices');
}

if (!function_exists('smarty_aar_load_readme')) {
    /**
     * AJAX handler to load and parse the README.md content.
     */
    function smarty_aar_load_readme() {
        check_ajax_referer('smarty_auto_approve_reviews_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions.');
        }
    
        $readme_path = plugin_dir_path(__FILE__) . 'README.md';
        if (file_exists($readme_path)) {
            // Include Parsedown library
            if (!class_exists('Parsedown')) {
                require_once plugin_dir_path(__FILE__) . 'libs/Parsedown.php';
            }
    
            $parsedown = new Parsedown();
            $markdown_content = file_get_contents($readme_path);
            $html_content = $parsedown->text($markdown_content);
    
            // Remove <img> tags from the content
            $html_content = preg_replace('/<img[^>]*>/', '', $html_content);
    
            wp_send_json_success($html_content);
        } else {
            wp_send_json_error('README.md file not found.');
        }
    }    
    add_action('wp_ajax_smarty_aar_load_readme', 'smarty_aar_load_readme');
}

if (!function_exists('smarty_aar_load_changelog')) {
    /**
     * AJAX handler to load and parse the CHANGELOG.md content.
     */
    function smarty_aar_load_changelog() {
        check_ajax_referer('smarty_auto_approve_reviews_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have sufficient permissions.');
        }
    
        $changelog_path = plugin_dir_path(__FILE__) . 'CHANGELOG.md';
        if (file_exists($changelog_path)) {
            if (!class_exists('Parsedown')) {
                require_once plugin_dir_path(__FILE__) . 'libs/Parsedown.php';
            }
    
            $parsedown = new Parsedown();
            $markdown_content = file_get_contents($changelog_path);
            $html_content = $parsedown->text($markdown_content);
    
            wp_send_json_success($html_content);
        } else {
            wp_send_json_error('CHANGELOG.md file not found.');
        }
    }
    add_action('wp_ajax_smarty_aar_load_changelog', 'smarty_aar_load_changelog');
}