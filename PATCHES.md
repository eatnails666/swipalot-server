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
