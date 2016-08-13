<?php
/**
 * WooCommerce Coupon Links
 *
 * @package   WooCommerceCouponLinks
 * @author    Luke McDonald
 * @author    Brady Vercher
 * @link      http://www.cedaro.com/
 * @copyright Copyright (c) 2015 Cedaro, Inc.
 * @license   GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: WooCommerce Coupon Links
 * Plugin URI: https://github.com/cedaro/woocommerce-coupon-links
 * Description: Automatically apply a WooCommerce coupon code to the cart with a url.
 * Version: 2.0.2
 * Author: Cedaro
 * Author URI: http://www.cedaro.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: cedaro/woocommerce-coupon-links
 */

/**
 * Automatically apply a coupon passed via URL to the cart.
 *
 * @since 1.0.0
 */
function cedaro_woocommerce_coupon_links() {
	// Bail if WooCommerce or sessions aren't available.
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	/**
	 * Filter the coupon code query variable name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query_var Query variable name.
	 */
	$query_var = apply_filters( 'woocommerce_coupon_links_query_var', 'coupon_code' );

	// Bail if a coupon code isn't in the query.
	if ( empty( get_query_var( $query_var ) ) ) {
		global $cedaro_coupon;
		if ( ! empty( $cedaro_coupon ) ) {
			$coupon = $cedaro_coupon;
		} else {
			return;
		}
	} else {
		$coupon = get_query_var( $query_var );
	}

	// Set a session cookie to persist the coupon in case the cart is empty.
	WC()->session->set_customer_session_cookie( true );

	// Apply the coupon to the cart if necessary.
	if ( ! WC()->cart->has_discount( $coupon ) ) {
		// WC_Cart::add_discount() sanitizes the coupon code.
		WC()->cart->add_discount( $coupon );
	}
}

/*
 * Display the URL on the coupon page.
 *
 * @since 2.0.3
 */
function cedaro_show_coupon_url() {
	// Get the coupon code query variable.
	$query_var = apply_filters( 'woocommerce_coupon_links_query_var', 'coupon_code' );

	if ( get_option('permalink_structure') ) {
		// Rewrite is enabled
		$url_template = get_home_url() . "/$query_var/{coupon}";
	} else {
		$url_template = get_home_url() . "?$query_var={coupon}";
	}

	?>
	<p class="form-field coupon_url_field">
		<span id="coupon-url-label"><?php esc_html_e( 'Coupon URL', 'cedaro-coupon-links' ); ?></span>
		<span id="coupon-url" data-template="<?php echo esc_attr( $url_template ); ?>"><?php echo esc_html( str_replace( '{coupon}', get_the_title(), $url_template ) ); ?></span>
		<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'This field displays the URL that can be used to directly add this coupon. The URL will work in conjunction with other query string parameters. An example of this would be adding a product to the cart while at the same time applying the coupon.', 'cedaro-coupon-links' ); ?>"></span>
	</p>
	<?php
}

/*
 * Enqueue style and JavaScript needed for proper display of the URL on
 * the coupon page and also to make it easier to copy the URL.
 *
 * @since 2.0.3
 */
function cedaro_enqueue_coupon_url_styles() {
	$screen = get_current_screen();

	if ( ! empty( $screen ) && 'shop_coupon' === $screen->id ) {
		wp_enqueue_script( 'woocommerce-coupon-links', plugin_dir_url( __FILE__ ) . 'woocommerce-coupon-links-admin.js', array( 'jquery' ), '2.0.3', false );
		wp_enqueue_style( 'woocommerce-coupon-links', plugin_dir_url( __FILE__ ) . 'woocommerce-coupon-links-admin.css', array(), '2.0.3', 'all' );
	}
}

/**
 * Sets up rewrite rules for coupons.
 *
 * @since 2.0.3
 */
function cedaro_add_coupon_rewrite() {
	// Get the coupon code query variable.
	$query_var = apply_filters( 'woocommerce_coupon_links_query_var', 'coupon_code' );

	add_rewrite_endpoint( $query_var, EP_ALL );
}

/**
 * Removes our coupon query arg so as not to interfere with the WP query, see https://core.trac.wordpress.org/ticket/25143
 *
 * @param WP_Query $query The current query.
 *
 * @since 2.0.3
 */
function cedaro_unset_query_arg( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Get the coupon code query variable.
	$query_var = apply_filters( 'woocommerce_coupon_links_query_var', 'coupon_code' );

	global $cedaro_coupon;
	$cedaro_coupon = $query->get( $query_var );
	if ( ! empty( $cedaro_coupon ) ) {
		// unset coupon var from $wp_query
		$query->set( $query_var, null );
		global $wp;
		// unset ref var from $wp
		unset( $wp->query_vars[ $query_var ] );
		// if in home (because $wp->query_vars is empty) and 'show_on_front' is page
		if ( empty( $wp->query_vars ) && get_option( 'show_on_front' ) === 'page' ) {
			// reset and re-parse query vars
			$wp->query_vars['page_id'] = get_option( 'page_on_front' );
			$query->parse_query( $wp->query_vars );
		}
	}
}

add_action( 'template_redirect', 'cedaro_woocommerce_coupon_links', 9 );
add_action( 'pre_get_posts', 'cedaro_unset_query_arg' );
add_action( 'woocommerce_add_to_cart', 'cedaro_woocommerce_coupon_links' );
add_action( 'woocommerce_coupon_options', 'cedaro_show_coupon_url', 20 );
add_action( 'admin_enqueue_scripts', 'cedaro_enqueue_coupon_url_styles' );
add_action( 'init', 'cedaro_add_coupon_rewrite' );
