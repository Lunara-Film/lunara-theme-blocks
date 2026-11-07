<?php
/**
 * Theme setup, enqueue, and utility helpers.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Drop malformed srcset candidates injected by CDN/image optimizers.
 */
if ( ! function_exists( 'lunara_sanitize_srcset_value' ) ) {
    function lunara_sanitize_srcset_value( $srcset ) {
        $srcset = is_string( $srcset ) ? trim( html_entity_decode( $srcset, ENT_QUOTES, 'UTF-8' ) ) : '';
        if ( '' === $srcset ) {
            return '';
        }

        $candidates = preg_split( '/,\s*(?=(?:https?:)?\/\/|\/)/', $srcset );
        if ( ! is_array( $candidates ) || empty( $candidates ) ) {
            $candidates = array( $srcset );
        }

        $valid = array();
        foreach ( $candidates as $candidate ) {
            $candidate = trim( (string) $candidate );
            if ( '' === $candidate ) {
                continue;
            }

            $decoded_candidate = html_entity_decode( $candidate, ENT_QUOTES, 'UTF-8' );
            if ( preg_match( '/(?:[?&;](?:resize|fit)=0(?:%2c|,)nan)/i', $decoded_candidate ) || preg_match( '/(?:[?&;](?:w|h)=0(?:&|$))/i', $decoded_candidate ) ) {
                continue;
            }

            if ( preg_match( '/\s+\d+w$/', $candidate ) || preg_match( '/\s+\d+(?:\.\d+)?x$/', $candidate ) ) {
                $valid[] = $candidate;
            }
        }

        return implode( ', ', $valid );
    }
}

/**
 * Enqueue theme styles.
 */
function lunara_enqueue_styles() {
    // Base reset (replaces Blocksy parent base styles).
    $base_css = lunara_resolve_theme_asset( 'assets/css/lunara-base.css' );
    if ( ! empty( $base_css['uri'] ) ) {
        wp_enqueue_style(
            'lunara-base',
            $base_css['uri'],
            array(),
            lunara_theme_asset_version( $base_css['path'] )
        );
    }

    wp_enqueue_style(
        'lunara-style',
        get_stylesheet_uri(),
        array( 'lunara-base' ),
        filemtime( get_stylesheet_directory() . '/style.css' )
    );

    if ( defined( 'STACKABLE_VERSION' ) || defined( 'STACKABLE_FILE' ) ) {
        $stackable_css = lunara_resolve_theme_asset( 'assets/css/lunara-stackable.css' );

        if ( ! empty( $stackable_css['uri'] ) ) {
            wp_enqueue_style(
                'lunara-stackable',
                $stackable_css['uri'],
                array( 'lunara-style' ),
                lunara_theme_asset_version( $stackable_css['path'] )
            );
        }
    }

    // The Oscars portal's route stylesheet is assets/css/lunara-oscars-portal.css
    // (inc/frontend.php, wp_enqueue_scripts 111). assets/css/oscars.css remains
    // shipped for the plugin-owned ledger routes, which enqueue it themselves.
}
add_action( 'wp_enqueue_scripts', 'lunara_enqueue_styles' );

/** Whether the public request can omit rules anchored only to the portal body. */
function lunara_shell_uses_non_portal_variant() {
    // Match the body-class owner. Unknown routes/previews keep the complete shell.
    if ( ! function_exists( 'lunara_is_oscars_portal_route' ) || is_admin() || is_feed() || is_preview() || is_customize_preview() || lunara_is_oscars_portal_route() || ! empty( $GLOBALS['lunara_site_studio_preview_context'] ) ) {
        return false;
    }

    foreach ( array_keys( $_GET ) as $key ) {
        if ( is_string( $key ) && ( 'lunara_site_studio_instance' === $key || ( 0 === strpos( $key, 'lunara_' ) && false !== strpos( $key, 'preview' ) ) ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Load the public shell repair layer after route-specific styles.
 *
 * This stylesheet used to live inline in header.php. Keeping its enqueue
 * position preserves the existing cascade and lets browsers reuse the file.
 */
function lunara_enqueue_shell_styles() {
    $shell_css = lunara_resolve_theme_asset( 'assets/css/lunara-shell.css' );

    if ( lunara_shell_uses_non_portal_variant() ) {
        // Built from the canonical shell without moving any retained rules.
        $non_portal_css = lunara_resolve_theme_asset( 'assets/css/lunara-shell-non-portal.css' );
        if ( ! empty( $non_portal_css['uri'] ) ) {
            $shell_css = $non_portal_css;
        }
    }

    if ( empty( $shell_css['uri'] ) ) {
        return;
    }

    wp_enqueue_style(
        'lunara-shell',
        $shell_css['uri'],
        array( 'lunara-style' ),
        lunara_theme_asset_version( $shell_css['path'] )
    );
}
add_action( 'wp_enqueue_scripts', 'lunara_enqueue_shell_styles', 100 );

/**
 * Print a cacheable stylesheet at the same late cascade point previously used
 * by its inline PHP emitter.
 *
 * @param string $handle        WordPress style handle.
 * @param string $relative_path Theme-relative asset path.
 */
function lunara_print_cacheable_stylesheet( $handle, $relative_path ) {
    $asset = lunara_resolve_theme_asset( $relative_path );

    if ( empty( $asset['uri'] ) ) {
        return;
    }

    wp_enqueue_style(
        $handle,
        $asset['uri'],
        array(),
        lunara_theme_asset_version( $asset['path'] )
    );
    wp_print_styles( $handle );
}

/**
 * Shared route guardrails, formerly emitted as four large inline blocks.
 */
function lunara_print_public_guardrail_styles() {
    if ( is_admin() || is_feed() ) {
        return;
    }

    lunara_print_cacheable_stylesheet( 'lunara-public-guardrails', 'assets/css/lunara-public-guardrails.css' );
}
add_action( 'wp_head', 'lunara_print_public_guardrail_styles', 1005 );

/**
 * Homepage module rules that do not contain request-specific values.
 */
function lunara_print_home_module_styles() {
    if ( ! is_front_page() ) {
        return;
    }

    lunara_print_cacheable_stylesheet( 'lunara-home-modules', 'assets/css/lunara-home-modules.css' );
}
add_action( 'wp_head', 'lunara_print_home_module_styles', 44 );

/**
 * Preserve the original final-word cascade position for Oscars safeguards.
 */
function lunara_print_late_oscars_guardrail_styles() {
    if ( is_admin() || is_feed() ) {
        return;
    }

    $aat_entity          = get_query_var( 'aat_entity' );
    $aat_entity_id       = get_query_var( 'aat_entity_id' );
    $aat_hub             = get_query_var( 'aat_hub' );
    $is_aat_entity_route = ! empty( $aat_entity ) && ! empty( $aat_entity_id );
    $is_aat_hub_route    = ! empty( $aat_hub );

    if ( ! $is_aat_entity_route && ! $is_aat_hub_route ) {
        return;
    }

    lunara_print_cacheable_stylesheet( 'lunara-oscars-late-guardrails', 'assets/css/lunara-oscars-late-guardrails.css' );
}
add_action( 'wp_footer', 'lunara_print_late_oscars_guardrail_styles', 999 );

/**
 * Theme setup
 */
function lunara_theme_setup() {
    add_theme_support( 'post-thumbnails' );
    add_image_size( 'lunara-editorial-card', 1500, 2000, true );
    add_image_size( 'lunara-poster-library', 2000, 3000, true );
    add_image_size( 'lunara-hero-spotlight', 1920, 1080, true );
    add_image_size( 'lunara-review-card', 750, 1000, true );
    add_image_size( 'lunara-review-card-retina', 1500, 2000, true );
    add_image_size( 'lunara-review-feature', 1500, 2000, true );
    add_image_size( 'lunara-review-hero', 1920, 1080, true );
    add_theme_support( 'title-tag' );
    add_theme_support( 'custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ) );

    add_theme_support( 'html5', array(
        'comment-list',
        'comment-form',
        'search-form',
        'gallery',
        'caption',
        'script',
        'style',
    ) );

    // Register navigation menu
    register_nav_menus( array(
        'primary'          => __( 'Primary Menu', 'lunara-film' ),
        'footer'           => __( 'Footer Menu', 'lunara-film' ),
        'footer-editorial' => __( 'Footer Editorial', 'lunara-film' ),
        'footer-oscars'    => __( 'Footer Oscars', 'lunara-film' ),
        'footer-utility'   => __( 'Footer Utility', 'lunara-film' ),
    ) );
}
add_action( 'after_setup_theme', 'lunara_theme_setup' );

/**
 * Keep the public front door from shipping an empty browser title when the
 * static homepage/page title is intentionally quiet.
 *
 * @param array $parts WordPress document title parts.
 * @return array
 */
function lunara_filter_front_page_document_title_parts( $parts ) {
    if ( ! is_front_page() ) {
        return $parts;
    }

    $site_name = trim( (string) get_bloginfo( 'name' ) );
    if ( '' === $site_name ) {
        $site_name = __( 'Lunara Film', 'lunara-film' );
    }

    $parts['title'] = $site_name;

    return $parts;
}
add_filter( 'document_title_parts', 'lunara_filter_front_page_document_title_parts', 20 );

/**
 * Keep the header logo visible without competing with Home's true LCP image.
 */
function lunara_custom_logo_fetch_priority() {
    return is_front_page() ? 'auto' : 'high';
}

/**
 * Ensure the custom logo always ships with meaningful accessibility text.
 */
function lunara_filter_custom_logo_image_attributes( $attr, $custom_logo_id, $blog_id ) {
    unset( $custom_logo_id, $blog_id );

    $alt = isset( $attr['alt'] ) ? trim( (string) $attr['alt'] ) : '';
    $classes = isset( $attr['class'] ) ? (string) $attr['class'] : '';

    if ( '' === $alt ) {
        $site_name   = trim( (string) get_bloginfo( 'name' ) );
        $attr['alt'] = sprintf( __( '%s logo', 'lunara-film' ), '' !== $site_name ? $site_name : 'Lunara Film' );
    }

    $attr['width']    = 300;
    $attr['height']   = 79;
    $attr['sizes']    = '(max-width: 689px) 260px, 300px';
    $attr['decoding'] = 'async';

    if ( false !== strpos( $classes, 'default-logo' ) ) {
        $attr['loading']       = 'eager';
        $attr['fetchpriority'] = lunara_custom_logo_fetch_priority();
    } elseif ( false !== strpos( $classes, 'dark-mode-logo' ) ) {
        $attr['loading'] = 'lazy';
        unset( $attr['fetchpriority'] );
    }

    return $attr;
}
add_filter( 'get_custom_logo_image_attributes', 'lunara_filter_custom_logo_image_attributes', 10, 3 );

function lunara_resize_wpcom_image_url( $url, $width, $height ) {
    $url    = html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' );
    $width  = max( 1, absint( $width ) );
    $height = max( 1, absint( $height ) );

    if ( '' === trim( $url ) || 0 === $width || 0 === $height ) {
        return '';
    }

    $url = remove_query_arg( array( 'fit', 'resize', 'w', 'h', '_jb' ), $url );
    $url = add_query_arg(
        array(
            'resize'  => $width . ',' . $height,
            'quality' => '86',
            'ssl'     => '1',
        ),
        $url
    );

    return esc_url( str_replace( ',', '%2C', $url ) );
}

function lunara_normalize_custom_logo_markup( $html ) {
    if ( ! is_string( $html ) || '' === $html ) {
        return $html;
    }

    if ( false === stripos( $html, 'default-logo' ) && false === stripos( $html, 'dark-mode-logo' ) && false === stripos( $html, 'lunara-footer-logo' ) ) {
        return $html;
    }

    if ( ! preg_match( '/\ssrc=("|\')([^"\']+)\1/i', $html, $src_match ) ) {
        return $html;
    }

    $logo_300 = lunara_resize_wpcom_image_url( $src_match[2], 300, 79 );
    $logo_600 = lunara_resize_wpcom_image_url( $src_match[2], 600, 158 );

    if ( '' === $logo_300 ) {
        return $html;
    }

    $html = str_replace( $src_match[0], ' src="' . esc_url( $logo_300 ) . '"', $html );

    if ( '' !== $logo_600 ) {
        $srcset = esc_attr( $logo_300 . ' 300w, ' . $logo_600 . ' 600w' );
        if ( preg_match( '/\ssrcset=("|\')[^"\']+\1/i', $html, $srcset_match ) ) {
            $html = str_replace( $srcset_match[0], ' srcset="' . $srcset . '"', $html );
        } else {
            $html = preg_replace( '/<img\b/i', '<img srcset="' . $srcset . '"', $html, 1 );
        }
    }

    if ( preg_match( '/\ssizes=("|\')[^"\']+\1/i', $html, $sizes_match ) ) {
        $html = str_replace( $sizes_match[0], ' sizes="(max-width: 689px) 260px, 300px"', $html );
    }

    if ( preg_match( '/\salt=("|\')\s*(?:logo)?\s*\1/i', $html, $alt_match ) ) {
        $html = str_replace( $alt_match[0], ' alt="Lunara Film logo"', $html );
    }

    $html = preg_replace( '/\sdata-(?!(?:no-lazy|skip-lazy)\b)[a-z0-9_-]+=("|\')[^"\']*\1/i', '', $html );

    return $html;
}
add_filter( 'get_custom_logo', 'lunara_normalize_custom_logo_markup', PHP_INT_MAX );

/**
 * Keep above-the-fold images out of JS lazy placeholders.
 */
function lunara_is_priority_image_attr( $attr ) {
    if ( ! is_array( $attr ) ) {
        return false;
    }

    $loading       = isset( $attr['loading'] ) ? strtolower( (string) $attr['loading'] ) : '';
    $fetchpriority = isset( $attr['fetchpriority'] ) ? strtolower( (string) $attr['fetchpriority'] ) : '';

    return 'eager' === $loading || 'high' === $fetchpriority;
}

function lunara_protect_priority_image_attributes( $attr ) {
    if ( is_admin() ) {
        return $attr;
    }

    $class_string = isset( $attr['class'] ) ? (string) $attr['class'] : '';

    if ( false !== strpos( $class_string, 'default-logo' ) || false !== strpos( $class_string, 'dark-mode-logo' ) || false !== strpos( $class_string, 'lunara-footer-logo' ) ) {
        $attr['width']    = 300;
        $attr['height']   = 79;
        $attr['sizes']    = '(max-width: 689px) 260px, 300px';
        $attr['decoding'] = 'async';

        if ( empty( $attr['alt'] ) ) {
            $site_name   = trim( (string) get_bloginfo( 'name' ) );
            $attr['alt'] = sprintf( __( '%s logo', 'lunara-film' ), '' !== $site_name ? $site_name : 'Lunara Film' );
        }

        if ( false !== strpos( $class_string, 'default-logo' ) ) {
            $attr['loading']       = 'eager';
            $attr['fetchpriority'] = lunara_custom_logo_fetch_priority();
        } else {
            $attr['loading'] = 'lazy';
            unset( $attr['fetchpriority'] );
        }
    }

    if ( ! lunara_is_priority_image_attr( $attr ) ) {
        return $attr;
    }

    $classes = isset( $attr['class'] ) ? preg_split( '/\s+/', (string) $attr['class'] ) : array();
    $classes = array_values( array_filter( array_map( 'sanitize_html_class', (array) $classes ) ) );
    $classes = array_diff( $classes, array( 'lazyload' ) );
    $classes = array_unique( array_merge( $classes, array( 'skip-lazy', 'no-lazy' ) ) );

    $attr['class']          = trim( implode( ' ', $classes ) );
    $attr['data-no-lazy']   = '1';
    $attr['data-skip-lazy'] = '1';
    $attr['decoding']       = 'async';

    return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'lunara_protect_priority_image_attributes', PHP_INT_MAX );

function lunara_restore_priority_image_markup( $html ) {
    if ( is_admin() || ! is_string( $html ) || '' === $html ) {
        return $html;
    }

    $is_logo_markup = false !== stripos( $html, 'default-logo' ) || false !== stripos( $html, 'dark-mode-logo' ) || false !== stripos( $html, 'lunara-footer-logo' );

    if ( false === stripos( $html, 'fetchpriority="high"' ) && false === stripos( $html, "fetchpriority='high'" ) && false === stripos( $html, 'loading="eager"' ) && false === stripos( $html, "loading='eager'" ) ) {
        return $is_logo_markup ? lunara_normalize_custom_logo_markup( $html ) : $html;
    }

    if ( preg_match( '/\sdata-(?:lazy-)?src=("|\')([^"\']+)\1/i', $html, $src_match ) ) {
        $src = esc_url( html_entity_decode( (string) $src_match[2], ENT_QUOTES, 'UTF-8' ) );
        if ( '' !== $src ) {
            $html = preg_replace( '/\ssrc=("|\')data:image\/gif[^"\']*\1/i', ' src="' . $src . '"', $html, 1 );
        }
    }

    if ( false === stripos( $html, ' srcset=' ) && preg_match( '/\sdata-(?:lazy-)?srcset=("|\')([^"\']+)\1/i', $html, $srcset_match ) ) {
        $srcset = lunara_sanitize_srcset_value( (string) $srcset_match[2] );
        if ( '' !== $srcset ) {
            $html = preg_replace( '/<img\b/i', '<img srcset="' . esc_attr( $srcset ) . '"', $html, 1 );
        }
    }

    if ( preg_match( '/\sclass=("|\')([^"\']*)\1/i', $html, $class_match ) ) {
        $classes = preg_split( '/\s+/', (string) $class_match[2] );
        $classes = array_values( array_filter( array_map( 'sanitize_html_class', (array) $classes ) ) );
        $classes = array_diff( $classes, array( 'lazyload' ) );
        $classes = array_unique( array_merge( $classes, array( 'skip-lazy', 'no-lazy' ) ) );
        $html    = str_replace( $class_match[0], ' class="' . esc_attr( trim( implode( ' ', $classes ) ) ) . '"', $html );
    }

    if ( false === stripos( $html, 'data-no-lazy=' ) ) {
        $html = preg_replace( '/<img\b/i', '<img data-no-lazy="1" data-skip-lazy="1"', $html, 1 );
    }

    $html = lunara_normalize_custom_logo_markup( $html );

    return $html;
}
add_filter( 'wp_get_attachment_image', 'lunara_restore_priority_image_markup', PHP_INT_MAX );
add_filter( 'post_thumbnail_html', 'lunara_restore_priority_image_markup', PHP_INT_MAX );

if ( ! function_exists( 'lunara_resize_tmdb_image_url' ) ) {
    function lunara_resize_tmdb_image_url( $url, $size = 'w780' ) {
        $url  = trim( (string) $url );
        $size = preg_match( '/^w\d+$/', (string) $size ) ? (string) $size : 'w780';

        if ( '' === $url ) {
            return '';
        }

        return preg_replace( '#https://image\.tmdb\.org/t/p/(?:w\d+|original)(?=/)#i', 'https://image.tmdb.org/t/p/' . $size, $url );
    }
}

if ( ! function_exists( 'lunara_tmdb_image_srcset' ) ) {
    /**
     * Responsive candidates for a TMDB image, or '' for any other URL.
     *
     * TMDB serves fixed widths: w780 exists for posters and backdrops alike,
     * w1280 only for backdrops. The original stays available as the largest
     * candidate, declared at a conservative width, so high-density screens
     * keep full sharpness while phones and 1x screens stop downloading it.
     *
     * @param string $url      TMDB image URL at any size.
     * @param bool   $backdrop True for landscape stills, false for posters.
     * @return string srcset value, or '' when the URL is not a TMDB image.
     */
    function lunara_tmdb_image_srcset( $url, $backdrop = true ) {
        if ( ! preg_match( '#^(https://image\.tmdb\.org/t/p/)(?:w\d+|original)(/[^\s?\#"\'<>]+)$#i', trim( (string) $url ), $parts ) ) {
            return '';
        }

        $candidates = array( $parts[1] . 'w780' . $parts[2] . ' 780w' );
        if ( $backdrop ) {
            $candidates[] = $parts[1] . 'w1280' . $parts[2] . ' 1280w';
        }
        $candidates[] = $parts[1] . 'original' . $parts[2] . ( $backdrop ? ' 1920w' : ' 2000w' );

        return implode( ', ', $candidates );
    }
}

if ( ! function_exists( 'lunara_image_url_width_srcset' ) ) {
    /**
     * Width candidates for an image known only by URL, or '' when the host
     * cannot resize it.
     *
     * TMDB images use TMDB's fixed widths. WordPress.com uploads (on this site
     * or through the i0.wp.com CDN) resize from a `w` query argument, which
     * keeps the aspect ratio and never upscales.
     *
     * @param string         $url      Image URL.
     * @param bool           $backdrop For TMDB: landscape still rather than poster.
     * @param array<int,int> $widths   Candidate widths for WordPress.com images.
     * @return string
     */
    function lunara_image_url_width_srcset( $url, $backdrop = true, $widths = array( 480, 768, 1200 ) ) {
        $url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
        if ( '' === $url ) {
            return '';
        }

        $tmdb = lunara_tmdb_image_srcset( $url, $backdrop );
        if ( '' !== $tmdb ) {
            return $tmdb;
        }

        $host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        $wpcom     = '' !== $host && ( $host === $home_host || 1 === preg_match( '/(^|\.)(wp|wordpress)\.com$/', $host ) );
        if ( ! $wpcom || false === strpos( $url, '/wp-content/uploads/' ) ) {
            return '';
        }

        $base       = remove_query_arg( array( 'w', 'h', 'fit', 'resize', 'crop', 'zoom' ), $url );
        $candidates = array();
        foreach ( $widths as $width ) {
            $width = absint( $width );
            if ( $width > 0 ) {
                $candidates[] = add_query_arg( 'w', $width, $base ) . ' ' . $width . 'w';
            }
        }

        return implode( ', ', $candidates );
    }
}

if ( ! function_exists( 'lunara_image_cdn_source' ) ) {
    /**
     * The URL the WordPress.com image CDN can resize, with no size arguments,
     * or '' when the host cannot resize (3.2.152).
     *
     * A site upload keeps its host (the CDN already fronts it, and the i0.wp.com
     * form is kept as it is). A TMDB image is proxied from its original through
     * i0.wp.com, so every candidate is one CDN resize of one source.
     *
     * @param string $url Image URL.
     * @return string
     */
    function lunara_image_cdn_source( $url ) {
        $url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
        if ( '' === $url ) {
            return '';
        }

        if ( preg_match( '#^https?://image\.tmdb\.org/t/p/(?:w\d+|original)/([^\s?\#"\'<>]+)#i', $url, $tmdb ) ) {
            return 'https://i0.wp.com/image.tmdb.org/t/p/original/' . $tmdb[1];
        }

        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $host ) {
            return '';
        }

        $base = remove_query_arg( array( 'w', 'h', 'fit', 'resize', 'crop', 'zoom', '_jb' ), $url );
        if ( 1 === preg_match( '/^i[0-3]\.wp\.com$/', $host ) ) {
            return $base;
        }

        $home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
        $is_site   = '' !== $home_host && ( $host === $home_host || 'www.' . $host === $home_host || $host === 'www.' . $home_host );
        $is_wpcom  = 1 === preg_match( '/(^|\.)(wp|wordpress)\.com$/', $host );
        if ( ( ! $is_site && ! $is_wpcom ) || false === strpos( $base, '/wp-content/uploads/' ) ) {
            return '';
        }

        return $base;
    }
}

if ( ! function_exists( 'lunara_image_cdn_crop_url' ) ) {
    /**
     * One CDN crop of an image to an exact frame, or '' when it cannot resize.
     *
     * @param string $url    Image URL.
     * @param int    $width  Frame width in pixels.
     * @param int    $height Frame height in pixels.
     * @return string
     */
    function lunara_image_cdn_crop_url( $url, $width, $height ) {
        $source = lunara_image_cdn_source( $url );
        $width  = absint( $width );
        $height = absint( $height );
        if ( '' === $source || $width < 1 || $height < 1 || ! function_exists( 'lunara_resize_wpcom_image_url' ) ) {
            return '';
        }

        return (string) lunara_resize_wpcom_image_url( $source, $width, $height );
    }
}

if ( ! function_exists( 'lunara_image_cdn_width_url' ) ) {
    /**
     * One CDN downscale of an image to a width, aspect ratio kept, or ''.
     *
     * @param string $url   Image URL.
     * @param int    $width Width in pixels.
     * @return string
     */
    function lunara_image_cdn_width_url( $url, $width ) {
        $source = lunara_image_cdn_source( $url );
        $width  = absint( $width );
        if ( '' === $source || $width < 1 ) {
            return '';
        }

        return esc_url( add_query_arg( array( 'w' => $width, 'quality' => '86', 'ssl' => '1' ), $source ) );
    }
}

if ( ! function_exists( 'lunara_image_source_size_from_url' ) ) {
    /**
     * What a URL says about its source's size (3.2.154): a `fit=W,H` or
     * `w=W` the CDN already answered, which the CDN never scales up, so it
     * is a true lower bound of the source. A `resize=` is a request the
     * theme itself wrote and may already be an upscale; a TMDB rendition
     * width says nothing about the original the CDN crops (3.2.155): both
     * give no size.
     *
     * @param string $url Image URL.
     * @return array{width:int,height:int} Zero where unknown.
     */
    function lunara_image_source_size_from_url( $url ) {
        $url  = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
        $size = array( 'width' => 0, 'height' => 0 );
        if ( preg_match( '/[?&]fit=(\d+)(?:,|%2C)(\d+)/i', $url, $m ) ) {
            $size['width']  = (int) $m[1];
            $size['height'] = (int) $m[2];
        } elseif ( preg_match( '/[?&]w=(\d+)/i', $url, $m ) ) {
            $size['width'] = (int) $m[1];
        }

        return $size;
    }
}

if ( ! function_exists( 'lunara_image_upload_size' ) ) {
    /**
     * The full size of the site upload behind a URL (3.2.155), through the
     * attachment lookup that remembers its answers, or an empty array when
     * the URL is not one of this site's uploads.
     *
     * @param string $url Image URL, site-hosted or through the CDN proxy.
     * @return array{width:int,height:int}|array{}
     */
    function lunara_image_upload_size( $url ) {
        if ( ! function_exists( 'lunara_attachment_id_for_url' ) || ! function_exists( 'wp_get_attachment_metadata' ) ) {
            return array();
        }
        $url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
        $url = (string) preg_replace( '#^https?://i[0-3]\.wp\.com/#i', 'https://', $url );
        $url = (string) preg_replace( '/\?.*$/', '', $url );
        if ( '' === $url || false === strpos( $url, '/wp-content/uploads/' ) ) {
            return array();
        }
        $attachment_id = (int) lunara_attachment_id_for_url( $url );
        if ( $attachment_id < 1 ) {
            return array();
        }
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! is_array( $meta ) || absint( $meta['width'] ?? 0 ) < 1 || absint( $meta['height'] ?? 0 ) < 1 ) {
            return array();
        }

        return array( 'width' => absint( $meta['width'] ), 'height' => absint( $meta['height'] ) );
    }
}

if ( ! function_exists( 'lunara_image_cdn_crop_limit' ) ) {
    /**
     * The widest crop of the frame's ratio the source can give without being
     * scaled up (3.2.154), or 0 when nothing is known about the source.
     *
     * @param string               $url          Image URL.
     * @param int                  $frame_width  Frame ratio width.
     * @param int                  $frame_height Frame ratio height.
     * @param array<string,int>    $source       Known source width and height, when the caller has them.
     * @return int
     */
    function lunara_image_cdn_crop_limit( $url, $frame_width, $frame_height, $source = array() ) {
        $source = is_array( $source ) ? $source : array();
        $width  = absint( $source['width'] ?? 0 );
        $height = absint( $source['height'] ?? 0 );
        if ( $width < 1 && $height < 1 ) {
            $known  = lunara_image_source_size_from_url( $url );
            $width  = $known['width'];
            $height = $known['height'];
        }
        if ( $width < 1 && $height < 1 ) {
            // 3.2.155: a site upload the URL does not size (a `resize=` the
            // theme wrote) is measured through its attachment.
            $known  = lunara_image_upload_size( $url );
            $width  = absint( $known['width'] ?? 0 );
            $height = absint( $known['height'] ?? 0 );
        }

        $limit        = $width;
        $frame_width  = absint( $frame_width );
        $frame_height = absint( $frame_height );
        if ( $height > 0 && $frame_width > 0 && $frame_height > 0 ) {
            $by_height = (int) floor( $height * $frame_width / $frame_height );
            $limit     = $limit > 0 ? min( $limit, $by_height ) : $by_height;
        }

        return max( 0, (int) $limit );
    }
}

if ( ! function_exists( 'lunara_image_cdn_crop_srcset' ) ) {
    /**
     * Width candidates for an image in a fixed-ratio frame.
     *
     * Every candidate is cropped to the frame's ratio, so the candidate's
     * width is the width it renders at and the browser chooses by width
     * alone. The CDN scales a `resize` crop UP when asked for more than the
     * source holds (3.2.154), so no candidate is wider than the widest crop
     * the source can give: $source when the caller knows it, else what the
     * URL says (lunara_image_source_size_from_url()); the native crop joins
     * the list when it is wider than the last kept width. Returns empty
     * strings when the host cannot resize, or fewer than two candidates
     * remain, so the caller prints the image as it was given.
     *
     * @param string            $url            Image URL.
     * @param int               $frame_width    Frame ratio width (for example 2).
     * @param int               $frame_height   Frame ratio height (for example 3).
     * @param array<int,int>    $widths         Candidate widths in pixels.
     * @param int               $fallback_width The candidate `src` should be nearest to.
     * @param array<string,int> $source         Known source width and height, when the caller has them.
     * @return array{src:string,srcset:string}
     */
    function lunara_image_cdn_crop_srcset( $url, $frame_width, $frame_height, $widths, $fallback_width = 0, $source = array() ) {
        $empty        = array( 'src' => '', 'srcset' => '' );
        $frame_width  = absint( $frame_width );
        $frame_height = absint( $frame_height );
        if ( $frame_width < 1 || $frame_height < 1 ) {
            return $empty;
        }

        $widths = array_values( array_unique( array_filter( array_map( 'absint', (array) $widths ) ) ) );
        sort( $widths, SORT_NUMERIC );

        $limit = lunara_image_cdn_crop_limit( $url, $frame_width, $frame_height, $source );
        if ( $limit > 0 ) {
            $requested = empty( $widths ) ? 0 : max( $widths );
            $widths    = array_values( array_filter( $widths, static function ( $width ) use ( $limit ) { return $width <= $limit; } ) );
            // The native crop joins only in place of widths the cap removed
            // (3.2.155: never above the widest width asked for), and only when
            // it is at least a tenth wider than the last kept width.
            if ( $limit < $requested && ( empty( $widths ) || $limit >= max( $widths ) * 1.1 ) ) {
                $widths[] = $limit;
            }
        }
        if ( count( $widths ) < 2 ) {
            return $empty;
        }

        $candidates     = array();
        $src            = '';
        $nearest        = PHP_INT_MAX;
        $fallback_width = absint( $fallback_width );
        foreach ( $widths as $width ) {
            $height    = (int) round( $width * $frame_height / $frame_width );
            $candidate = lunara_image_cdn_crop_url( $url, $width, $height );
            if ( '' === $candidate ) {
                return $empty;
            }
            $candidates[] = $candidate . ' ' . $width . 'w';
            $distance     = abs( $width - $fallback_width );
            if ( '' === $src || $distance < $nearest ) {
                $src     = $candidate;
                $nearest = $distance;
            }
        }

        return array(
            'src'    => $src,
            'srcset' => implode( ', ', $candidates ),
        );
    }
}

if ( ! function_exists( 'lunara_image_cdn_width_srcset' ) ) {
    /**
     * Width candidates for an image shown whole (object-fit: contain): plain
     * downscales, aspect ratio kept, none wider than the source (3.2.154).
     * Empty strings when the host cannot resize.
     *
     * @param string            $url            Image URL.
     * @param array<int,int>    $widths         Candidate widths in pixels.
     * @param int               $fallback_width The candidate `src` should be nearest to.
     * @param array<string,int> $source         Known source width and height, when the caller has them.
     * @return array{src:string,srcset:string}
     */
    function lunara_image_cdn_width_srcset( $url, $widths, $fallback_width = 0, $source = array() ) {
        $empty  = array( 'src' => '', 'srcset' => '' );
        $widths = array_values( array_unique( array_filter( array_map( 'absint', (array) $widths ) ) ) );
        sort( $widths, SORT_NUMERIC );

        // 3.2.154: a `w=` downscale never grows past the source, but its
        // descriptor would; keep candidates within the source's width.
        $source = is_array( $source ) ? $source : array();
        $limit  = absint( $source['width'] ?? 0 );
        if ( $limit < 1 ) {
            $known = lunara_image_source_size_from_url( $url );
            $limit = $known['width'];
        }
        if ( $limit < 1 ) {
            $known = lunara_image_upload_size( $url );
            $limit = absint( $known['width'] ?? 0 );
        }
        if ( $limit > 0 ) {
            $requested = empty( $widths ) ? 0 : max( $widths );
            $widths    = array_values( array_filter( $widths, static function ( $width ) use ( $limit ) { return $width <= $limit; } ) );
            if ( $limit < $requested && ( empty( $widths ) || $limit >= max( $widths ) * 1.1 ) ) {
                $widths[] = $limit;
            }
        }
        if ( count( $widths ) < 2 ) {
            return $empty;
        }

        $candidates     = array();
        $src            = '';
        $nearest        = PHP_INT_MAX;
        $fallback_width = absint( $fallback_width );
        foreach ( $widths as $width ) {
            $candidate = lunara_image_cdn_width_url( $url, $width );
            if ( '' === $candidate ) {
                return $empty;
            }
            $candidates[] = $candidate . ' ' . $width . 'w';
            $distance     = abs( $width - $fallback_width );
            if ( '' === $src || $distance < $nearest ) {
                $src     = $candidate;
                $nearest = $distance;
            }
        }

        return array(
            'src'    => $src,
            'srcset' => implode( ', ', $candidates ),
        );
    }
}

if ( ! function_exists( 'lunara_image_sizes_for' ) ) {
    /**
     * The width a slot renders its image at, as a `sizes` value (3.2.152).
     *
     * Measured on the live site at 23 widths from 320 to 1920px. Pixel values
     * for a slot that is a column of a grid; a viewport share only where the
     * frame really is one (a phone's full-width still).
     *
     * @param string $slot Slot name.
     * @return string '' for an unknown slot.
     */
    function lunara_image_sizes_for( $slot ) {
        // 3.2.154: re-measured at every breakpoint (320-1920px, 23 widths).
        // The Oscars grids are one 96px column to 600px, then a fluid two-up
        // (256-339px), three-up from 821px (234-325px) and a 253-257px four-up
        // from 1201px; the hero poster paints 120px wide at every width (a
        // 2:3 image covering a 96x180 box on phones); a Home companion still
        // is full width to 820px, 328-440px in the two-up and 300-417px
        // beside the lead from 1101px.
        $sizes = array(
            'oscars-grid'          => '(max-width: 600px) 96px, (max-width: 640px) 275px, (max-width: 820px) 340px, (max-width: 1200px) 320px, 256px',
            'oscars-board'         => '(max-width: 600px) 96px, (max-width: 640px) 275px, (max-width: 820px) 340px, (max-width: 1200px) 320px, 256px',
            'oscars-hero'          => '120px',
            'debrief-poster'       => '(max-width: 420px) 88px, (max-width: 640px) 104px, (max-width: 900px) 128px, 220px',
            'pair-card'            => '(max-width: 640px) 96px, 128px',
            'related-grid'         => '(max-width: 640px) 96px, 112px',
            'front-desk-companion' => '(max-width: 820px) calc(100vw - 64px), (max-width: 1100px) 440px, (max-width: 1280px) 340px, 420px',
            // 3.2.161: a door is landscape on a phone and portrait on desktop,
            // and its picture covers it, so the candidate a door needs is the
            // wider of its frame and its height at 16:9. Measured at
            // 320-1920px: full width less 34px sides to 820px, 250px tall;
            // a third of the panel above, 39vw tall to 560px.
            'front-door'           => '(max-width: 820px) max(calc(100vw - 68px), 444px), (max-width: 1300px) 69.4vw, min(69.4vw, 996px)',
            // 3.2.156: the Film Index and the Talent Index cards (one column
            // to 389px, then a fluid auto-fill grid of 158px minimums: 172-241px
            // two-up, 175-187px three-up, 163-194px four-up, 166-172px from
            // 1024px) and the dossier hero poster (min(58vw, 220px) to 760px,
            // then clamp(160px, 18vw, 250px)), measured at 15 widths on live.
            'entity-card'          => '(max-width: 389px) calc(100vw - 32px), (max-width: 599px) 50vw, (max-width: 759px) 34vw, (max-width: 1023px) 25vw, 170px',
            'entity-hero'          => '(max-width: 760px) min(58vw, 220px), clamp(160px, 18vw, 250px)',
        );

        return isset( $sizes[ $slot ] ) ? $sizes[ $slot ] : '';
    }
}

if ( ! function_exists( 'lunara_image_blur_backdrop_url' ) ) {
    /**
     * The source for a backdrop the stylesheet blurs by 20px: 320px wide.
     *
     * The blur hides more detail than a 320px source lacks at any band width,
     * so the full still (often 2,000px and more) is never downloaded for it.
     * A host that cannot resize keeps its URL.
     *
     * @param string $url Image URL.
     * @return string
     */
    function lunara_image_blur_backdrop_url( $url ) {
        $small = lunara_image_cdn_width_url( $url, 320 );

        return '' !== $small ? $small : trim( (string) $url );
    }
}

if ( ! function_exists( 'lunara_resize_tmdb_image_markup' ) ) {
    function lunara_resize_tmdb_image_markup( $html, $size = 'w500' ) {
        if ( ! is_string( $html ) || '' === $html ) {
            return $html;
        }

        $size = preg_match( '/^w\d+$/', (string) $size ) ? (string) $size : 'w500';

        return preg_replace_callback(
            '#https://image\.tmdb\.org/t/p/(?:w\d+|original)(/[^\s"\'<)]+)#i',
            function ( $matches ) use ( $size ) {
                return 'https://image.tmdb.org/t/p/' . $size . $matches[1];
            },
            $html
        );
    }
}

if ( ! function_exists( 'lunara_normalize_visual_package_image_sizes' ) ) {
    function lunara_normalize_visual_package_image_sizes( $visual, $poster_size = 'w500', $backdrop_size = 'w780' ) {
        if ( ! is_array( $visual ) || empty( $visual ) ) {
            return array();
        }

        foreach ( array( 'poster_url' ) as $key ) {
            if ( ! empty( $visual[ $key ] ) ) {
                $visual[ $key ] = lunara_resize_tmdb_image_url( $visual[ $key ], $poster_size );
            }
        }

        foreach ( array( 'portrait_url' ) as $key ) {
            if ( ! empty( $visual[ $key ] ) ) {
                $visual[ $key ] = lunara_resize_tmdb_image_url( $visual[ $key ], 'w342' );
            }
        }

        if ( ! empty( $visual['backdrop_url'] ) ) {
            $visual['backdrop_url'] = lunara_resize_tmdb_image_url( $visual['backdrop_url'], $backdrop_size );
        }

        foreach ( array( 'poster_html', 'fallback_html', 'card_fallback_html' ) as $key ) {
            if ( ! empty( $visual[ $key ] ) ) {
                $visual[ $key ] = lunara_resize_tmdb_image_markup( $visual[ $key ], $poster_size );
            }
        }

        if ( ! empty( $visual['tmdb'] ) && is_array( $visual['tmdb'] ) ) {
            if ( ! empty( $visual['tmdb']['poster_full'] ) ) {
                $visual['tmdb']['poster_full'] = lunara_resize_tmdb_image_url( $visual['tmdb']['poster_full'], $poster_size );
            }
            if ( ! empty( $visual['tmdb']['profile_full'] ) ) {
                $visual['tmdb']['profile_full'] = lunara_resize_tmdb_image_url( $visual['tmdb']['profile_full'], 'w342' );
            }
            if ( ! empty( $visual['tmdb']['backdrop_full'] ) ) {
                $visual['tmdb']['backdrop_full'] = lunara_resize_tmdb_image_url( $visual['tmdb']['backdrop_full'], $backdrop_size );
            }
        }

        return $visual;
    }
}

/**
 * Optional escape hatch for forcing the legacy Lunara shell.
 *
 * By default we now allow Blocksy to render its native header builder again so
 * the parent theme regains control over the shell UI. A filter remains in place
 * in case we need to temporarily restore the old child-theme header path during
 * follow-up work.
 */
if ( apply_filters( 'lunara_disable_blocksy_header', false ) ) {
    add_filter( 'blocksy_has_header', '__return_false' );
}

/**
 * Resolve a child-theme asset across the canonical assets tree and older flat-file layouts.
 */
function lunara_resolve_theme_asset( $preferred, $fallbacks = array() ) {
    $candidates = array_merge( array( $preferred ), (array) $fallbacks );

    foreach ( $candidates as $relative ) {
        $relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
        if ( $relative === '' ) {
            continue;
        }

        $path = trailingslashit( get_stylesheet_directory() ) . $relative;
        if ( file_exists( $path ) ) {
            return array(
                'path' => $path,
                'uri'  => trailingslashit( get_stylesheet_directory_uri() ) . $relative,
            );
        }
    }

    return array(
        'path' => '',
        'uri'  => '',
    );
}

/**
 * Use file modification time when possible so asset changes bust cache automatically.
 */
function lunara_theme_asset_version( $path ) {
    if ( $path && file_exists( $path ) ) {
        return (string) filemtime( $path );
    }

    return wp_get_theme()->get( 'Version' );
}

/**
 * Return a non-empty theme mod string or a fallback.
 */
if ( ! function_exists( 'lunara_theme_mod_text' ) ) {
    function lunara_theme_mod_text( $setting, $default = '' ) {
        $value = trim( (string) get_theme_mod( $setting, '' ) );

        return $value !== '' ? $value : $default;
    }
}

/**
 * Return a non-empty theme mod URL or a fallback.
 */
if ( ! function_exists( 'lunara_theme_mod_url' ) ) {
    function lunara_theme_mod_url( $setting, $default = '' ) {
        $value = esc_url_raw( (string) get_theme_mod( $setting, '' ) );

        return $value !== '' ? $value : $default;
    }
}

/**
 * Keep a stable QR URL for festival outreach while allowing the destination to change.
 */
function lunara_handle_festival_qr_redirect() {
    $qr_key = isset( $_GET['lunara_qr'] ) ? sanitize_key( wp_unslash( $_GET['lunara_qr'] ) ) : '';

    if ( 'festival' !== $qr_key ) {
        return;
    }

    $target = lunara_theme_mod_url( 'lunara_festival_qr_target_url', home_url( '/' ) );
    if ( '' === $target ) {
        $target = home_url( '/' );
    }

    $target_host = wp_parse_url( $target, PHP_URL_HOST );
    $home_host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

    if ( $target_host && $home_host && strtolower( $target_host ) === strtolower( $home_host ) ) {
        $target = add_query_arg(
            array(
                'utm_source'   => 'festival_qr',
                'utm_medium'   => 'qr',
                'utm_campaign' => 'festival_outreach',
            ),
            $target
        );
    }

    wp_safe_redirect( $target, 302, 'Lunara Festival QR' );
    exit;
}
add_action( 'template_redirect', 'lunara_handle_festival_qr_redirect', 0 );

/**
 * Treat /news/ as a legacy doorway into the Journal surface.
 */
if ( ! function_exists( 'lunara_handle_legacy_news_redirect' ) ) {
    function lunara_handle_legacy_news_redirect() {
        if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
            return;
        }

        $request_path = wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
        $request_path = is_string( $request_path ) ? untrailingslashit( $request_path ) : '';

        if ( '/news' !== $request_path ) {
            return;
        }

        $target = function_exists( 'lunara_home_dispatch_archive_url' )
            ? lunara_home_dispatch_archive_url()
            : home_url( '/journal/' );

        if ( ! is_string( $target ) || '' === $target ) {
            $target = home_url( '/journal/' );
        }

        if ( untrailingslashit( $target ) === untrailingslashit( home_url( '/news/' ) ) ) {
            return;
        }

        wp_safe_redirect( $target, 301, 'Lunara Legacy News' );
        exit;
    }
}
add_action( 'template_redirect', 'lunara_handle_legacy_news_redirect', 1 );

/**
 * Normalize rich text into a clean one-line archive summary.
 */
if ( ! function_exists( 'lunara_clean_archive_summary_text' ) ) {
    function lunara_clean_archive_summary_text( $content ) {
        $content = strip_shortcodes( (string) $content );
        $content = wp_strip_all_tags( $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( (string) $content );
    }
}

/**
 * Pull a safe archive intro from a supporting page without leaking shortcode scaffolding.
 */
if ( ! function_exists( 'lunara_get_archive_intro_from_post' ) ) {
    function lunara_get_archive_intro_from_post( $post ) {
        if ( ! ( $post instanceof WP_Post ) ) {
            return '';
        }

        $excerpt = lunara_clean_archive_summary_text( $post->post_excerpt );
        if ( '' !== $excerpt ) {
            return $excerpt;
        }

        return lunara_clean_archive_summary_text( $post->post_content );
    }
}

/**
 * Parse comma/newline-separated post IDs or URLs into published post IDs.
 */
if ( ! function_exists( 'lunara_parse_manual_post_ids' ) ) {
    function lunara_parse_manual_post_ids( $raw_value, $allowed_post_type = '' ) {
        $allowed_post_type = sanitize_key( (string) $allowed_post_type );
        $tokens            = preg_split( '/[\r\n,]+/', (string) $raw_value );
        $post_ids          = array();

        if ( ! is_array( $tokens ) ) {
            return array();
        }

        foreach ( $tokens as $token ) {
            $token   = trim( (string) $token );
            $post_id = 0;

            if ( '' === $token ) {
                continue;
            }

            if ( preg_match( '/^\d+$/', $token ) ) {
                $post_id = intval( $token );
            } elseif ( filter_var( $token, FILTER_VALIDATE_URL ) ) {
                $post_id = url_to_postid( $token );
            }

            if ( $post_id <= 0 ) {
                continue;
            }

            $post = get_post( $post_id );
            if ( ! ( $post instanceof WP_Post ) || 'publish' !== $post->post_status ) {
                continue;
            }

            if ( '' !== $allowed_post_type && $post->post_type !== $allowed_post_type ) {
                continue;
            }

            $post_ids[] = $post_id;
        }

        return array_values( array_unique( array_map( 'intval', $post_ids ) ) );
    }
}

/**
 * Sanitize decimal Customizer values.
 */
function lunara_sanitize_decimal( $value ) {
    return is_numeric( $value ) ? (float) $value : 0;
}

/**
 * Sanitize a CSS font stack entered in the Customizer.
 */
function lunara_sanitize_font_stack( $value ) {
    $value = wp_strip_all_tags( (string) $value );
    $value = preg_replace( '/[^A-Za-z0-9,\-_"\'\s]/', '', $value );
    $value = preg_replace( '/\s+/', ' ', (string) $value );

    return trim( (string) $value );
}

/**
 * Sanitize a select/radio Customizer value against its declared choices.
 *
 * @param string               $input   The value to sanitize.
 * @param WP_Customize_Setting $setting The setting instance.
 * @return string Sanitized value or the setting default.
 */
function lunara_sanitize_select( $input, $setting ) {
    $input   = sanitize_text_field( $input );
    $choices = $setting->manager->get_control( $setting->id )->choices;

    return array_key_exists( $input, $choices ) ? $input : $setting->default;
}

/**
 * Convert a hex color into rgba() for runtime CSS output.
 */
function lunara_hex_to_rgba( $value, $opacity = 1 ) {
    $hex = sanitize_hex_color( $value );

    if ( ! $hex ) {
        return 'rgba(201,169,97,0.24)';
    }

    $hex = ltrim( $hex, '#' );

    if ( 3 === strlen( $hex ) ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $opacity = max( 0, min( 1, (float) $opacity ) );
    $red     = hexdec( substr( $hex, 0, 2 ) );
    $green   = hexdec( substr( $hex, 2, 2 ) );
    $blue    = hexdec( substr( $hex, 4, 2 ) );
    $alpha   = rtrim( rtrim( sprintf( '%.3F', $opacity ), '0' ), '.' );

    return sprintf( 'rgba(%d,%d,%d,%s)', $red, $green, $blue, $alpha );
}
