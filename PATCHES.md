# PATCHES.md — adforest-child

Tracks every patch applied to the AdForest child theme to survive future ScriptsBundle theme updates.

---

## How to use this file after a parent theme update

1. For **template-file overrides** (template-parts/, woocommerce/): after a theme update, diff the new parent file against the child copy and re-apply any changes near the patched lines.
2. For **function overrides** (inc/ads-override.php): after a theme update, diff lines 2838–3263 of the new parent `adforest/inc/utilities.php` against `adforest-child/inc/ads-override.php`. Re-apply functional changes, preserving Patch #1.

---

## Patch #1 — adforest_render_ads_in_search: search_ad_720_2 escaping

**File (child):** `adforest-child/inc/ads-override.php`  
**Source (parent):** `adforest/inc/utilities.php` lines 2838–3263 (v6.0.13)  
**Approximate line in child file:** line 426 (counting from function wrapper, excluding PHP header)  
**Applied:** 2026-05-16

**What changed:**
```php
// BEFORE (parent — BROKEN)
<?php echo wp_kses_post($adforest_theme['search_ad_720_2']); ?>

// AFTER (child — FIXED)
<?php adforest_render_theme_ad('search_ad_720_2'); ?>
```

**Why:** `wp_kses_post()` strips `<script>` tags. `adforest_render_theme_ad()` reads the ad type from Redux options and delegates to `adforest_render_ad()`, which uses raw `echo` for `adsense` type and `wp_kses(…, ADFOREST_ALLOWED_FORM_HTML)` for `custom_html` type. `ADFOREST_ALLOWED_FORM_HTML` includes `<script>` in its whitelist (`adforest/functions.php:41`).

**Loaded via:** `adforest-child/functions.php` → `require_once __DIR__ . '/inc/ads-override.php'`  
**Override mechanism:** `if (!function_exists('adforest_render_ads_in_search'))` guard in parent (line 2838) — child defines the function first since child `functions.php` loads before parent.

---

## Patch #2 — search-map.php: grid ad wp_kses_post → adforest_render_ad

**File (child):** `adforest-child/template-parts/layouts/search/search-map.php`  
**Source (parent):** `adforest/template-parts/layouts/search/search-map.php`  
**Approximate line in parent:** 727 (inside `turn_on_grid_adverts_search` block)  
**Applied:** 2026-05-16

**What changed:**
```php
// BEFORE (parent — BROKEN)
echo wp_kses_post( $ads[ $ad_index ] );

// AFTER (child — FIXED)
if ( function_exists( 'adforest_render_ad' ) ) {
    $map_grid_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_grid_adverts' ) : 'image';
    adforest_render_ad( $map_grid_ad_type, $ads[ $ad_index ] );
} else {
    echo wp_kses( $ads[ $ad_index ], ADFOREST_ALLOWED_FORM_HTML );
}
```

**Why:** Same root cause as Patch #1. `wp_kses_post` strips `<script>`. The ad type key `search_page_grid_adverts` is the Redux option registered in `inc/options-init.php:2644` and used by the existing working render in `inc/utilities.php:3155`.

---

## Patch #3 — search-map.php: list ad wp_kses_post → adforest_render_ad

**File (child):** `adforest-child/template-parts/layouts/search/search-map.php`  
**Source (parent):** `adforest/template-parts/layouts/search/search-map.php`  
**Approximate line in parent:** 826 (inside `turn_on_list_adverts_search` block, inside `<div class="margin-tb-30">`)  
**Applied:** 2026-05-16

**What changed:**
```php
// BEFORE (parent — BROKEN)
echo wp_kses_post( $ads[ $ad_index ] );

// AFTER (child — FIXED)
if ( function_exists( 'adforest_render_ad' ) ) {
    $map_list_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_list_adverts' ) : 'image';
    adforest_render_ad( $map_list_ad_type, $ads[ $ad_index ] );
} else {
    echo wp_kses( $ads[ $ad_index ], ADFOREST_ALLOWED_FORM_HTML );
}
```

**Why:** Same root cause. Key `search_page_list_adverts` is registered in `inc/options-init.php:2678` and used in `inc/utilities.php:3015`.

---

## Patch #4 — woocommerce/archive-product.php: child override in place

**File (child):** `adforest-child/woocommerce/archive-product.php`  
**Source (parent):** `adforest/woocommerce/archive-product.php`  
**Applied:** 2026-05-16

**What changed:** No code changes needed — the parent theme already had the correct `adforest_render_theme_ad('shop_advertisement_topp')` guard at line ~168. Child override copy kept for update durability (protects against a future parent update removing the guard).

---

## Intentionally unpatched — LOW severity

These files contain `wp_kses_post()` calls that are NOT broken and intentionally left alone:

### `adforest/template-parts/layouts/ad-style/style-1.php` — lines 600, 646

```php
// These lines are dead code. They are only reached if adforest_render_theme_ad()
// does not exist — which cannot happen while adsense-helper.php is loaded.
} else {
    echo wp_kses_post( $horizontal_ad );   // line ~600
    echo wp_kses_post( $horizontal_ad_2 ); // line ~646
}
```

**Why unpatched:** The guard `if ( function_exists( 'adforest_render_theme_ad' ) )` at line ~597/643 means these fallback lines are unreachable. No fix needed unless `inc/adsense-helper.php` is removed from the parent theme.

### `adforest/template-parts/layouts/ad-style/style-2.php` — lines 108, 629

Same situation as style-1.php. Fallback is dead code. Not patched.

---

## NOT ad-related — correctly using wp_kses_post / esc_html

The following uses of `wp_kses_post()` and `esc_html()` throughout the theme are **correct and must not be changed**:

- `wp_kses_post($category_links_string)` — HTML-safe list of category anchor tags
- `wp_kses_post($price_html)` — price display with allowed HTML
- `wp_kses_post(get_the_content())` — post body content
- `wp_kses_post($offer)`, `wp_kses_post($max)`, `wp_kses_post($min)` — numeric/safe values
- `esc_html(...)` calls throughout — escaping UI strings; correct use

---

## Patch #5 — featured_ads_shortcode: advert_horizontal wp_kses_post → _adforest_mu_render_ad

**File (mu-plugin):** `/wp-content/mu-plugins/adforest-adsense-fix.php`
**Source (plugin):** `adforest-elementor/widget_shortcodes.php` line 1642
**Applied:** 2026-05-17

**What changed:**
```php
// BEFORE (plugin — BROKEN)
<?php echo wp_kses_post($advert_img); ?>

// AFTER (mu-plugin — FIXED)
<?php _adforest_mu_render_ad($advert_img); ?>
```

**Why:** `advert_horizontal` is an Elementor `TEXTAREA` control which sanitizes with `wp_kses_post()` at save time, stripping `<script>` tags. `_adforest_mu_render_ad()` detects `<ins class="adsbygoogle"` + `data-ad-client` in the content. For stripped content (no `<script>` tag), it re-wraps the bare push call: `str_replace('(adsbygoogle = window.adsbygoogle || []).push({});', '<script>...(same)...</script>', $content)`. Then raw-echoes since it is verified AdSense.

**Override mechanism:** mu-plugins load before regular plugins. `if (!function_exists("featured_ads_shortcode"))` guard in source plugin is bypassed by our earlier definition.

**Durability:** If `adforest-elementor` plugin is updated, check line 1642 in new `widget_shortcodes.php` and update `adforest-adsense-fix.php` accordingly. The Elementor TEXTAREA sanitization issue will persist unless the widget control type is changed to `Controls_Manager::CODE`.

---

## Patch #6 — adforest_display_1_ads_sidebar_section: ad_img wp_kses_post → _adforest_mu_render_ad

**File (mu-plugin):** `/wp-content/mu-plugins/adforest-adsense-fix.php`
**Source (plugin):** `adforest-elementor/adforest_elementor_functions.php` line 884
**Applied:** 2026-05-17

**What changed:**
```php
// BEFORE (plugin — BROKEN)
<?php echo wp_kses_post($ad_img); ?>

// AFTER (mu-plugin — FIXED)
<?php _adforest_mu_render_ad($ad_img); ?>
```

**Why:** Same root cause as Patch #5. `adforest_display_1_ads_sidebar_section()` renders the 300x250 vertical sidebar ad. Called by `featured_ads_shortcode()`.

**Override mechanism:** Same as Patch #5. `if (!function_exists('adforest_display_1_ads_sidebar_section'))` guard in source plugin.

---

## DB fix — Redux ad type keys updated to 'adsense'

**Applied:** 2026-05-17 via WP-CLI `wp eval`

Redux option `adforest_theme` ad type sub-keys were defaulting to `'image'`, routing through `adforest_render_ad('image', $content)` → `wp_kses_post()` → strips `<script>`. Changed to `'adsense'` so `adforest_render_ad('adsense', $content)` does a raw echo.

**Keys updated** (11 total):

| Key | Old | New |
|-----|-----|-----|
| `search_ad_720_2_type` | (not set) image | `adsense` |
| `search_ad_720_1_type` | (not set) image | `adsense` |
| `search_page_grid_adverts_type` | `image` | `adsense` |
| `search_page_list_adverts_type` | `image` | `adsense` |
| `style_ad_720_1_type` | `image` | `adsense` |
| `style_ad_720_2_type` | `image` | `adsense` |
| `adforest_user_page_ad_vertical_type` | `image` | `adsense` |
| `blog_advertisment_top_type` | `image` | `adsense` |
| `blog_advertisment_bottom_type` | `image` | `adsense` |
| `single_post_advertisment_top_type` | `image` | `adsense` |
| `single_post_advertisment_bottom_type` | `image` | `adsense` |

**Durability note:** If an admin opens Appearance → Theme Options and re-saves without changing these dropdowns, the values will stay as `adsense`. However, if an admin changes a type dropdown back to `image` or saves new ad code that goes through Redux's own sanitization (which may strip `<script>` on some versions), the fix may regress. After any Redux save, re-check with: `wp eval "$theme = get_option('adforest_theme'); echo $theme['style_ad_720_1_type'];"`.

---

## Patch #7 — Twilio bridge for server-side phone-login OTP

**File (child):** `adforest-child/inc/twilio-otp-bridge.php`
**Source (parent):** `adforest/inc/authentication.php:1577` (`do_action('adforest_send_otp_code', $contact, $otp, $user_id)`)
**Applied:** 2026-05-19

**What changed:** The parent theme fires `do_action('adforest_send_otp_code', ...)` from `sb_login_check_user_func()` when an existing user tries to log in by phone, but ships zero listeners — the action fired into the void and the SMS was never sent. This patch adds `_swipalot_twilio_send_otp()` as a listener that bridges the action to `twl_send_sms()` provided by the `wp-twilio-core` plugin.

**Gating:** The bridge is a no-op unless `get_option('_swipalot_twilio_enabled', false)` returns truthy. The owner will enable Twilio plugin credentials and flip the flag separately:

```
wp option update _swipalot_twilio_enabled 1
```

To disable without removing the file:

```
wp option update _swipalot_twilio_enabled 0
```

**Failure mode:** If the flag is on but `wp-twilio-core` is missing/inactive (no `twl_send_sms()` function), or if `twl_send_sms()` returns a `WP_Error`, the bridge logs to PHP `error_log` with the phone number masked (last 4 digits → `****`). The user-facing flow still sees the AJAX call succeed because the action fires after `set_transient(...)` — the failure is silent from the user's POV, identical to today's behavior. Worth revisiting after Twilio is live (consider blocking the AJAX response on send success).

**Message body:** `"Your SwipAlot verification code is XXXXXX. It expires in 5 minutes. Do not share this code."` — matches the OTP TTL set at `inc/authentication.php:1551` (300 s).

**Override mechanism:** No override — this is a brand-new file loaded via `functions.php → require_once __DIR__ . '/inc/twilio-otp-bridge.php'`. The hook attaches at action priority 10 with 3 args.

**Durability:** Survives parent theme updates trivially because it's net-new code attached to a documented action hook. The only failure mode on update would be the parent removing or renaming `adforest_send_otp_code`, in which case the bridge becomes inert without errors.

---

## Patch #8 — adforest_check_social_user: provider tagging for Google/Facebook

**File (child):** `adforest-child/inc/social-login-override.php`
**Source (parent):** `adforest/inc/authentication.php:4160-4274` (v6.0.13)
**Applied:** 2026-05-19

**What changed:** The parent's `adforest_check_social_user()` AJAX handler creates a WP user on first Google/Facebook login but writes no provider-identifying user meta. As a result, social-registered users are indistinguishable from email-registered users in the DB and cannot be counted. This patch defines the function in the child theme (caught by the parent's `if (!function_exists('adforest_check_social_user'))` guard at line 4160) with two additions:

1. **Capture provider id from the verification response.** Facebook Graph API request now asks for `fields=id,name,email` (the `id` is the FB user id); Google `tokeninfo` already returns `user_id`. Both stored in a `$provider_id` local variable.
2. **Write provider meta on both branches:**
   - **Existing-user login:** if the matched WP user doesn't already have a `google_id` / `fb_id` meta, set it from `$provider_id`. If `_swipalot_signup_method` is unset, set it to `'google'` or `'facebook'`. This effectively backfills tags onto pre-existing social users the next time they log in.
   - **New-user creation:** unconditionally set `google_id` / `fb_id` and `_swipalot_signup_method` after `adforest_do_register()` succeeds.

**Meta keys written:**

| Key | Value | Notes |
|-----|-------|-------|
| `google_id` | Google `user_id` from tokeninfo | Only for `network=google` signups/logins. |
| `fb_id` | Facebook user id from Graph `/me?fields=id` | Only for `network=facebook` signups/logins. |
| `_swipalot_signup_method` | `'google'` or `'facebook'` | Underscore prefix → hidden from default user-meta admin UI. Tracks which path each user came through. |

**Override mechanism:** Child theme `functions.php` loads before parent — `require_once __DIR__ . '/inc/social-login-override.php'` defines `adforest_check_social_user` first. The `add_action('wp_ajax_sb_social_login', 'adforest_check_social_user')` calls at parent `authentication.php:4157-4158` resolve the function by name at fire time, so our definition wins. Same pattern used by Patch #1.

**Durability:** After any parent AdForest update, diff `adforest/inc/authentication.php` lines 4160-4274 against this child copy. The parent function shape is stable — likely changes are minor (new field validation, additional metadata writes). Re-apply the two `$provider_id` / `update_user_meta` blocks marked above. If the parent ever ADDS native provider tagging, this child override becomes redundant and can be removed.

**Audit query** (after the patch is live and at least one social login has occurred):

```sql
SELECT meta_value AS method, COUNT(DISTINCT user_id) AS users
FROM wp_usermeta
WHERE meta_key = '_swipalot_signup_method'
GROUP BY meta_value;
```

**Note on App Review:** As of 2026-05-19, the Facebook App (`1779296373474905`) is not approved for the `email` permission. Until App Review is complete, Facebook signups will fail at the `$info->email == $_POST['email']` check (no email returned from Graph). The provider-tagging code is correct but no Facebook signups will succeed until App Review unblocks it. Google signups should tag immediately on the next login.
