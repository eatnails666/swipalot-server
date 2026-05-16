<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined('ABSPATH') || exit;

global $adforest_theme;
if (isset($adforest_theme['shop-turn-on']) && $adforest_theme['shop-turn-on']) {
    get_header();
    $category = get_queried_object();
    $refresh_url = isset($category->term_id) ? get_term_link($category->term_id) : get_permalink(wc_get_page_id('shop'));

    $layoutCol = (isset($adforest_theme['shop-layout-col']) && $adforest_theme['shop-layout-col'] == true) ? $adforest_theme['shop-layout-col'] : 'col-lg-3';
    $container_class = 'listing-list-items';
    if ('col-lg-4' == $layoutCol) {
        $container_class = 'listing-list-item-1s';
    }

    $sidebar_position = (isset($adforest_theme['shop-sidebar-position'])) ? $adforest_theme['shop-sidebar-position'] : 'left';
    global $wpdb;

    $current_page = max(1, get_query_var('paged'));
    $posts_per_page = get_option('posts_per_page');
    $offset = ($current_page - 1) * $posts_per_page;

    $query_args = array(
        'post_type' => 'product',
        'posts_per_page' => $posts_per_page,
        'paged' => $current_page,
        'meta_query' => array(),
        'tax_query' => array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => 'simple',
            ),
        ),
    );

    $the_query = new WP_Query($query_args);


    $rating = isset($_GET['rating']) ? (int)$_GET['rating'] : '';
    $title = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : '';
    $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : '';
    $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : '';
    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : '';

    if ($rating) {
        $query_args['meta_query'][] = array(
            'key' => '_wc_average_rating',
            'value' => $rating,
            'compare' => '>=',
            'type' => 'NUMERIC',
        );
    }

    if (!empty($title)) {
        $query_args['s'] = $title;
    }

    if (!empty($min_price) || !empty($max_price)) {
        $price_query = array(
            'key' => '_price',
            'compare' => 'BETWEEN',
            'value' => array($min_price, $max_price),
            'type' => 'NUMERIC',
        );
        $query_args['meta_query'][] = $price_query;
    }

    if ($category_id) {
        $query_args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => $category_id,
        );
    }

    if (isset($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'ascend':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'ASC';
                break;
            case 'price-descending':
                $query_args['meta_key'] = '_price';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = 'DESC';
                break;
            case 'price-ascending':
                $query_args['meta_key'] = '_price';
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = 'ASC';
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
                break;
        }
    }

    $products = new WP_Query($query_args);

    $adt_container_class = "";
    if ( isset( $adforest_theme['sb_header'] ) && ( $adforest_theme['sb_header'] == "white" || $adforest_theme['sb_header'] == "header_w_topbar" ) ) {
        $adt_container_class = "adt-container";
    }

    $page_id = get_queried_object_id();

    $page_header_style = get_post_meta($page_id, '_page_header_style', true);

    if(isset($page_header_style) && ($page_header_style == "white" || $page_header_style == "header_w_topbar" || $page_header_style == "vendor-2")) {
        $adt_container_class = "adt-container";
    }
    ?>

    <section class="listing-list-salesman">
        <div class="first-heading">
            <?php adforest_custom_breadcrumbs(); ?>
        </div>
    </section>

    <!-- adt-multivendor-search-section-start -->
    <section class="adt-multivendor-search-section">
        <div class="container <?php echo esc_attr($adt_container_class); ?>">
            <div class="row">
                <div class="col-lg-12">
                    <div class="adt-multivendor-search-wrapper">
                        <div class="adt-multivendor-search-filters-sidebar adt-ads-filter-sidebar">
                            <?php dynamic_sidebar('adforest_woocommerce_widget'); ?>
                        </div>
                        <div class="adt-multivendor-search-results-content">
                            <div class="adt-ads-sort-box">
                                <h3><?php echo esc_html( $products->found_posts ) . esc_html__(" Products Found:", 'adforest'); ?></h3>
                                <div class="filter-box">
                                    <form method="GET" action="<?php echo esc_url($refresh_url); ?>" id="sort-form">
                                        <a href="<?php echo esc_url($refresh_url); ?>" class="filter-refresh-btn">
                                            <i class="fas fa-redo-alt"></i><?php echo esc_html__("Refresh", "adforest"); ?>
                                        </a>
                                        <select class="default-select" id="sort-select" name="sort"
                                                onchange="document.getElementById('sort-form').submit();">
                                            <option value="descend" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'descend'); ?>><?php echo esc_html__("Newest To Oldest", "adforest"); ?></option>
                                            <option value="ascend" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'ascend'); ?>><?php echo esc_html__("Oldest To Newest", "adforest"); ?></option>
                                            <option value="price-descending" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'price-descending'); ?>><?php echo esc_html__("Price High to Low", "adforest"); ?></option>
                                            <option value="price-ascending" <?php selected(isset($_GET['sort']) ? $_GET['sort'] : '', 'price-ascending'); ?>><?php echo esc_html__("Price Low to High", "adforest"); ?></option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            <div class="adt-horizontal-ad-box">
                                <?php
                                if ( function_exists( 'adforest_render_theme_ad' ) ) {
                                    adforest_render_theme_ad( 'shop_advertisement_topp' );
                                } else {
                                    echo wp_kses_post( isset( $adforest_theme['shop_advertisement_topp'] ) ? $adforest_theme['shop_advertisement_topp'] : '' );
                                }
                                ?>
                            </div>
                            <?php
                            if ($products->have_posts()) : ?>
                                <div class="adt-multivendor-product-grid">
                                    <?php while ($products->have_posts()) : $products->the_post();
                                        global $product;
                                        $heart_filled = 'fa-heart';
                                        $fav_class = "";
                                        if (get_user_meta(get_current_user_id(), '_product_fav_id_' . get_the_ID(), true) == get_the_ID()) {
                                            $fav_class = 'favourited';
                                            $heart_filled = 'fa-heart';
                                        }
                                        ?>
                                        <div class="adt-multivendor-category-ad-card">
                                            <div class="category-img-box">
                                                <?php
                                                $image_id = $product->get_image_id();
                                                $image_url = wp_get_attachment_image_url($image_id, 'full');

                                                $default_image = trailingslashit(get_template_directory_uri()) . 'images/no-image.jpg';
                                                $img_src = !empty($image_url) ? $image_url : $default_image;
                                                ?>
                                                <a href="<?php the_permalink(); ?>"><img class="img-fluid"
                                                                                         src="<?php echo esc_url($img_src); ?>"
                                                                                         alt="ad-img"></a>
                                            </div>
                                            <div class="category-content-box">
                                                <div class="rating">
                                                    <span><?php echo esc_html($product->get_average_rating()); ?></span>
                                                    <small><?php echo esc_html($product->get_rating_count()) . ' ' . esc_html__("Reviews", "adforest"); ?></small>
                                                </div>
                                                <a href="<?php the_permalink(); ?>">
                                                    <h5><?php echo esc_html(truncate_string(get_the_title(), $adforest_theme['sb_ad_title_limit'])); ?></h5>
                                                </a>
                                                <?php
                                                $regular_price = $product->get_regular_price();
                                                $sale_price = $product->get_sale_price();
                                                ?>

                                                <strong class="price">
                                                    <?php if (!empty($sale_price)) : ?>
                                                        <del><?php echo wc_price($regular_price); ?></del>
                                                        <ins><?php echo wc_price($sale_price); ?></ins>
                                                    <?php else : ?>
                                                        <?php echo wc_price($regular_price); ?>
                                                    <?php endif; ?>
                                                </strong>
                                                <div class="detail-btn-box">
                                                    <a href="<?php the_permalink(); ?>"
                                                       class="detail-btn"><?php echo esc_html__("Detail Now", "adforest"); ?></a>
                                                    <a href="javascript:void(0);"
                                                       class="favourite product_to_fav  <?php echo esc_attr($fav_class); ?>"
                                                       data-productId="<?php echo get_the_ID(); ?>">
                                                        <i class="fa <?php echo esc_attr($heart_filled); ?>"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <nav aria-label="Page navigation example">
                                    <ul class="pagination adt-custom-pagination margin-bottom-30">
                                        <?php
                                        $current_page = max(1, get_query_var('paged'));
                                        $total_pages = $products->max_num_pages;

                                        if ($current_page > 1) {
                                            echo '<li class="page-item"><a class="page-link prv" href="' . esc_url(get_pagenum_link($current_page - 1)) . '"><i class="fas fa-chevron-left"></i></a></li>';
                                        } else {
                                            echo '<li class="page-item disabled"><a class="page-link prv" href="#" tabindex="-1" aria-disabled="true"><i class="fas fa-chevron-left"></i></a></li>';
                                        }

                                        $pages = array();

                                        for ($i = 1; $i <= min(3, $total_pages); $i++) {
                                            $pages[] = $i;
                                        }

                                        $start_range = max(1, $current_page - 1);
                                        $end_range = min($total_pages, $current_page + 1);
                                        for ($i = $start_range; $i <= $end_range; $i++) {
                                            $pages[] = $i;
                                        }

                                        for ($i = max(4, $total_pages - 1); $i <= $total_pages; $i++) {
                                            $pages[] = $i;
                                        }

                                        $pages = array_unique($pages);
                                        sort($pages);

                                        $prev_page = 0;
                                        foreach ($pages as $page) {
                                            if ($prev_page && ($page - $prev_page > 1)) {
                                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                            }
                                            if ($page === $current_page) {
                                                echo '<li class="page-item"><a class="page-link active" href="#">' . str_pad($page, 2, '0', STR_PAD_LEFT) . '</a></li>';
                                            } else {
                                                echo '<li class="page-item"><a class="page-link" href="' . esc_url(get_pagenum_link($page)) . '">' . str_pad($page, 2, '0', STR_PAD_LEFT) . '</a></li>';
                                            }
                                            $prev_page = $page;
                                        }

                                        if ($current_page < $total_pages) {
                                            echo '<li class="page-item"><a class="page-link nxt" href="' . esc_url(get_pagenum_link($current_page + 1)) . '"><i class="fas fa-chevron-right"></i></a></li>';
                                        } else {
                                            echo '<li class="page-item disabled"><a class="page-link nxt" href="#" tabindex="-1" aria-disabled="true"><i class="fas fa-chevron-right"></i></a></li>';
                                        }
                                        ?>
                                    </ul>
                                </nav>
                            <?php else: ?>
                                <p><?php echo esc_html__("No products found.", "adforest"); ?></p>
                            <?php endif;
                            wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                </div>
                <?php
                $services = $adforest_theme['services_boxes'];

                if (!empty($services)) {
                    foreach ($services as $service) {
                        $icon = !empty($service['url']) ? esc_attr($service['url']) : '';
                        $title = !empty($service['title']) ? esc_html($service['title']) : '';
                        $description = !empty($service['description']) ? esc_html($service['description']) : '';

                        echo '<div class="col-6 col-lg-3">';
                        echo '  <div class="adt-multivendor-services-box">';
                        echo '      <i class="' . $icon . '"></i>';
                        echo '      <h6>' . $title . '</h6>';
                        echo '      <p>' . $description . '</p>';
                        echo '  </div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </section>
    <!-- adt-multivendor-search-section-end -->
    <?php
    get_footer();
} else {
    $sb_packages_page = apply_filters('adforest_language_page_id', $adforest_theme['sb_packages_page']);
    $redirect_url = home_url();

    echo '
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                setTimeout(function() {
                    window.location.href = "' . esc_url($redirect_url) . '";
                }, 1000);
            });
        </script>
        ';
    exit;
}
?>