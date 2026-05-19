<?php
/**
 * Child-theme override of adforest_check_social_user().
 *
 * Verbatim copy of the parent definition at
 * wp-content/themes/adforest/inc/authentication.php:4160, with two additions:
 *
 *   1. Provider tagging — every successful Google/Facebook login OR signup
 *      writes `google_id` / `fb_id` and `_swipalot_signup_method` user meta.
 *      This lets us enumerate social signups in the future. Existing users
 *      who haven't been tagged yet are backfilled on next social login.
 *   2. Field request — Facebook Graph API is asked for `id` explicitly so we
 *      can store the FB user id rather than the email.
 *
 * Load order: child theme functions.php loads before the parent's. Defining
 * `adforest_check_social_user` here trips the parent's
 * `if (!function_exists('adforest_check_social_user'))` guard at
 * authentication.php:4160. The action handler registered immediately above
 * that guard (line 4157) calls the function by name, so our definition wins.
 *
 * Patch #8 — see PATCHES.md.
 *
 * @package adforest-child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'adforest_check_social_user' ) ) {
    function adforest_check_social_user() {
        global $adforest_theme;
        $is_demo = adforest_is_demo();

        if ($is_demo) {
            echo '0|error|Invalid request|' . __("Not allowed in demo mode", 'adforest');
            die();
        }

        check_ajax_referer('sb_social_login_nonce', 'security');
        $network = (isset($_POST['sb_network'])) ? $_POST['sb_network'] : '';
        $response_response = false;
        $user_name = "";
        $provider_id = "";


        if ($network == 'facebook') {
            $access_token = (isset($_POST['access_token'])) ? $_POST['access_token'] : '';
            $token_verify = wp_remote_get("https://graph.facebook.com/me?fields=id,name,email&access_token=$access_token");

            if (isset($token_verify['response']['code']) && $token_verify['response']['code'] == '200') {
                $info = json_decode($token_verify['body']);
                if (isset($_POST['email']) && isset($info->email) && $info->email == $_POST['email']) {
                    $user_name = $info->email;
                    $provider_id = isset($info->id) ? (string) $info->id : '';
                    $response_response = true;
                }
            }
        } else if ($network == 'google') {
            $access_token = (isset($_POST['access_token'])) ? $_POST['access_token'] : '';
            $token_verify = wp_remote_get("https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=$access_token");

            if (isset($token_verify['response']['code']) && $token_verify['response']['code'] == '200') {
                $info = json_decode($token_verify['body']);
                if (isset($_POST['email']) && isset($info->email) && $info->email == $_POST['email']) {
                    $user_name = $info->email;
                    $provider_id = isset($info->user_id) ? (string) $info->user_id : '';
                    $response_response = true;
                }
            }
        }

        if ($response_response == false) {
            echo '0|error|Invalid request|' . __("Authentication Failed.", 'adforest');
            die();
        }

        unset($_SESSION['sb_nonce']);
        $_SESSION['sb_nonce'] = time();

        if ($user_name == "") {
            echo '1|' . $_SESSION['sb_nonce'] . '|0|' . __("We are unable to get your email.", 'adforest');
            die();
        }

        $meta_key = ( $network === 'facebook' ) ? 'fb_id' : ( ( $network === 'google' ) ? 'google_id' : '' );

        if (email_exists($user_name)) {
            $user = get_user_by('email', $user_name);
            $user_id = $user->ID;

            if ($user) {
                if (count($user->roles) == 0) {
                    echo '1|' . $_SESSION['sb_nonce'] . '|0|' . __("Your account is not verified yet", 'adforest');
                    die();
                }

                if ( $meta_key && $provider_id !== '' && ! get_user_meta( $user_id, $meta_key, true ) ) {
                    update_user_meta( $user_id, $meta_key, $provider_id );
                }
                if ( $network && ! get_user_meta( $user_id, '_swipalot_signup_method', true ) ) {
                    update_user_meta( $user_id, '_swipalot_signup_method', $network );
                }

                wp_clear_auth_cookie();
                wp_set_current_user($user_id, $user->user_login);
                wp_set_auth_cookie($user_id);

                echo '1|' . $_SESSION['sb_nonce'] . '|1|' . __("You're logged in successfully", 'adforest');
            }
        } else {
            $user_username = explode('@', $user_name);
            $other_errors = adforest_before_register_new_user($user_username[0], $user_name);

            if ($other_errors) {
                echo '0|error|Invalid request|' . $other_errors;
                die();
            }

            // Register new user
            $password = mt_rand(1000, 10000);
            $uid = adforest_do_register($user_name, $password);

            if (filter_var($uid, FILTER_VALIDATE_INT) === false) {
                echo '0|error|Invalid request|' . __("Something went wrong.", 'adforest');
            } else {
                global $adforest;


                if (function_exists('adforest_email_on_new_social_user')) {
                    adforest_email_on_new_social_user($uid, $password);
                }

                $user_role = $adforest_theme['sb_user_role_on_registeration'] ?? 'none';
                if ($user_role == 'dealer') {
                    update_user_meta($uid, '_sb_user_type', 'Dealer');
                } else if ($user_role == 'individual') {
                    update_user_meta($uid, '_sb_user_type', 'Individual');
                }

                if (isset($adforest_theme['sb_allow_pkg_on_reg']) && $adforest_theme['sb_allow_pkg_on_reg'] == '1') {
                    $sb_allow_pkg_on_reg = true;
                    $package_to_assign = $adforest_theme['sb_register_package'] ?? '';
                    if (!empty($package_to_assign) && function_exists('adforest_give_user_package_from_admin')) {
                        adforest_give_user_package_from_admin($package_to_assign, $uid, $sb_allow_pkg_on_reg);
                    }
                }

                if ( $meta_key && $provider_id !== '' ) {
                    update_user_meta( $uid, $meta_key, $provider_id );
                }
                if ( $network ) {
                    update_user_meta( $uid, '_swipalot_signup_method', $network );
                }

                echo '1|' . $_SESSION['sb_nonce'] . '|1|' . __("You're registered and logged in successfully.", 'adforest');
            }
        }

        die();
    }
}
