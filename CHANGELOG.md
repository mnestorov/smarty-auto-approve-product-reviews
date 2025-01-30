# Changelog

### 1.0.0 (2024.06.03)
- Initial release.

### 1.0.1 (2025.01.22)
- New: Improved Spam Checks
    - Honeypot: A hidden input field that bots often fill. If it’s filled, we mark the review as spam.
    - URL detection (smarty_contains_urls): Uses a regex to check for URLs in the review.
    - WordPress Blacklist (wp_blacklist_check): Uses the core WP blacklist to flag known spam patterns.
    - Frequency Throttling: Checks how many comments have come from the same IP in the last hour. If over a threshold, mark spam.
    - Spammy Keywords & Short Content: Marks extremely short or known spammy words as spam.

- New: Settings Page for Logs
    - We created a new page under Settings > Auto Approve Reviews that loads and displays debug.log. The function add_options_page registers it, and the callback smarty_aar_auto_approve_reviews_settings_page reads and prints the file contents.

- New: Honeypot Form Field
    - We add the field in both comment_form_logged_in_after and comment_form_after_fields. This ensures it appears whether the user is logged in or not, but remains hidden to real users.

- Preserved Original Ratings Logic
    - We still read the rating from $_POST['rating'] and compare it to the user-selected woocommerce_reviews_auto_approve_rating (default [5]).

### 1.0.2 (2025.01.30)
- Added [HPOS (High-Performance Order Storage)](https://woocommerce.com/document/high-performance-order-storage/) compatibility declaration. The HPOS replaces the old post-based order system with custom database tables. 