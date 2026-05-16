<?php
/**
 * Child-theme override: adforest_render_ads_in_search()
 *
 * Source: adforest/inc/utilities.php lines 2838-3263 (parent theme v6.0.13)
 * Patch:  Replace wp_kses_post($adforest_theme['search_ad_720_2'])
 *         with adforest_render_theme_ad('search_ad_720_2')
 *         so <script> tags survive to the rendered page.
 * See:    PATCHES.md — Patch #1
 */
if (!function_exists(('adforest_render_ads_in_search'))) {
    function adforest_render_ads_in_search($query, $style_for_infinity_scroll, $loading_ads_mode, $paged, $args, $ad_count)
    {
        global $adforest_theme;
        $view_type = (isset($_GET['view-type']) && $_GET['view-type'] !== '') ? $_GET['view-type'] : (isset($adforest_theme['search_ad_layout_for_search']) ? $adforest_theme['search_ad_layout_for_search'] : 'grid');
        if ($query->have_posts() && ($view_type == 'list')) { ?>
            <div class="adt-search-ads-list" <?php echo ($style_for_infinity_scroll); ?>>
                <?php
                $site_currency = isset($adforest_theme['sb_currency']) && !empty($adforest_theme['sb_currency']) ? $adforest_theme['sb_currency'] : get_woocommerce_currency_symbol();
                $search_page_list_adverts = $adforest_theme['search_page_list_adverts'];
                $ads = explode('|', $search_page_list_adverts);
                $total_ads = count($ads);
                $ad_index = 0;

                $ad_threshold = rand(3, 4);
                $listing_counter = 0;
                while ($query->have_posts()) : $query->the_post();
                    $listing_counter++;
                    $ad_details = get_ad_post_details(get_the_ID());
                    $first_img = $ad_details['img'];
                    $truncated_location = $ad_details['location'];
                    $truncated_title = $ad_details['ad_title'];
                    $price_html = $ad_details['price_html'];
                    $ad_permalink = $ad_details['ad_link'];
                    $heart_class = $ad_details['heart_class'];
                    $is_fav      = isset($ad_details['is_fav']) ? (bool) $ad_details['is_fav'] : false;
                    $fav_title   = $is_fav ? esc_html__( 'Click to remove from favourite', 'adforest' ) : esc_html__( 'Click to make it favourite', 'adforest' );
                    $fav_extra   = $is_fav ? ' ad-favourited' : '';
                    $is_featured = $ad_details['is_featured'];
                    $all_ad_images = $ad_details['all_ad_images'];
                    $ad_poster_img = $ad_details['ad_poster_img'];
                    $ad_poster_name = $ad_details['ad_poster_name'];
                    $ad_title = get_the_title();
                    $location = $ad_details['location'];
                    $content = get_the_content();
                    $clean_content = wp_strip_all_tags($content);
                    $truncated_content = truncate_string($clean_content, 120);
                    $ad_categories_post = $ad_details['categories'];
                    if (function_exists('adforest_comments_pagination2')) {
                        $page = (isset($_GET['page-number'])) ? $_GET['page-number'] : 1;
                    } else {
                        $page = (get_query_var('page')) ? get_query_var('page') : 1;
                    }

                    $limit = $adforest_theme['sb_rating_max'] ?? 0;
                    $offset = ($page * $limit) - $limit;
                    $comment_args = array(
                        'type__in' => array('ad_post_rating'),
                        'number' => $limit,
                        'offset' => $offset,
                        'parent' => 0,
                        'post_id' => get_the_ID(),
                    );

                    $comments = get_comments($comment_args);
                    $get_percentage = adforest_fetch_reviews_average(get_the_ID());

                    if (isset($adforest_theme['adforest_list_layout']) && $adforest_theme['adforest_list_layout'] == '1') {
                        ?>
                        <div class="adt-category-ad-list">
                            <div class="category-img-box">
                                <a href="<?php echo esc_url($ad_permalink, 'adforest'); ?>">
                                    <img class="img-fluid"
                                         src="<?php echo esc_url($first_img, "adforest"); ?>"
                                         alt="<?php echo esc_html(get_the_title()); ?>">

                                    <?php if ($is_featured): ?>
                                        <span class="featured-label"><?php echo __('Featured', 'adforest'); ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
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
                            <div class="category-content-box">
                                <a href="javascript:void(0);"
                                   class="favourite ad_to_fav<?php echo esc_attr( $fav_extra ); ?>"
                                   data-adid="<?php echo get_the_ID(); ?>"
                                   data-toggle="tooltip"
                                   data-placement="top"
                                   title="<?php echo esc_attr( $fav_title ); ?>"
                                   aria-label="<?php echo esc_attr( $fav_title ); ?>">
                                    <i class="<?php echo esc_attr($heart_class); ?>"></i>
                                </a>
                                <div class="adt-ad-cats">
                                    <?php echo wp_kses($category_links_string, ADFOREST_ALLOWED_FORM_HTML); ?>
                                </div>
                                <a href="<?php the_permalink(); ?>">
                                    <h5><?php echo esc_html($truncated_title); ?></h5></a>
                                <p>
                                    <i class="fas fa-map-marker-alt"></i><?php echo esc_html($truncated_location); ?>
                                </p>
                                <div class="price-box">
                                    <?php echo wp_kses($price_html, ADFOREST_ALLOWED_FORM_HTML); ?>
                                    <a href="<?php the_permalink(); ?>"
                                       class="detail-btn"><?php echo __("Detail", "adforest"); ?></a>
                                </div>
                            </div>
                        </div>
                        <?php
                    } elseif (isset($adforest_theme['adforest_list_layout']) && $adforest_theme['adforest_list_layout'] == '2') {
                        ?>
                        <div class="adt-car-dealer-card">
                            <div class="adt-car-ad-carousel owl-carousel owl-theme">
                                <?php foreach ($all_ad_images as $image_url) { ?>
                                    <div class="item"><img src="<?php echo esc_url($image_url); ?>"
                                                           alt="<?php echo esc_html(get_the_title()) ?>">
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="adt-car-content-box">
                                <div class="adt-car-meta-box">
                                    <div class="rating-box">
                                        <span class="rating"><?php echo esc_html(isset($get_percentage['average']) ? $get_percentage['average'] : "0.0"); ?></span>
                                        <?php
                                        if (isset($get_percentage) && count($get_percentage['ratings']) > 0) {
                                            echo adforest_return_echo($get_percentage['total_stars']);
                                        } else {
                                            echo str_repeat('<i class="far fa-star" aria-hidden="true"></i>', 5);
                                        }
                                        ?>
                                        <span class="reviews"><?php echo is_array($comments) ? count($comments) : 0 . __(" Reviews", 'adforest'); ?></span>
                                    </div>
                                    <a href="<?php echo esc_url($ad_permalink, "adforest"); ?>">
                                        <h3><?php echo esc_html($ad_title); ?></h3></a>
                                    <ul>
                                        <li>
                                            <i class="fas fa-location-arrow"></i><?php echo esc_html($location); ?>
                                        </li>
                                        <li><i class="far fa-calendar-alt"></i><?php echo get_the_date(); ?></li>
                                        <li>
                                            <img src="<?php echo esc_url($ad_poster_img, "adforest"); ?>"
                                                 alt="author"><?php echo esc_html($ad_poster_name); ?>
                                        </li>
                                    </ul>
                                    <p><?php echo esc_html($truncated_content); ?></p>
                                </div>
                                <div class="adt-car-price-meta">
                                    <div class="price-box">
                                        <?php if (isset($formatted_price)) { ?>
                                            <span><?php echo esc_html($site_currency) . $formatted_price; ?></span>
                                        <?Php } ?>
                                        <?php if (isset($ad_price_type) && !empty($ad_price_type)) { ?>
                                            <small>(<?php echo esc_html($ad_price_type); ?>)</small>
                                        <?php } ?>
                                    </div>
                                    <div class="detail-btn-box">
                                            <span class="favorite ad_to_fav<?php echo esc_attr( $fav_extra ); ?>"
                                                  data-adid="<?php echo get_the_ID(); ?>"
                                                  data-toggle="tooltip"
                                                  data-placement="top"
                                                  title="<?php echo esc_attr( $fav_title ); ?>"
                                                  aria-label="<?php echo esc_attr( $fav_title ); ?>">
                                                <i class="<?php echo esc_attr($heart_class); ?>"></i>
                                            </span>
                                        <a href="<?php echo esc_url($ad_permalink, "adforest"); ?>"
                                           class="adt-button-dark-1"><?php echo __("Details", "adforest"); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }

                    if (isset($adforest_theme['turn_on_list_adverts_search']) && $adforest_theme['turn_on_list_adverts_search'] == '1') {
                        if ($listing_counter == $ad_threshold && $total_ads > 0) {
                            echo '<div class="margin-tb-30">';
                            if ( function_exists( 'adforest_render_ad' ) ) {
                                $list_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_list_adverts' ) : 'image';
                                adforest_render_ad( $list_ad_type, $ads[$ad_index] );
                            } else {
                                echo wp_kses( $ads[$ad_index], ADFOREST_ALLOWED_FORM_HTML );
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

                endwhile; ?>
            </div>
            <?php
            if ($query->have_posts()) {
                if ($loading_ads_mode == 'show_more' || $loading_ads_mode == 'infinity_scroll') {
                    ?>
                    <div class="d-flex justify-content-center">
                        <button data-search-query='<?php echo json_encode($args); ?>'
                                data-loading-mode="<?php echo esc_attr($loading_ads_mode); ?>"
                                data-ad-count="<?php echo esc_attr($ad_count); ?>"
                                data-posts-per-page="<?php echo get_option('posts_per_page'); ?>"
                                data-view-type="<?php echo esc_attr($view_type); ?>"
                                class="adt-button-dark"
                                id="load-more-ads-btn"><?php echo esc_html('Show More', 'adforest'); ?></button>
                    </div>
                <?php }
            } ?>
            <div class="m-2" id="no_more_ads_p"></div>
            <?php if ($loading_ads_mode == 'pagination') {
                $total_pages = $query->max_num_pages;
                if ($total_pages > 1) {
                    ?>
                    <nav aria-label="pagination">
                        <ul class="pagination adt-custom-pagination">
                            <?php
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
                            ?>
                        </ul>
                    </nav>
                <?php }
            } ?>
            <?php
        } elseif ($query->have_posts()) {
            $grid_cols = $adforest_theme['no_of_ad_in_search_page_row'] ?? '';
            $sb_2column = (isset($adforest_theme['sb_2column_mobile_layout']) && $adforest_theme['sb_2column_mobile_layout'] == false) ? "one-column-mobile-layout" : "";
            ?>
            <div class="adt-search-ads-grid <?php echo "adt-search-ads-col-" . esc_attr($grid_cols); ?> <?php echo esc_attr($sb_2column) ?>" <?php echo ($style_for_infinity_scroll); ?>>
                <?php
                $search_page_adverts = (isset($adforest_theme['search_page_grid_adverts']) ? $adforest_theme['search_page_grid_adverts'] : '');
                $ads = explode('|', $search_page_adverts);
                $total_ads = count($ads);
                $ad_index = 0;
                $title_limit = 40;
                $location_limit = 40;
                if (isset($adforest_theme['sb_ad_title_limit_on']) && $adforest_theme['sb_ad_title_limit_on'] == '1') {
                    $title_limit = isset($adforest_theme['sb_ad_title_limit']) ? $adforest_theme['sb_ad_title_limit'] : 40;
                }

                if (isset($adforest_theme['sb_ad_location_limit_on']) && $adforest_theme['sb_ad_location_limit_on'] == '1') {
                    $location_limit = isset($adforest_theme['sb_ad_location_limit']) ? $adforest_theme['sb_ad_location_limit'] : 40;
                }

                $ad_threshold = rand(3, 4);
                $listing_counter = 0;
                while ($query->have_posts()) {
                    $query->the_post();
                    $listing_counter++;
                    $ad_details = get_ad_post_details(get_the_ID());
                    $first_img = $ad_details['img'];
                    $truncated_location = truncate_string($ad_details['location'], $location_limit);
                    $truncated_title = truncate_string($ad_details['ad_title'], $title_limit);
                    $price_html = $ad_details['price_html'];
                    $ad_permalink = $ad_details['ad_link'];
                    $heart_class = $ad_details['heart_class'];
                    $is_fav      = isset($ad_details['is_fav']) ? (bool) $ad_details['is_fav'] : false;
                    $fav_title   = $is_fav ? esc_html__( 'Click to remove from favourite', 'adforest' ) : esc_html__( 'Click to make it favourite', 'adforest' );
                    $fav_extra   = $is_fav ? ' ad-favourited' : '';
                    $is_featured = $ad_details['is_featured'];
                    $all_ad_images = $ad_details['all_ad_images'];
                    $ad_poster_img = $ad_details['ad_poster_img'];
                    $ad_poster_name = $ad_details['ad_poster_name'];
                    $ad_title = truncate_string($ad_details['ad_title'], $title_limit);
                    $featured_tag = $is_featured ? '<img style="transform: rotate(180deg);" src="' . esc_url(get_template_directory_uri()) . '/images/featured.png' . '" alt="featured-tag" class="featured-tag">' : '';
                    $ad_categories_post = $ad_details['categories'];
                    $top_bar_specific_style = '';
                    if ($adforest_theme['search_design'] == 'topbar') {
                        $top_bar_specific_style = 'top_bar_specific_style';
                    }
                    $ad_type = get_post_meta(get_the_ID(), '_adforest_ad_type', true);
                    if (isset($adforest_theme['adforest_grid_layout']) && $adforest_theme['adforest_grid_layout'] == 'simple') {
                        echo adforest_ad_grid_1($ad_permalink, $first_img, $is_featured, $ad_categories_post, $ad_details, $truncated_title, $truncated_location, $price_html, $heart_class);
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
                            $search_page_adverts = (isset($adforest_theme['search_page_grid_adverts']) ? $adforest_theme['search_page_grid_adverts'] : '');

                            if (strpos($search_page_adverts, '<!--ADSEPARATOR-->') !== false) {
                                $ads = explode('<!--ADSEPARATOR-->', $search_page_adverts);
                            } elseif (strpos($search_page_adverts, '||SEPARATOR||') !== false) {
                                $ads = explode('||SEPARATOR||', $search_page_adverts);
                            } elseif (strpos($search_page_adverts, '###') !== false) {
                                $ads = explode('###', $search_page_adverts);
                            } else {
                                $ads = array($search_page_adverts);
                            }

                            $total_ads = count($ads);
                            $current_ad = trim($ads[$ad_index]);

                            if (!empty($current_ad)) {
                                if ( function_exists( 'adforest_render_ad' ) ) {
                                    $grid_ad_type = function_exists( 'adforest_get_ad_type' ) ? adforest_get_ad_type( 'search_page_grid_adverts' ) : 'image';
                                    adforest_render_ad( $grid_ad_type, $current_ad );
                                } else {
                                    echo wp_kses_post( $current_ad );
                                }
                            }

                            $ad_index++;
                            if ($ad_index >= $total_ads) {
                                $ad_index = 0;
                            }

                            $listing_counter = 0;
                            $ad_threshold = isset($adforest_theme['show_ads_after_a_no_of_listings']) ? intval($adforest_theme['show_ads_after_a_no_of_listings']) : 0;
                        }
                    }
                }
                ?>
            </div>
            <?php
            if ($query->have_posts()) {
                if ($loading_ads_mode == 'show_more' || $loading_ads_mode == 'infinity_scroll') {
                    ?>
                    <div class="d-flex justify-content-center">
                        <button data-search-query='<?php echo json_encode($args); ?>'
                                data-loading-mode="<?php echo esc_attr($loading_ads_mode); ?>"
                                data-ad-count="<?php echo esc_attr($ad_count); ?>"
                                data-posts-per-page="<?php echo get_option('posts_per_page'); ?>"
                                data-view-type="<?php echo esc_attr($view_type); ?>"
                                class="adt-button-dark"
                                id="load-more-ads-btn"><?php echo esc_html('Show More', 'adforest'); ?></button>
                    </div>
                <?php }
            } ?>
            <div class="m-2" id="no_more_ads_p"></div>
            <?php if ($loading_ads_mode == 'pagination') {
                $total_pages = $query->max_num_pages;
                if ($total_pages > 1) {
                    $range = 2; // Number of pages to show on each side of current page
                    $showitems = ($range * 2) + 1; // Total items to show
                    ?>
                    <nav aria-label="pagination">
                        <ul class="pagination adt-custom-pagination">
                            <?php
                            // Previous button
                            if ($paged > 1) {
                                echo '<li class="page-item"><a class="page-link prv" href="' . esc_url(get_pagenum_link($paged - 1)) . '"><i class="fas fa-chevron-left"></i></a></li>';
                            }

                            // First page
                            if ($paged > ($range + 1) && $showitems < $total_pages) {
                                echo '<li class="page-item"><a class="page-link" href="' . esc_url(get_pagenum_link(1)) . '">01</a></li>';
                            }

                            // First ellipsis
                            if ($paged > ($range + 2) && $showitems < $total_pages) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            // Page numbers around current page
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if (1 != $total_pages && !($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $total_pages <= $showitems) {
                                    $active_class = ($i == $paged) ? ' active' : '';
                                    echo '<li class="page-item"><a class="page-link' . $active_class . '" href="' . esc_url(get_pagenum_link($i)) . '">' . str_pad($i, 2, '0', STR_PAD_LEFT) . '</a></li>';
                                }
                            }

                            // Last ellipsis
                            if ($paged < $total_pages - $range - 1 && $showitems < $total_pages) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            // Last page
                            if ($paged < $total_pages - $range && $showitems < $total_pages) {
                                echo '<li class="page-item"><a class="page-link" href="' . esc_url(get_pagenum_link($total_pages)) . '">' . str_pad($total_pages, 2, '0', STR_PAD_LEFT) . '</a></li>';
                            }

                            // Next button
                            if ($paged < $total_pages) {
                                echo '<li class="page-item"><a class="page-link nxt" href="' . esc_url(get_pagenum_link($paged + 1)) . '"><i class="fas fa-chevron-right"></i></a></li>';
                            }
                            ?>
                        </ul>
                    </nav>
                <?php }
            } ?>
        <?php } else {
            $nothing_found = esc_url(get_template_directory_uri()) . '/images/nothing-found.png';
            echo '<div class="no_ads_found">
                    <img src="' . esc_url($nothing_found) . '" alt="">
                    <h3>' . __("No Ads found.", "adforest") . '</h3>
                  </div>';
        } ?>
        <?php
        if (isset($adforest_theme['search_ad_720_2']) && $adforest_theme['search_ad_720_2'] != "" && $query->have_posts()) {
            ?>
            <div class="col-md-12">
                <div class="margin-bottom-30 margin-top-10 text-center">
                    <?php adforest_render_theme_ad('search_ad_720_2'); ?>
                </div>
            </div>
            <?php
        }
        ?>
        <?php
    }
}

/* adforest search params */
