<?php
/** Square geometry belongs to the publication, including ordinary saved content. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Preserve other declarations, including quoted/data-URL semicolons. */
function lunara_square_inline_geometry( $style ) {
    if ( ! is_string( $style ) || false === stripos( $style, 'radius' ) ) { return $style; }
    $parts = array(); $start = 0; $quote = ''; $depth = 0; $comment = false;
    $length = strlen( $style );
    for ( $i = 0; $i <= $length; $i++ ) {
        $char = $i < $length ? $style[$i] : ';';
        if ( $comment ) {
            if ( '*' === $char && '/' === ( $style[$i + 1] ?? '' ) ) { $comment = false; $i++; }
            continue;
        }
        if ( '' !== $quote ) {
            if ( '\\' === $char ) { $i++; continue; }
            if ( $char === $quote ) { $quote = ''; }
            continue;
        }
        if ( '/' === $char && '*' === ( $style[$i + 1] ?? '' ) ) { $comment = true; $i++; continue; }
        if ( '"' === $char || "'" === $char ) { $quote = $char; continue; }
        if ( '(' === $char ) { $depth++; }
        if ( ')' === $char ) { $depth = max( 0, $depth - 1 ); }
        if ( ';' !== $char || $depth > 0 ) { continue; }
        $declaration = substr( $style, $start, $i - $start );
        $colon = strpos( $declaration, ':' );
        if ( false !== $colon ) {
            $property = trim( preg_replace( '~/\*.*?\*/~s', '', substr( $declaration, 0, $colon ) ) );
            if ( preg_match( '/^border(?:-(?:top-left|top-right|bottom-left|bottom-right|start-start|start-end|end-start|end-end))?-radius$/i', $property ) ) {
                $important = preg_match( '/!\s*important\s*$/i', $declaration ) ? ' !important' : '';
                $declaration = substr( $declaration, 0, $colon + 1 ) . '0' . $important;
            }
        }
        $parts[] = $declaration . ( $i < $length ? ';' : '' );
        $start = $i + 1;
    }
    // Malformed trailing CSS remains untouched rather than losing content.
    if ( $start <= $length ) { $parts[] = substr( $style, $start ); }
    return implode( '', $parts );
}

/** Read-time compatibility: never rewrite post_content or block attributes in storage. */
function lunara_render_square_content( $html ) {
    if ( ! is_string( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ||
        ( false === stripos( $html, 'radius' ) && false === strpos( $html, 'is-style-rounded' ) ) ) { return $html; }
    $processor = new WP_HTML_Tag_Processor( $html );
    while ( $processor->next_tag() ) {
        $classes = preg_split( '/\s+/', (string) $processor->get_attribute( 'class' ) );
        if ( array_intersect( $classes, array( 'lunara-debrief-seal', 'lunara-debrief-seal-inner', 'lunara-debrief-orbit-plate-mark', 'lunara-pair-thread-origin', 'lunara-pair-thread-node', 'splide__spinner' ) ) ) { continue; }
        $style = $processor->get_attribute( 'style' );
        $square = lunara_square_inline_geometry( $style );
        if ( $square !== $style ) { $processor->set_attribute( 'style', $square ); }
        // Retired WordPress image style can be present in old serialized markup.
        $processor->remove_class( 'is-style-rounded' );
    }
    return $processor->get_updated_html();
}
add_filter( 'render_block', 'lunara_render_square_content', 99 );
add_filter( 'the_content', 'lunara_render_square_content', 99 );

/** Existing Site Editor preferences cannot reinstate per-block rounded corners. */
function lunara_square_global_style_nodes( $node, $radius = '0' ) {
    if ( ! is_array( $node ) ) { return $node; }
    foreach ( $node as $key => $value ) {
        if ( 'border' === $key && is_array( $value ) && array_key_exists( 'radius', $value ) ) {
            $value['radius'] = $radius;
        }
        $node[$key] = is_array( $value ) ? lunara_square_global_style_nodes( $value, $radius ) : $value;
    }
    return $node;
}
function lunara_square_global_styles( $data ) {
    $raw = $data->get_data();
    $settings = lunara_square_global_style_nodes( $raw['settings'] ?? array(), false );
    $settings['border']['radius'] = false;
    $update = array( 'version' => $raw['version'] ?? 2, 'settings' => $settings );
    if ( isset( $raw['styles'] ) ) { $update['styles'] = lunara_square_global_style_nodes( $raw['styles'] ); }
    return $data->update_with( $update );
}
add_filter( 'wp_theme_json_data_user', 'lunara_square_global_styles', 99 );

function lunara_retire_rounded_block_style() {
    unregister_block_style( 'core/image', 'rounded' );
}
add_action( 'init', 'lunara_retire_rounded_block_style', 100 );

function lunara_square_block_registration( $args, $name ) {
    if ( isset( $args['supports']['__experimentalBorder'] ) && is_array( $args['supports']['__experimentalBorder'] ) ) {
        $args['supports']['__experimentalBorder']['radius'] = false;
    }
    if ( 'core/image' === $name && isset( $args['styles'] ) ) {
        $args['styles'] = array_values( array_filter( $args['styles'], function ( $style ) { return 'rounded' !== ( $style['name'] ?? '' ); } ) );
    }
    return $args;
}
add_filter( 'register_block_type_args', 'lunara_square_block_registration', 99, 2 );
