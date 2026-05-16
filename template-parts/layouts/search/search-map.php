<?php
global $adforest_theme;
$adforest_search_page = apply_filters('adforest_language_page_id', $adforest_theme['sb_search_page']);
if (function_exists('adforest_load_search_countries')) {
    adforest_load_search_countries();
}

$loading_ads_mode = isset($adforest_theme['loading_ads_mode']) ? $adforest_theme['loading_ads_mode'] : 'pagination';

$style_for_infinity_scroll = '';
if ($loading_ads_mode == 'infinity_scroll') {
    $style_for_infinity_scroll = 'style = "height: 1000px; overflow: auto;"';
}
$mapType = adforest_mapType();

wp_enqueue_script('adforest-search');
wp_enqueue_style('datepicker', trailingslashit(esc_url(get_template_directory_uri())) . 'assets/css/datepicker.min.css');
/* For Near By Ads */
$allow_near_by = (isset($_GET['location']) && $_GET['location']) ? true : false;
$allow_rd = (isset($_GET['rd']) && $_GET['rd']) ? true : false;
$lat_lng_meta_query = array();
if ($allow_near_by && $allow_rd) {
    $latlng = array();
    if ($mapType == 'leafletjs_map') {
        $map_lat = (isset($_GET['lat']) && $_GET['lat']) ? floatval($_GET['lat']) : 0;
        $map_long = (isset($_GET['long']) && $_GET['long']) ? floatval($_GET['long']) : 0;
        if ($map_lat != 0 && $map_long != 0) {
            $latlng = array("latitude" => $map_lat, "longitude" => $map_long);
        }
    }

    // Fallback: if no coords yet (Google Maps mode, or Leaflet with missing lat/lng), geocode the location text
    if (empty($latlng) && function_exists('adforest_getLatLong')) {
        $latlng = adforest_getLatLong(sanitize_text_field($_GET['location']));
        if (!is_array($latlng)) {
            $latlng = array();
        }
    }

    if (count($latlng) > 0) {
        $latitude = (isset($latlng['latitude'])) ? $latlng['latitude'] : '';
        $longitude = (isset($latlng['longitude'])) ? $latlng['longitude'] : '';
        $distance = (isset($_GET['rd'])) ? $_GET['rd'] : '20';
        $data_array = array("latitude" => $latitude, "longitude" => $longitude, "distance" => $distance);
        if ($latitude != "" && $longitude != "") {
            $type_lat = "'DECIMAL'";
            $type_lon = "'DECIMAL'";
            $lats_longs = adforest_determine_minMax_latLong($data_array, false);
            if (isset($lats_longs) && count($lats_longs) > 0) {
                //$lat_lng_meta_query['relation'] = 'AND';
                $lat_lng_meta_query[] = array(
                    'key' => '_adforest_ad_map_lat',
                    'value' => array(
                        $lats_longs['lat']['min'],
                        $lats_longs['lat']['max']
                    ),
                    'compare' => 'BETWEEN',
                    'type' => 'DECIMAL',
                );
                $lat_lng_meta_query[] = array(
                    'key' => '_adforest_ad_map_long',
                    'value' => array(
                        $lats_longs['long']['min'],
                        $lats_longs['long']['max']
                    ),
                    'compare' => 'BETWEEN',
                    'type' => 'DECIMAL',
                );
                add_filter('get_meta_sql', 'adforest_cast_decimal_precision');
                if (!function_exists('adforest_cast_decimal_precision')) {
                    function adforest_cast_decimal_precision($array)
                    {
                        // Only replace bare DECIMAL (not already DECIMAL(...)) to avoid double-fire corruption
                        $array['where'] = preg_replace('/DECIMAL(?!\()/', 'DECIMAL(10,6)', $array['where']);

                        return $array;
                    }
                }
            }
        }
    }
}

$meta = array('key' => 'post_id', 'value' => '0', 'compare' => '!=',);
// only active ads
$is_active = array('key' => '_adforest_ad_status_', 'value' => 'active', 'compare' => '=',);
$condition = '';
if (isset($_GET['condition']) && $_GET['condition'] != "") {
    $condition = array('key' => '_adforest_ad_condition', 'value' => $_GET['condition'], 'compare' => '=',);
}
$ad_type = '';
if (isset($_GET['ad_type']) && $_GET['ad_type'] != "") {
    $ad_type = array('key' => '_adforest_ad_type', 'value' => $_GET['ad_type'], 'compare' => '=',);
} else if (isset($_GET['adtype']) && $_GET['adtype'] != "") {
    $ad_type = array('key' => '_adforest_ad_type', 'value' => $_GET['adtype'], 'compare' => '=',);
}
$warranty = '';
if (isset($_GET['warranty']) && $_GET['warranty'] != "") {
    $warranty = array('key' => '_adforest_ad_warranty', 'value' => $_GET['warranty'], 'compare' => '=',);
}
$feature_or_simple = '';
if (isset($_GET['ad']) && $_GET['ad'] != "") {
    $feature_or_simple = array('key' => '_adforest_is_feature', 'value' => $_GET['ad'], 'compare' => '=',);
}
if (isset($_GET['sort']) && $_GET['sort'] == "featured") {
    $feature_or_simple = array('key' => '_adforest_is_feature', 'value' => '1', 'compare' => '=',);
}

$currency = '';
if (isset($_GET['c']) && $_GET['c'] != "") {
    $currency = array('key' => '_adforest_ad_currency', 'value' => $_GET['c'], 'compare' => '=',);
}
$price = '';
if (isset($_GET['min_price']) && $_GET['min_price'] != "") {
    $price = array(
        'key' => '_adforest_ad_price',
        'value' => array($_GET['min_price'], $_GET['max_price']),
        'type' => 'numeric',
        'compare' => 'BETWEEN',
    );
}
$location = '';
if (isset($_GET['location']) && $_GET['location'] != "" && !$allow_rd) {

    $raw_location = sanitize_text_field($_GET['location']);

    // Split by comma and space into individual keywords
    $keywords = preg_split('/[\s,]+/', $raw_location);

    // Remove empty values and short noise words (2 chars or less)
    $keywords = array_filter($keywords, function($word) {
        return strlen($word) > 2;
    });

    if (empty($keywords)) {
        // Fallback to original single-string LIKE behavior
        $location = array(
            'key' => '_adforest_ad_location',
            'value' => $raw_location,
            'compare' => 'LIKE',
        );
    } else {
        // Tokenized OR search: match any keyword in the stored address
        $location = array('relation' => 'OR');
        foreach ($keywords as $word) {
            $location[] = array(
                'key' => '_adforest_ad_location',
                'value' => sanitize_text_field($word),
                'compare' => 'LIKE',
            );
        }
    }
}
//Location
$countries_location = '';
if (isset($_GET['country_id']) && $_GET['country_id'] != "") {
    $countries_location = array(
        array(
            'taxonomy' => 'ad_country',
            'field' => 'term_id',
            'terms' => $_GET['country_id'],
        ),
    );
}

$ad_currency = '';
if (isset($_GET['ad_currency']) && $_GET['ad_currency'] != "") {
    $ad_currency = array(
        array(
            'taxonomy' => 'ad_currency',
            'field' => 'term_id',
            'terms' => $_GET['ad_currency'],
        ),
    );
}


$countries_location = apply_filters('adforest_site_location_ads', $countries_location, 'search');
$order = 'desc';
$orderBy = 'date';
$ordering_price = "";


if (isset($_GET['sort']) && $_GET['sort'] != "") {
    $orde_arr = explode('-', $_GET['sort']);
    $order = isset($orde_arr[1]) ? $orde_arr[1] : 'desc';
    if (isset($orde_arr[0]) && $orde_arr[0] == 'price') {
        $orderBy = 'meta_value_num';
        $ordering_price = '_adforest_ad_price';
    } else {
        $orderBy = isset($orde_arr[0]) ? $orde_arr[0] : 'date';
    }
}
$category = '';
if (isset($_GET['cat_id']) && $_GET['cat_id'] != "") {
    $category = array(
        array(
            'taxonomy' => 'ad_cats',
            'field' => 'term_id',
            'terms' => $_GET['cat_id'],
            'include_children' => 1,
        ),
    );
}
$title = '';
if (isset($_GET['ad_title']) && $_GET['ad_title'] != "") {
    $title = $_GET['ad_title'];
}
$custom_search = array();
if (isset($_GET['min_custom']) && is_array($_GET['min_custom']) && count($_GET['min_custom']) > 0) {
    foreach ($_GET['min_custom'] as $key => $val) {
        $get_minVal = $val;
        $get_maxVal = (isset($_GET['max_custom']["$key"]) && $_GET['max_custom']["$key"] != "") ? $_GET['max_custom']["$key"] : '';
        if ($get_minVal != "" && $get_maxVal != "") {
            $metaKey = '_adforest_tpl_field_' . $key;
            if (adforest_validateDateFormat($get_minVal) && adforest_validateDateFormat($get_maxVal)) {
                $custom_search[] = array(
                    'key' => $metaKey,
                    'value' => array($get_minVal, $get_maxVal),
                    'compare' => 'BETWEEN',
                );
            } else {
                $custom_search[] = array(
                    'key' => $metaKey,
                    'value' => array($get_minVal, $get_maxVal),
                    'type' => 'numeric',
                    'compare' => 'BETWEEN',
                );
            }
        }
    }
}
if (isset($_GET['custom']) && is_array($_GET['min_custom']) && count($_GET['min_custom']) > 0) {
    $template_cat_id = (isset($_GET['cat_id']) && $_GET['cat_id'] != "") ? $_GET['cat_id'] : '';
    $cat_tempate = adforest_dynamic_field_type_template($template_cat_id);
    foreach ($_GET['custom'] as $key => $val) {
        if (is_array($val)) {
            $arr = array();
            $metaKey = '_adforest_tpl_field_' . $key;
            if (is_array($val) && count($val) > 0) {
                foreach ($val as $v) {
                    $custom_search[] = array('key' => $metaKey, 'value' => $v, 'compare' => 'LIKE',);
                }
            }
        } else {
            if (trim($val) == "0") {
                continue;
            }
            $field_type = adforest_dynamic_field_type($cat_tempate, $key);
            $val = stripslashes_deep($val);
            $metaKey = '_adforest_tpl_field_' . $key;
            if ($field_type == 'checkbox') {
                $custom_search[] = array('key' => $metaKey, 'value' => ('"' . $val . '"'), 'compare' => 'LIKE',);
            } elseif ($field_type == 'select') {
                // $custom_search[] = array('key' => $metaKey, 'value' => '^' . $val, 'compare' => 'REGEXP',);
                $custom_search[] = array('key' => $metaKey, 'value' => $val, 'compare' => 'REGEXP',);
            } else {
                $custom_search[] = array('key' => $metaKey, 'value' => $val, 'compare' => 'LIKE',);
            }
        }
    }
}
if (get_query_var('paged')) {
    $paged = get_query_var('paged');
} else if (get_query_var('page')) {
    // This will occur if on front page.
    $paged = get_query_var('page');
} else {
    $paged = 1;
}
$args = array(
    's' => $title,
    'post_type' => 'ad_post',
    'post_status' => 'publish',
    'posts_per_page' => get_option('posts_per_page'),
    'tax_query' => array($category, $countries_location, $ad_currency),
    'meta_key' => $ordering_price,
    'meta_query' => array(
        $is_active,
        $condition,
        $ad_type,
        $warranty,
        $feature_or_simple,
        $price,
        $currency,
        $location,
        $custom_search,
        $lat_lng_meta_query,
    ),
    'order' => $order,
    'orderby' => $orderBy,
    'paged' => $paged,
);
$args = apply_filters('adforest_wpml_show_all_posts', $args);
$query = new WP_Query($args);

$total_ad_count = 0;
while ($query->have_posts()) {
    $query->the_post();
    $total_ad_count++;
}

$view_type = 'grid';
if (!isset($_GET['view-type']) || $_GET['view-type'] == 'grid') {
    $view_type = 'grid';
} elseif (isset($_GET['view-type']) && $_GET['view-type'] == 'list') {
    $view_type = 'list';
}

?>

<!-- adt-map-search-section-start -->
<section class="adt-map-search-section">
    <div class="map-search-wrapper">
        <div class="search-content-side scroller">
            <div class="all-filters-sidebar adt-ads-filter-sidebar">
                <i class="fas fa-times close-sidebar"></i>
                <?php dynamic_sidebar('adforest_search_sidebar'); ?>
            </div>
            <div class="search-filters-content">
                <div class="adt-ads-sort-box">
                    <h3><?php echo esc_html($query->found_posts) . ' ' . esc_html__('Ad(s) Found:', 'adforest'); ?></h3>
                    <div class="right-content">
                        <?php
                        $selectedOldest = $selectedLatest = $selectedTitleAsc = $selectedTitleDesc = $selectedPriceHigh = $selectedPriceLow = $selectedFeatured = '';
                        if (isset($_GET['sort'])) {
                            $selectedOldest = ($_GET['sort'] == 'id-asc') ? 'selected' : '';
                            $selectedLatest = ($_GET['sort'] == 'id-desc') ? 'selected' : '';
                            $selectedTitleAsc = ($_GET['sort'] == 'title-asc') ? 'selected' : '';
                            $selectedFeatured = ($_GET['sort'] == 'featured') ? 'selected' : '';
                            $selectedTitleDesc = ($_GET['sort'] == 'title-desc') ? 'selected' : '';
                            $selectedPriceHigh = ($_GET['sort'] == 'price-desc') ? 'selected' : '';
                            $selectedPriceLow = ($_GET['sort'] == 'price-asc') ? 'selected' : '';
                        } elseif (isset($_GET['ad'])) {
                            $selectedFeatured = ($_GET['ad'] == '1') ? 'selected' : '';
                        }
                        ?>

                        <form id="sort-form" method="get">
                            <select name="sort" class="default-select order_by" id="select-sort">
                                <option value="id-desc" <?php echo esc_attr($selectedLatest); ?>>
                                    <?php echo esc_html__('Newest To Oldest', 'adforest'); ?>
                                </option>
                                <option value="id-asc" <?php echo esc_attr($selectedOldest); ?>>
                                    <?php echo esc_html__('Oldest To Newest', 'adforest'); ?>
                                </option>
                                <option value="featured" <?php echo esc_attr($selectedFeatured); ?>>
                                    <?php echo esc_html__('Featured', 'adforest'); ?>
                                </option>
                                <option value="price-desc" <?php echo esc_attr($selectedPriceHigh); ?>>
                                    <?php echo esc_html__('Price: High to Low', 'adforest'); ?>
                                </option>
                                <option value="price-asc" <?php echo esc_attr($selectedPriceLow); ?>>
                                    <?php echo esc_html__('Price: Low to High', 'adforest'); ?>
                                </option>
                            </select>
                            <?php echo adforest_search_params('sort'); ?>
                        </form>
                        <?php
                        $grid_view = adforest_custom_remove_url_query('view-type', 'grid');
                        $list_view = adforest_custom_remove_url_query('view-type', 'list');
                        $grid_active = '';
                        $list_active = '';
                        if ((isset($_GET['view-type']) && $_GET['view-type'] == 'grid') || !isset($_GET['view-type'])) {
                            $grid_active = 'active';
                        } elseif (isset($_GET['view-type']) && $_GET['view-type'] == 'list') {
                            $list_active = 'active';
                        }

                        if (isset($adforest_theme['search_layout_types']) && $adforest_theme['search_layout_types'] == true) {
                            ?>
                            <a href="<?php echo esc_url($grid_view); ?>"
                               class="icon-box grid <?php echo esc_attr($grid_active); ?>"><i
                                        class="fas fa-th-large"></i></a>
                            <a href="<?php echo esc_url($list_view); ?>"
                               class="icon-box list <?php echo esc_attr($list_active); ?>"><i
                                        class="fas fa-bars"></i></a>
                        <?php } ?>
                        <?php
                        if (isset($_GET) && count($_GET) > 0) {
                            ?>
                            <a style="display: inline; font-size: 16px"
                               href="<?php echo esc_url(get_permalink($adforest_search_page)); ?>"
                               class="filter-refresh-btn">
                                <i class="fas fa-redo-alt" data-toggle="tooltip"
                                   data-placement="top"
                                   title="<?php echo esc_attr__('Reset Search', 'adforest'); ?>"></i>
                            </a>
                        <?php } ?>
                    </div>
                </div>
                <div class="search-filters-wrapper">
                    <div class="dropdown adtype-dropdown">
                        <?php
                        global $wp;
                        $adforest_search_page = apply_filters('adforest_language_page_id', $adforest_theme['sb_search_page']);
                        $adforest_search_page = isset($adforest_search_page) && $adforest_search_page != '' ? get_the_permalink($adforest_search_page) : 'javascript:void(0)';
                        $adforest_search_page = apply_filters('adforest_category_widget_form_action', $adforest_search_page);
                        ?>
                        <form id="ad_type_form" method="get"
                              action="<?php echo adforest_return_echo($adforest_search_page); ?>">

                            <?php
                            $ad_types = adforest_get_ad_taxonomy_callback('ad_type');
                            $perm_name = (is_home() || is_front_page()) ? 'adtype' : 'ad_type';

                            $searched_type_name = "";
                            if (isset($_GET['ad_type']) && $_GET['ad_type'] !== "") {
                                $searched_type_name = $_GET['ad_type'];
                            }

                            $type_placeholder = $searched_type_name == "" ? "Ad Type" : $searched_type_name;
                            ?>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <?php echo esc_html($type_placeholder); ?>
                            </button>
                            <ul class="dropdown-menu adtype-list">
                                <?php if (!empty($ad_types) && is_array($ad_types)) : ?>
                                    <?php foreach ($ad_types as $ad_type) : ?>
                                        <li>
                                            <label class="adt-container">
                                                <?php echo esc_html($ad_type->name); ?>
                                                <input tabindex="7" type="radio" class="submit-on-change"
                                                       id="minimal-radio-<?php echo esc_attr($ad_type->term_id); ?>"
                                                       name="<?php echo esc_attr($perm_name); ?>"
                                                       value="<?php echo esc_attr($ad_type->name); ?>">
                                                <span class="checkmark"></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <li><?php echo esc_html__('No Ad Types Found', 'adforest'); ?></li>
                                <?php endif; ?>
                            </ul>
                            <?php echo adforest_search_params($perm_name); ?>
                        </form>
                    </div>
                    <div class="dropdown category-dropdown adt-category-list-sidebar">
                        <?php
                        $adforest_search_page = apply_filters('adforest_language_page_id', $adforest_theme['sb_search_page']);
                        $adforest_search_page = isset($adforest_search_page) && $adforest_search_page != '' ? get_the_permalink($adforest_search_page) : 'javascript:void(0)';
                        $adforest_search_page = apply_filters('adforest_category_widget_form_action', $adforest_search_page, 'cat_page');
                        ?>

                        <form method="get" id="search_cats_w"
                              action="<?php echo adforest_return_echo($adforest_search_page); ?>">
                            <?php
                            $ad_categories = adforest_get_ad_taxonomy_callback('ad_cats');
                            
                            // Filter out categories with zero ads if the option is enabled
                            if (isset($adforest_theme['search_popup_cat_disable']) && $adforest_theme['search_popup_cat_disable'] == true) {
                                $ad_categories = array_filter($ad_categories, function($category) {
                                    $category_details = get_taxonomy_details($category);
                                    return isset($category_details['ad_count']) && $category_details['ad_count'] > 0;
                                });
                            }
                            
                            $searched_cat_name = "";
                            if (isset($_GET['cat_id']) && $_GET['cat_id'] !== "") {
                                $searched_cat = $_GET['cat_id'];
                                $term = get_term_by('id', $searched_cat, 'ad_cats');
                                $searched_cat_name = $term->name;
                            }

                            $cat_placeholder = $searched_cat_name == "" ? "All Categories" : $searched_cat_name;
                            ?>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <i class="fas fa-bars"></i><?php echo esc_html($cat_placeholder); ?>
                            </button>
                            <ul class="dropdown-menu categories-list">
                                <?php
                                if (is_array($ad_categories) && count($ad_categories) > 0) {
                                    foreach ($ad_categories as $category) {
                                        $category_details = get_taxonomy_details($category);
                                        $name = $category_details['name'];
                                        $ad_count = $category_details['ad_count'];
                                        $image = $category_details['image'];
                                        $icon = $category_details['icon'];
                                        $display_mode = $category_details['display_mode'];
                                        $link = $category_details['link'];
                                        $category_search_page = 'javascript:void(0);';
                                        $category_search_page = apply_filters('adforest_filter_taxonomy_popup_actions', $category_search_page, $category->term_id, 'ad_cats');
                                        ?>
                                        <li>
                                            <div class="adt-category-box">
                                                <div class="category-meta">
                                                    <a href="<?php echo esc_url($category_search_page); ?>"
                                                       class="img-box category_click_link"
                                                       data-cat-id="<?php echo esc_attr($category->term_id); ?>">
                                                        <?php if ($display_mode === 'icon' && !empty($icon)) : ?>
                                                            <div class="<?php echo esc_attr($icon); ?>"></div>
                                                        <?php else : ?>
                                                            <img class="img-fluid" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>">
                                                        <?php endif; ?>
                                                    </a>
                                                    <a href="<?php echo esc_url($category_search_page); ?>"
                                                       class="category_click_link"
                                                       data-cat-id="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($name); ?></a>
                                                </div>
                                                <span class="listing-count"><?php printf( esc_html__( '%s ads', 'adforest' ), esc_html( $ad_count ) ); ?></span>
                                            </div>
                                        </li>
                                        <?php
                                    }
                                }
                                ?>
                            </ul>
                            <input type="hidden" name="cat_id" id="cat_id" value=""/>
                            <?php echo adforest_search_params('cat_id'); ?>
                            <?php apply_filters('adforest_form_lang_field', true); ?>
                        </form>
                    </div>
                    <button class="search-all-filters"><i
                                class="fas fa-filter"></i><?php echo esc_html__('All Filters', 'adforest'); ?></button>
                </div>
                <?php
                if (isset($adforest_theme['sb_allow_cats_above_filters']) && $adforest_theme['sb_allow_cats_above_filters']) {
                    if (isset($_GET['cat_id']) && $_GET['cat_id'] != "") {
                        ?><?php
                        $cat_id = $_GET['cat_id'];
                        $ad_cats = adforest_get_cats('ad_cats', $cat_id);
                        
                        // Filter out sub-categories with zero ads if the option is enabled
                        if (isset($adforest_theme['search_popup_cat_disable']) && $adforest_theme['search_popup_cat_disable'] == true) {
                            $ad_cats = array_filter($ad_cats, function($category) {
                                return isset($category->count) && $category->count > 0;
                            });
                        }
                        
                        $res = '';
                        $rows_count = 1;
                        $max_rows = $adforest_theme['sb_max_sub_cats'];
                        $show = true;
                        if (count($ad_cats) > 0) {
                            parse_str($_SERVER['QUERY_STRING'], $search_params);
                            unset($search_params['cat_id']);
                            $new_params = http_build_query($search_params);
                            $cat_params = '';
                            $cls = '';
                            $res .= '<ul class="city-select-city" >';
                            foreach ($ad_cats as $ad_cat) {
                                if ($new_params != "") {
                                    $cat_params = '?' . $new_params . '&cat_id=' . $ad_cat->term_id;
                                    $cat_link = get_the_permalink($adforest_search_page) . $cat_params;
                                } else {
                                    $cat_params = '?cat_id=' . $ad_cat->term_id;
                                    $cat_link = get_the_permalink($adforest_search_page) . $cat_params;
                                }

                                $li_col = '3';
                                if (isset($adforest_theme['sb_li_cols']) && $adforest_theme['sb_li_cols'] != "") {
                                    $li_col = $adforest_theme['sb_li_cols'];
                                }

                                $count = ($ad_cat->count);
                                if ($rows_count > $max_rows && $show) {
                                    $show = false;
                                    $res .= '<li class="col-md-12 col-sm-12 col-xs-12 hide_cats text-center margin-top-20"><a href="javascript:void(0);" class="tax-show-more">' . esc_html__( 'Show more', 'adforest' ) . '</a></li>';
                                    $cls = 'no-display show_it';
                                }
                                $res .= '<li class="col-md-' . esc_attr($li_col) . ' col-sm-6 col-xs-12 ' . esc_attr($cls) . '"><a href="' . $cat_link . '" >' . $ad_cat->name . ' <span>(' . $count . ')</span> </a></li>';
                                $rows_count++;
                            }
                            $res .= '</ul>';
                            ?>
                            <div class="col-md-12 col-sm-12 col-xs-12">
                                <div class="expand-collapse adforest-new-filter">
                                    <h3>
                                        <a role="button" data-bs-toggle="collapse" data-parent="#accordion"
                                           href="#collapseOnez" aria-expanded="true" aria-controls="collapseOnez">
                                            <i class="more-less fa fa-minus"></i>
                                            <?php
                                            $title = adforest_get_taxonomy_parents($cat_id, 'ad_cats', false);
                                            $find = '&raquo;';
                                            $replace = '';
                                            $result = preg_replace("/$find/", $replace, $title, 1);
                                            echo '<span>' . adforest_return_echo($result) . '</span>';
                                            ?>
                                        </a>
                                    </h3>
                                    <form>
                                        <div id="collapseOnez" class="panel-collapse collapse in show"
                                             role="tabpanel"
                                             aria-labelledby="headingOnez">
                                            <div class="panel-body">
                                                <div class="search-modal">
                                                    <div class="search-block"><?php echo adforest_return_echo($res); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="clearfix"></div>
                            <?php
                        }
                    }
                }
                ?>
                <div class="map_search-tags margin-top-10">
                    <?php get_template_part('template-parts/layouts/search/search', 'tags'); ?>
                </div>
            </div>

            <?php if (isset($adforest_theme['featured_first']) && $adforest_theme['featured_first'] == '1') { ?>
                <div class="featured-ads-box">
                    <div class="adt-ads-top-box">
                        <h2><?php echo esc_html__('Featured Ads', 'adforest'); ?></h2>
                    </div>
                    <?php
                    $featured_args = [
                        'post_type' => 'ad_post',
                        'posts_per_page' => get_option('posts_per_page'),
                        'meta_key' => '_adforest_is_feature',
                        'meta_value' => '1',
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ];
                    $featured_ads = new WP_Query($featured_args);
                    ?>
                    <div class="adt-vendor-mini-ads-carousel owl-carousel owl-theme">
                        <?php
                        if ($featured_ads->have_posts()) :
                            while ($featured_ads->have_posts()) : $featured_ads->the_post();
                                $truncate_title = 15;
                                $ad_details = get_ad_post_details(get_the_ID(), $truncate_title);
                                $first_img = $ad_details['img'];
                                $price_html = $ad_details['price_html'];
                                $price_html = str_replace(['<strong>', '</strong>'], [
                                    '<h5>',
                                    '</h5>'
                                ], $price_html);
                                $ad_permalink = $ad_details['ad_link'];
                                $is_featured = $ad_details['is_featured'];
                                $ad_title = $ad_details['truncated_title'];
                                ?>
                                <div class="item">
                                    <div class="adt-mini-ad-box">
                                        <div class="ad-img-box">
                                            <a href="<?php echo esc_url($ad_permalink); ?>">
                                                <img src="<?php echo esc_url($first_img); ?>"
                                                     alt="<?php echo esc_html(get_the_title()); ?>">
                                            </a>
                                            <?php if ($is_featured) : ?>
                                                <img class="featured-tag"
                                                     src="<?php echo trailingslashit(esc_url(get_template_directory_uri())) . 'images/featured.png'; ?>"
                                                     alt="featured-tag">
                                            <?php endif; ?>
                                        </div>
                                        <div class="ad-meta-box">
                                            <a href="<?php echo esc_url($ad_permalink); ?>">
                                                <h6><?php echo esc_html($ad_title); ?></h6>
                                            </a>
                                            <?php echo wp_kses_post($price_html); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            endwhile;
                            wp_reset_postdata();
                        else :
                            echo '<p>' . esc_html__('No featured ads available at the moment.', 'adforest') . '</p>';
                        endif;
                        ?>
                    </div>
                </div>
            <?php } ?>

            <?php
            $grid_cols = $adforest_theme['no_of_ad_in_search_page_row'];
            $sb_2column = (isset($adforest_theme['sb_2column_mobile_layout']) && $adforest_theme['sb_2column_mobile_layout'] == false) ? "one-column-mobile-layout" : "";
            if (($query->have_posts() && isset($_GET['view-type']) && $_GET['view-type'] != 'list') || ($query->have_posts() && !isset($_GET['view-type']))) { ?>
                <div class="search-ads-result-box grid <?php echo esc_attr($sb_2column) ?>"
                     style="grid-template-columns: repeat(<?php echo esc_attr($grid_cols); ?>, 1fr);">
                    <?php
                    $search_page_adverts = $adforest_theme['search_page_grid_adverts'];
                    $ads = explode('|', $search_page_adverts);
                    $total_ads = count($ads);
                    $ad_index = 0;

                    $ad_threshold = rand(3, 4);
                    $listing_counter = 0;
                    while ($query->have_posts()) : $query->the_post();
                        $listing_counter++;
                        $ad_details = get_ad_post_details(get_the_ID());
                        $category_names = $ad_details['category_names'];
                        $first_img = $ad_details['img'];
                        $truncated_location = $ad_details['truncated_location'];
                        $truncated_title = truncate_string($ad_details['ad_title'], 40);
                        $price_html = $ad_details['price_html'];
                        $ad_permalink = $ad_details['ad_link'];
                        $heart_class = $ad_details['heart_class'];
                        $is_featured = $ad_details['is_featured'];
                        $all_ad_images = $ad_details['all_ad_images'];
                        $ad_poster_img = $ad_details['ad_poster_img'];
                        $ad_poster_name = $ad_details['ad_poster_name'];
                        $ad_title = truncate_string($ad_details['ad_title'], 40);
                        $featured_tag = $is_featured ? '<img style="transform: rotate(180deg);" src="' . esc_url(get_template_directory_uri()) . '/images/featured.png' . '" alt="featured-tag" class="featured-tag">' : '';
                        $top_bar_specific_style = '';
                        $ad_type = get_post_meta(get_the_ID(), '_adforest_ad_type', true);
                        $ad_categories_post = $ad_details['categories'];
                        if ($adforest_theme['search_design'] == 'topbar') {
                            $top_bar_specific_style = 'top_bar_specific_style';
                        }
                        if (isset($adforest_theme['adforest_grid_layout']) && $adforest_theme['adforest_grid_layout'] == 'simple') {
                            ?>
                            <?php echo adforest_ad_grid_1($ad_permalink, $first_img, $is_featured, $ad_categories_post, $ad_details, $truncated_title, $truncated_location, $price_html, $heart_class); ?>
                            <?php
                        } elseif (isset($adforest_theme['adforest_grid_layout']) && $adforest_theme['adforest_grid_layout'] == 'with_labels') {
                            ?>
                            <div class="item search_with_labels_grid <?php echo esc_attr($top_bar_specific_style); ?>">
                                <?php echo adforest_ad_grid_2($all_ad_images, $ad_permalink, $is_featured, $ad_poster_img, $ad_poster_name, $ad_title, $truncated_location, $price_html, $heart_class); ?>
                            </div>
                            <?php
                        } elseif (isset($adforest_theme['adforest_grid_layout']) && $adforest_theme['adforest_grid_layout'] == 'modern') {
                            ?>
                            <div class="item search_with_labels_grid <?php echo esc_attr($top_bar_specific_style); ?>">
                                <?php echo adforest_ad_grid_3($all_ad_images, $ad_permalink, $heart_class, $featured_tag, $ad_poster_img, $ad_poster_name, $ad_type, $ad_title, $price_html, $truncated_location); ?>
                            </div>
                            <?php
                        }

                        if (isset($adforest_theme['turn_on_grid_adverts_search']) && $adforest_theme['turn_on_grid_adverts_search'] == '1') {
                            if ($listing_counter == $ad_threshold && $total_ads > 0) {
                                if ( function_exists( 'adforest_render_ad' ) ) {
                                    $map_grid_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_grid_adverts' ) : 'image';
                                    adforest_render_ad( $map_grid_ad_type, $ads[ $ad_index ] );
                                } else {
                                    echo wp_kses( $ads[ $ad_index ], ADFOREST_ALLOWED_FORM_HTML );
                                }

                                $ad_index++;
                                if ($ad_index >= $total_ads) {
                                    $ad_index = 0;
                                }

                                $listing_counter = 0;
                                $ad_threshold = isset($adforest_theme['show_ads_after_a_no_of_listings']) ? intval($adforest_theme['show_ads_after_a_no_of_listings']) : 0;
                            }
                        }

                    endwhile;
                    ?>
                </div>
            <?php } elseif ($query->have_posts()) {
                ?>
                <div class="search-ads-result-box">
                    <?php
                    $search_page_list_adverts = $adforest_theme['search_page_list_adverts'];
                    $ads = explode('|', $search_page_list_adverts);
                    $total_ads = count($ads);
                    $ad_index = 0;

                    $ad_threshold = rand(3, 4);
                    $listing_counter = 0;
                    $site_currency = isset($adforest_theme['sb_currency']) && !empty($adforest_theme['sb_currency']) ? $adforest_theme['sb_currency'] : get_woocommerce_currency_symbol();
                    while ($query->have_posts()) : $query->the_post();
                        $listing_counter++;
                        $ad_details = get_ad_post_details(get_the_ID());
                        $category_names = $ad_details['category_names'];
                        $first_img = $ad_details['img'];
                        $truncated_location = $ad_details['location'];
                        $truncated_title = $ad_details['ad_title'];
                        $price_html = $ad_details['price_html'];
                        $ad_permalink = $ad_details['ad_link'];
                        $heart_class = $ad_details['heart_class'];
                        $is_featured = $ad_details['is_featured'];
                        $ad_categories_post = $ad_details['categories'];
                        ?>
                        <div class="adt-category-ad-list">
                            <div class="category-img-box">
                                <a href="<?php echo esc_url($ad_permalink); ?>">
                                    <img class="img-fluid"
                                         src="<?php echo esc_url($first_img); ?>"
                                         alt="<?php echo esc_html(get_the_title()); ?>">

                                    <?php if ($is_featured): ?>
                                        <span class="featured-label"><?php echo esc_html__( 'Featured', 'adforest' ); ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="category-content-box">
                                <?php
                                $is_fav_local    = ( strpos( (string) $heart_class, 'fas ' ) !== false );
                                $fav_title_local = $is_fav_local ? esc_html__( 'Click to remove from favourite', 'adforest' ) : esc_html__( 'Click to make it favourite', 'adforest' );
                                $fav_extra_local = $is_fav_local ? ' ad-favourited' : '';
                                ?>
                                <a href="javascript:void(0);"
                                   class="favourite ad_to_fav<?php echo esc_attr( $fav_extra_local ); ?>"
                                   data-adid="<?php echo get_the_ID(); ?>"
                                   data-toggle="tooltip"
                                   data-placement="top"
                                   title="<?php echo esc_attr( $fav_title_local ); ?>"
                                   aria-label="<?php echo esc_attr( $fav_title_local ); ?>">
                                    <i class="<?php echo esc_attr($heart_class); ?>"></i>
                                </a>
                                <?php
                                $category_links = [];

                                foreach ($ad_categories_post as $category) {
                                    $category_url = get_term_link($category);
                                    if (!is_wp_error($category_url)) {
                                        $category_links[] = '<a class="ctg-tag" href="' . esc_url($category_url) . '">' . esc_html($category->name) . '</a>';
                                    }
                                }

                                $category_links_string = implode(' > ', $category_links);
                                ?>
                                <div class="adt-ad-cats">
                                    <?php echo wp_kses_post($category_links_string); ?>
                                </div>
                                <a href="<?php the_permalink(); ?>">
                                    <h5><?php echo esc_html($truncated_title); ?></h5></a>
                                <p>
                                    <i class="fas fa-map-marker-alt"></i><?php echo esc_html($truncated_location); ?>
                                </p>
                                <div class="price-box">
                                    <?php echo esc_html($price_html); ?>
                                    <a href="<?php the_permalink(); ?>"
                                       class="detail-btn"><?php echo __("Detail", "adforest"); ?></a>
                                </div>
                            </div>
                        </div>
                        <?php

                        if (isset($adforest_theme['turn_on_list_adverts_search']) && $adforest_theme['turn_on_list_adverts_search'] == '1') {
                            if ($listing_counter == $ad_threshold && $total_ads > 0) {
                                echo '<div class="margin-tb-30">';
                                if ( function_exists( 'adforest_render_ad' ) ) {
                                    $map_list_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_list_adverts' ) : 'image';
                                    adforest_render_ad( $map_list_ad_type, $ads[ $ad_index ] );
                                } else {
                                    echo wp_kses( $ads[ $ad_index ], ADFOREST_ALLOWED_FORM_HTML );
                                }
                                echo '</div>';

                                $ad_index++;
                                if ($ad_index >= $total_ads) {
                                    $ad_index = 0;
                                }

                                $listing_counter = 0;
                                $ad_threshold = isset($adforest_theme['show_list_ads_after_a_no_of_listings']) ? intval($adforest_theme['show_list_ads_after_a_no_of_listings']) : 0;
                            }
                        }

                    endwhile;
                    ?>
                </div>
            <?php } else {
                $nothing_found = esc_url(get_template_directory_uri()) . '/images/nothing-found.png';
                echo '<div class="no_ads_found">
                    <img src="' . esc_url($nothing_found) . '" alt="">
                    <h3>' . __("No Ads found.", "adforest") . '</h3>
                  </div>';
            } ?>
            <?php
            if ($query->have_posts()) {
                if ($loading_ads_mode == 'show_more' || $loading_ads_mode == 'infinity_scroll') {
                    ?>
                    <div class="load-more-btn-box">
                        <button data-search-query='<?php echo esc_attr(json_encode($args)); ?>'
                                data-loading-mode="<?php echo esc_attr($loading_ads_mode); ?>"
                                data-ad-count="<?php echo esc_attr($total_ad_count); ?>"
                                data-search-page="map"
                                data-view-type="<?php echo esc_attr($view_type) ?>"
                                data-posts-per-page="<?php echo get_option('posts_per_page'); ?>"
                                class="adt-button-dark"
                                id="load-more-ads-btn">
                            <?php echo esc_html__( 'Show More', 'adforest' ); ?>
                        </button>
                    </div>
                <?php }
            } ?>
            <div class="m-2" id="no_more_ads_p"></div>
            <?php if ($loading_ads_mode == 'pagination') { ?>
                <nav aria-label="pagination">
                    <ul class="pagination adt-custom-pagination">
                        <?php
                        $total_pages = $query->max_num_pages;
                        if ($total_pages > 1) {
                            if ($paged > 1) {
                                echo '<li class="page-item"><a class="page-link prv" href="' . esc_url(get_pagenum_link($paged - 1)) . '"><i class="fas fa-chevron-left"></i></a></li>';
                            }
                            for ($i = 1; $i <= $total_pages; $i++) {
                                $active_class = ($i == $paged) ? ' active' : '';
                                echo '<li class="page-item"><a class="page-link' . $active_class . '" href="' . esc_url(get_pagenum_link($i)) . '">' . str_pad($i, 2, '0', STR_PAD_LEFT) . '</a></li>';
                            }
                            if ($paged < $total_pages) {
                                echo '<li class="page-item"><a class="page-link nxt" href="' . esc_url(get_pagenum_link($paged + 1)) . '"><i class="fas fa-chevron-right"></i></a></li>';
                            }
                        }
                        ?>
                    </ul>
                </nav>
            <?php } ?>
        </div>
        <div class="search-map-side" style="height: 100vh;">
            <div id="map" style="width: 100%; height: 100%; z-index:0"></div>
            <?php
            $pin_lat = $adforest_theme['sb_default_lat'];
            $pin_long = $adforest_theme['sb_default_long'];
            $get_directions_text = __("Get Directions", "adforest");
            $ads = [];
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $ad_detail = get_ad_post_details(get_the_ID());
                    $ad_image = $ad_detail['img'];
                    $ad_price_html = $ad_detail['price_html'];
                    $ad_truncated_location = $ad_detail['truncated_location'];
                    $ad_link = $ad_detail['ad_link'];
                    $ads[] = [
                        'title' => get_the_title(),
                        'lat' => get_post_meta(get_the_ID(), '_adforest_ad_map_lat', true),
                        'lng' => get_post_meta(get_the_ID(), '_adforest_ad_map_long', true),
                        'img' => $ad_image,
                        'price_html' => $ad_price_html,
                        'truncated_location' => $ad_truncated_location,
                        'link' => $ad_link,
                    ];
                }
                wp_reset_postdata();
            }

            if ($mapType === 'google_map') {
                ?>
                <script type="text/javascript">
                    function getUrlParams() {
                        const params = new URLSearchParams(window.location.search);
                        return {
                            location: params.get('location'),
                            radius: parseFloat(params.get('rd')) || null
                        };
                    }

                    function initGoogleMap() {
                        const {location, radius} = getUrlParams();

                        let defaultLat = <?php echo isset($ads[0]['lat']) && !empty($ads[0]['lat']) ? $ads[0]['lat'] : $pin_lat; ?>;
                        let defaultLng = <?php echo isset($ads[0]['lng']) && !empty($ads[0]['lng']) ? $ads[0]['lng'] : $pin_long; ?>;
                        let defaultZoom = <?php echo isset($adforest_theme['search_map_zoom']) ? intval($adforest_theme['search_map_zoom']) : 10; ?>;

                        let map = new google.maps.Map(document.getElementById('map'), {
                            zoom: defaultZoom,
                            center: {lat: defaultLat, lng: defaultLng},
                            mapTypeId: "<?php echo $adforest_theme['adforest_google_map_type'] ?? 'roadmap'; ?>",
                            styles: [
                                {
                                    featureType: 'poi',
                                    elementType: 'all',
                                    stylers: [{ visibility: 'off' }]
                                },
                                {
                                    featureType: 'transit.station',
                                    elementType: 'all',
                                    stylers: [{ visibility: 'off' }]
                                },
                            ]
                        });

                        if (location && radius) {
                            const geocoder = new google.maps.Geocoder();
                            geocoder.geocode({address: location}, function (results, status) {
                                if (status === 'OK' && results[0].geometry) {
                                    const loc = results[0].geometry.location;
                                    map.setCenter(loc);

                                    new google.maps.Circle({
                                        strokeColor: '#FF0000',
                                        strokeOpacity: 0.6,
                                        strokeWeight: 2,
                                        fillColor: '#FF0000',
                                        fillOpacity: 0.2,
                                        map: map,
                                        center: loc,
                                        radius: radius * 1000
                                    });
                                } else {
                                    console.warn('Geocode failed:', status);
                                }
                            });
                        }

                        let ads = <?php echo json_encode($ads); ?>;
                        let markers = [];

                        if (ads.length > 0) {
                            ads.forEach(function (ad) {
                                let customIcon = {
                                    url: '<?php echo esc_url($adforest_theme['search_map_marker']['url']); ?>',
                                    anchor: new google.maps.Point(25, 77)
                                };


                                let marker = "";
                                <?php
                                if(isset($adforest_theme['search_map_marker']['url']) && !empty($adforest_theme['search_map_marker']['url'])) {
                                ?>
                                marker = new google.maps.Marker({
                                    position: {lat: parseFloat(ad.lat), lng: parseFloat(ad.lng)},
                                    map: map,
                                    title: ad.title,
                                    icon: customIcon
                                });
                                <?php } else { ?>
                                marker = new google.maps.Marker({
                                    position: {lat: parseFloat(ad.lat), lng: parseFloat(ad.lng)},
                                    map: map,
                                    title: ad.title,
                                });
                                <?php } ?>

                                const getDirectionsText = "<?php echo esc_url(get_template_directory_uri()) . "/images/directions-right.svg"; ?>";

                                let infowindow = new google.maps.InfoWindow({
                                    content: `<div class="d-flex justify-content-center align-items-center flex-row gap-2">
                                            <img src="${ad.img}" style="width: 120px; min-height: 100px" alt="img" />
                                            <div class="py-2">
                                                <a href="${ad.link}" style="font-size:16px; font-weight:bold; color: #0c0c0c">${ad.title}</a>
                                                <p class="price_container_map_popup">${ad.price_html}</p>
                                                <small>${ad.truncated_location}</small>
                                                <a href="https://www.google.com/maps/dir/?api=1&destination=${ad.lat},${ad.lng}"
                                                   target="_blank"
                                                   style="font-size:14px; color:blue; text-decoration:underline;">
                                                    <img style="width: 20px; height: 20px" src="${getDirectionsText}" alt="directions" data-toggle="tooltip" data-placement="top" title="Get Direction" />
                                                </a>
                                            </div>
                                       </div>`
                                });

                                marker.addListener('click', function () {
                                    infowindow.open(map, marker);
                                });

                                markers.push(marker);
                            });
                        }

                        const markerCluster = new markerClusterer.MarkerClusterer({
                            map: map,
                            markers: markers,
                        });
                    }

                    function loadMarkerClustererScript() {
                        const script = document.createElement('script');
                        script.src = 'https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js';
                        script.onload = delayedInit;
                        document.head.appendChild(script);
                    }

                    function delayedInit() {
                        if (typeof google !== 'undefined' && typeof markerClusterer !== 'undefined') {
                            initGoogleMap();
                        } else {
                            console.error('Google Maps API or MarkerClusterer is not loaded.');
                        }
                    }

                    document.addEventListener("DOMContentLoaded", function () {
                        loadMarkerClustererScript();
                    });
                </script>
                <?php
            }

            if ($mapType === 'leafletjs_map') {
                $nominatim_cc_param = '';
                if (isset($adforest_theme['sb_location_allowed']) && !$adforest_theme['sb_location_allowed'] && isset($adforest_theme['sb_list_allowed_country']) && is_array($adforest_theme['sb_list_allowed_country'])) {
                    $nominatim_cc_param = '&countrycodes=' . implode(',', $adforest_theme['sb_list_allowed_country']);
                }
                $nominatim_ft_param = '';
                if (!isset($adforest_theme['sb_location_type']) || $adforest_theme['sb_location_type'] !== 'regions') {
                    $nominatim_ft_param = '&featuretype=city';
                }
                $nominatim_vb_param = '';
                $vb_lat = isset($adforest_theme['sb_default_lat']) ? floatval($adforest_theme['sb_default_lat']) : 0;
                $vb_lng = isset($adforest_theme['sb_default_long']) ? floatval($adforest_theme['sb_default_long']) : 0;
                if ($vb_lat || $vb_lng) {
                    $nominatim_vb_param = '&viewbox=' . ($vb_lng - 1) . ',' . ($vb_lat - 1) . ',' . ($vb_lng + 1) . ',' . ($vb_lat + 1) . '&bounded=0';
                }
                ?>
                <script type="text/javascript">
                    function getUrlParams() {
                        const params = new URLSearchParams(window.location.search);
                        return {
                            location: params.get('location'),
                            radius: parseFloat(params.get('rd')) || null
                        };
                    }

                    document.addEventListener('DOMContentLoaded', function () {
                        const {location, radius} = getUrlParams();

                        let defaultLat = <?php echo isset($ads[0]['lat']) && !empty($ads[0]['lat']) ? $ads[0]['lat'] : $pin_lat; ?>;
                        let defaultLng = <?php echo isset($ads[0]['lng']) && !empty($ads[0]['lng']) ? $ads[0]['lng'] : $pin_long; ?>;
                        let defaultZoom = <?php echo isset($adforest_theme['search_map_zoom']) ? intval($adforest_theme['search_map_zoom']) : 10; ?>;

                        let map = L.map('map', {
                            zoomSnap: 0.25,
                            zoomDelta: 0.5,
                            wheelDebounceTime: 80
                        }).setView([defaultLat, defaultLng], defaultZoom);

                        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                            maxZoom: 19,
                            detectRetina: true,
                            updateWhenZooming: false,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>'
                        }).addTo(map);

                        map.createPane('labels');
                        map.getPane('labels').style.zIndex = 450;
                        map.getPane('labels').style.pointerEvents = 'none';
                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            updateWhenZooming: false,
                            pane: 'labels',
                            minZoom: 5
                        }).addTo(map);

                        L.control.fullscreen().addTo(map);

                        var locateBtn = L.control({position: 'topleft'});
                        locateBtn.onAdd = function () {
                            var div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
                            div.innerHTML = '<a href="#" title="My Location" role="button"><i class="fa fa-crosshairs"></i></a>';
                            div.onclick = function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                map.locate({setView: true, maxZoom: 14});
                            };
                            return div;
                        };
                        locateBtn.addTo(map);

                        if (location && radius) {
                            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}<?php echo $nominatim_cc_param . $nominatim_ft_param . $nominatim_vb_param; ?>`;
                            fetch(url)
                                .then(res => res.json())
                                .then(data => {
                                    if (data.length > 0) {
                                        const loc = data[0];
                                        const latLng = [parseFloat(loc.lat), parseFloat(loc.lon)];

                                        map.setView(latLng, 12);

                                        L.circle(latLng, {
                                            color: 'red',
                                            fillColor: '#f03',
                                            fillOpacity: 0.2,
                                            radius: radius * 1000
                                        }).addTo(map);
                                    }
                                })
                                .catch(err => console.warn('Geocode failed', err));
                        }

                        let ads = <?php echo json_encode($ads); ?>;

                        let clusterIconUrl = '<?php echo isset($adforest_theme['search_map_marker_more']['url']) ? esc_url($adforest_theme['search_map_marker_more']['url']) : ''; ?>';
                        let markers = L.markerClusterGroup({
                            iconCreateFunction: function (cluster) {
                                let childCount = cluster.getChildCount();

                                if (clusterIconUrl !== '') {
                                    return L.icon({
                                        iconUrl: clusterIconUrl,
                                        iconSize: [60, 60],
                                        className: 'custom-cluster-icon'
                                    });
                                } else {
                                    return L.divIcon({
                                        html: `<div class="custom-cluster"><span>${childCount}</span></div>`,
                                        className: 'pulsing-cluster',
                                        iconSize: [40, 40]
                                    });
                                }
                            }
                        });

                        if (ads.length > 0) {
                            ads.forEach(function (ad) {

                                let markerIconUrl = '<?php echo isset($adforest_theme['search_map_marker']['url']) ? esc_url($adforest_theme['search_map_marker']['url']) : ''; ?>';
                                let adIcon = L.icon({
                                    iconUrl: markerIconUrl !== '' ? markerIconUrl : 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
                                    iconSize: markerIconUrl !== '' ? [40, 40] : [25, 41],
                                    iconAnchor: markerIconUrl !== '' ? [15, 30] : [12, 41]
                                });

                                let marker = L.marker([ad.lat, ad.lng], {icon: adIcon});

                                const getDirectionsText = "<?php echo esc_url(get_template_directory_uri()) . "/images/directions-right.svg"; ?>";

                                marker.bindPopup(`<div class="d-flex justify-content-center align-items-center flex-row gap-2">
                                                        <img src="${ad.img}" style="width: 120px; min-height: 100px" alt="img" />
                                                        <div class="py-2">
                                                            <a href="${ad.link}" style="font-size:16px; font-weight:bold; color: #0c0c0c">${ad.title}</a>
                                                            <p class="price_container_map_popup">${ad.price_html}</p>
                                                            <small>${ad.truncated_location}</small>
                                                            <a href="https://www.google.com/maps/dir/?api=1&destination=${ad.lat},${ad.lng}"
                                                               target="_blank"
                                                               style="font-size:14px; color:blue; text-decoration:underline;">
                                                                <img style="width: 20px; height: 20px" src="${getDirectionsText}" alt="directions" data-toggle="tooltip" data-placement="top" title="Get Direction" />
                                                            </a>
                                                        </div>
                                                   </div>
                                                `);

                                markers.addLayer(marker);
                            });

                            map.addLayer(markers);
                        }

                    });
                </script>
                <?php
            }

            ?>
        </div>
    </div>
</section>
<!-- adt-map-search-section-end -->