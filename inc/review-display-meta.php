<?php
/**
 * Read-only compatibility for Review metadata used by public renderers.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'lunara_get_review_display_meta' ) ) {
    /**
     * Read a scalar value without displaying an ACF field reference as content.
     *
     * Older Review fields share their underscored storage key with ACF's field
     * references. Only the exact reference for this field selects its actual,
     * nonunderscored ACF value. Ordinary legacy text remains authoritative.
     * Nothing is written back to either storage format.
     *
     * @param int    $post_id Review post ID.
     * @param string $key Underscored Review metadata key.
     * @return string
     */
    function lunara_get_review_display_meta( $post_id, $key ) {
        $value = get_post_meta( $post_id, $key, true );
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $field_name = 0 === strpos( $key, '_lunara_' ) ? substr( $key, 1 ) : '';
        $reference  = 'field_' . $field_name;
        if ( '' !== $field_name && $reference === trim( (string) $value ) ) {
            $value = get_post_meta( $post_id, $field_name, true );
            if ( ! is_scalar( $value ) || $reference === trim( (string) $value ) ) {
                return '';
            }
        }

        return (string) $value;
    }
}
