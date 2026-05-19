<?php
/**
 * Twilio bridge for AdForest server-side OTP (phone login for existing users).
 *
 * Subscribes to the `adforest_send_otp_code` action fired by
 * adforest/inc/authentication.php (sb_login_check_user_func) and delivers the
 * code via the wp-twilio-core plugin's twl_send_sms() helper.
 *
 * Gated by the `_swipalot_twilio_enabled` option (default false) so this
 * bridge is a no-op until the owner has configured the Twilio plugin and
 * flipped the flag. Until then, the action fires harmlessly with no listener
 * receiving — matching prior behavior.
 *
 * Patch #7 — see PATCHES.md.
 *
 * @package adforest-child
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( '_swipalot_twilio_send_otp' ) ) {
    /**
     * Send an AdForest-issued OTP via Twilio.
     *
     * @param string $phone   E.164 phone number (e.g. +19041234567).
     * @param string $otp     6-digit numeric OTP (plaintext).
     * @param int    $user_id WP user id receiving the OTP.
     */
    function _swipalot_twilio_send_otp( $phone, $otp, $user_id ) {

        if ( ! get_option( '_swipalot_twilio_enabled', false ) ) {
            return;
        }

        if ( ! function_exists( 'twl_send_sms' ) ) {
            error_log( '[SwipAlot Twilio bridge] twl_send_sms() not available; wp-twilio-core not loaded.' );
            return;
        }

        $phone = trim( (string) $phone );
        $otp   = trim( (string) $otp );

        if ( $phone === '' || $otp === '' ) {
            return;
        }

        $message = sprintf(
            /* translators: 1: 6-digit OTP code */
            __( 'Your SwipAlot verification code is %1$s. It expires in 5 minutes. Do not share this code.', 'adforest-child' ),
            $otp
        );

        $args = array(
            'number_to' => $phone,
            'message'   => $message,
        );

        $result = twl_send_sms( $args );

        if ( is_wp_error( $result ) ) {
            error_log( sprintf(
                '[SwipAlot Twilio bridge] Failed to send OTP for user %d to %s: %s',
                (int) $user_id,
                substr( $phone, 0, -4 ) . '****',
                $result->get_error_message()
            ) );
        }
    }
    add_action( 'adforest_send_otp_code', '_swipalot_twilio_send_otp', 10, 3 );
}
