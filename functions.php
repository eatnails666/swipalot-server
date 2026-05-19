<?php
// Register new image sizes and expose them in the Media Library.
if ( ! function_exists('oac_register_custom_image_sizes') ) {
  function oac_register_custom_image_sizes() {
    add_image_size('square_512', 512, 512, true);
    add_image_size('square_1024', 1024, 1024, true);
  }
  add_action('after_setup_theme', 'oac_register_custom_image_sizes');
}
if ( ! function_exists('oac_custom_image_sizes') ) {
  function oac_custom_image_sizes($sizes) {
    $sizes['square_512']  = __('Square 512px', 'adforest-child');
    $sizes['square_1024'] = __('Square 1024px', 'adforest-child');
    return $sizes;
  }
  add_filter('image_size_names_choose', 'oac_custom_image_sizes');
}
// Kill Image Watermark review notice
add_action('admin_head', 'remove_iw_review_notice');
function remove_iw_review_notice() {
    echo '<style>
        .iw-notice.iw-review-notice,
        .notice.iw-review-notice,
        div[class*="iw-review"] {
            display: none !important;
        }
    </style>';
    
    // Set the dismissed flag
    update_option('iw_review_notice', 'yes');
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'iw_review_notice', 'yes');
}
// --- AdSense / escaping patches ---
// Must load before parent theme inc/utilities.php so the if(!function_exists(...))
// guards in the parent skip their broken definitions.
require_once __DIR__ . '/inc/ads-override.php';

// --- Social-login override (Patch #8) ---
// Defines adforest_check_social_user() before the parent's authentication.php loads,
// so its if(!function_exists(...)) guard skips the parent definition and our
// provider-tagged version handles the AJAX action.
require_once __DIR__ . '/inc/social-login-override.php';

// --- Twilio bridge for server-side phone-login OTP (Patch #7) ---
// Subscribes to the adforest_send_otp_code action. Gated by the
// _swipalot_twilio_enabled option (default false) — owner flips this once
// wp-twilio-core plugin credentials are configured.
require_once __DIR__ . '/inc/twilio-otp-bridge.php';
