<?php
/** Private, read-only review availability for every pairing editor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function lunara_pairing_editor_review_status( $imdb, $exclude = 0 ) {
    preg_match( '/\btt\d{7,8}\b/i', (string) $imdb, $match );
    $tt = isset( $match[0] ) ? strtolower( $match[0] ) : '';
    $url = $tt ? lunara_get_review_permalink_by_imdb_title_id( $tt, $exclude ) : '';
    return array( 'imdb' => $tt, 'status' => ! $tt ? 'unidentified' : ( $url ? 'reviewed' : 'unreviewed' ), 'url' => $url );
}

function lunara_pairing_editor_permission( $request ) {
    if ( ! is_user_logged_in() || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) { return false; }
    $id = absint( $request->get_param( 'review_id' ) );
    return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
}

function lunara_pairing_editor_movie_imdb( $movie_id ) {
    $movie = get_post( $movie_id );
    if ( ! $movie || 'movie' !== $movie->post_type || 'publish' !== $movie->post_status || ! empty( $movie->post_password ) ) { return ''; }
    $imdb = (string) get_post_meta( $movie_id, 'imdb_title_id', true );
    return $imdb ? $imdb : (string) get_post_meta( $movie_id, '_lunara_entity_id', true );
}

function lunara_pairing_editor_lookup( $request ) {
    $review_id = absint( $request->get_param( 'review_id' ) );
    $movie_id = absint( $request->get_param( 'movie_id' ) );
    $imdb = substr( (string) $request->get_param( 'imdb' ), 0, 1000 );
    if ( $movie_id ) {
        $imdb = lunara_pairing_editor_movie_imdb( $movie_id );
    }
    return new WP_REST_Response( lunara_pairing_editor_review_status( $imdb, $review_id ), 200 );
}

/** Reports only saved pairings; no new editorial state or writes. */
function lunara_pairing_editor_report( $request ) {
    $id = absint( $request->get_param( 'review_id' ) );
    if ( ! $id || 'review' !== get_post_type( $id ) ) { return new WP_REST_Response( array( 'message' => 'Choose a Review.' ), 400 ); }
    $rows = array();
    $roles = array( 'theme_echo' => 'Theme Echo', 'counter_program' => 'Counter Program', 'career_context' => 'Career Context' );
    $record = class_exists( 'Lunara_Debrief_Contract' ) ? Lunara_Debrief_Contract::record_from_review( $id ) : null;
    foreach ( $roles as $key => $label ) {
        $imdb = '';
        if ( $record ) {
            foreach ( $record['pairings'] as $pair ) {
                if ( $key === $pair['role'] ) { $imdb = $pair['film']['imdb_title_id'] ?? ''; break; }
            }
        } else {
            $movie_id = absint( get_post_meta( $id, $key . '_movie', true ) );
            $imdb = $movie_id ? lunara_pairing_editor_movie_imdb( $movie_id ) : get_post_meta( $id, '_lunara_' . $key, true );
            if ( ! $imdb && 'career_context' === $key ) { $imdb = get_post_meta( $id, '_lunara_craft_mirror', true ); }
        }
        $rows[] = array_merge( array( 'label' => $label ), lunara_pairing_editor_review_status( $imdb, $id ) );
    }
    return new WP_REST_Response( array( 'rows' => $rows, 'edit_url' => (string) get_edit_post_link( $id, 'raw' ) ), 200 );
}

add_action( 'rest_api_init', function () {
    foreach ( array( 'lookup', 'report' ) as $action ) {
        register_rest_route( 'lunara-pairing-editor/v1', '/' . $action, array(
            'methods' => 'GET', 'callback' => 'lunara_pairing_editor_' . $action,
            'permission_callback' => 'lunara_pairing_editor_permission',
            'args' => array(
                'review_id' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
                'movie_id' => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
                'imdb' => array( 'type' => 'string', 'maxLength' => 1000, 'default' => '' ),
            ),
        ) );
    }
} );

function lunara_pairing_editor_register_script() {
    $asset = lunara_resolve_theme_asset( 'assets/js/lunara-pairing-review-editor.js' );
    if ( ! empty( $asset['path'] ) ) {
        wp_register_script( 'lunara-pairing-review-editor', $asset['uri'], array( 'jquery', 'wp-api-fetch', 'wp-url' ), lunara_theme_asset_version( $asset['path'] ), true );
    }
}
add_action( 'init', 'lunara_pairing_editor_register_script' );
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( $screen && ( 'review' === $screen->post_type || false !== strpos( $screen->id, 'lunara-site-studio' ) ) ) { wp_enqueue_script( 'lunara-pairing-review-editor' ); }
} );
