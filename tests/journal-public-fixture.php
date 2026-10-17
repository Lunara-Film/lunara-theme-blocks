<?php
/** WordPress seams for the actual Journal archive template; fixture data only. */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Post { public function __construct( $values ) { foreach ( $values as $key => $value ) { $this->$key = $value; } } }
class WP_Term { public $taxonomy = 'journal_section'; public $term_id = 1; public $name = 'Signal'; public $slug = 'signal'; public $count = 12; }
class WP_Query { public $posts = array(); public $found_posts = 8; }
function __( $value ) { return $value; }
function _n( $one, $many, $count ) { return 1 === $count ? $one : $many; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function esc_html__( $value ) { return esc_html( $value ); }
function esc_attr__( $value ) { return esc_attr( $value ); }
function esc_html_e( $value ) { echo esc_html( $value ); }
function esc_attr_e( $value ) { echo esc_attr( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_title( $value ) { return strtolower( $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^a-z0-9_-]/i', '', $value ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_header() {}
function get_footer() { lunara_render_custom_footer(); }
function is_tax() { return ! empty( $GLOBALS['jp_term'] ); }
function is_post_type_archive() { return ! is_tax(); }
function get_queried_object() { return $GLOBALS['jp_term'] ?? null; }
function term_description() { return 'Reported film news and commentary.'; }
function is_wp_error() { return false; }
function remove_query_arg( $keys, $url ) { return $url; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
function get_pagenum_link() { return home_url( '/journal/' ); }
function get_post_type_archive_link( $type ) { return home_url( 'review' === $type ? '/reviews/' : '/journal/' ); }
function wp_count_posts() { return (object) array( 'publish' => 317 ); }
function get_posts() { return array( 1 ); }
function get_the_modified_date() { return 'October 5, 2026 8:37 PM'; }
function get_permalink( $id ) { return home_url( '/journal/fixture-' . $id . '/' ); }
function the_permalink() { echo esc_url( get_permalink( get_the_ID() ) ); }
function get_the_ID() { return $GLOBALS['post']->ID; }
function setup_postdata( $post ) {}
function wp_reset_postdata() {}
function get_the_title( $id = 0 ) { return $GLOBALS['jp_posts'][ $id ?: get_the_ID() ]->post_title; }
function the_title() { echo esc_html( get_the_title() ); }
function has_excerpt() { return true; }
function get_the_excerpt( $id ) { return $GLOBALS['jp_posts'][ $id ]->post_excerpt; }
function wp_trim_words( $text, $limit ) { $words = preg_split( '/\s+/', $text ); return implode( ' ', array_slice( $words, 0, $limit ) ) . ( count( $words ) > $limit ? '…' : '' ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function get_the_date() { return 'October 5, 2026'; }
function get_post_thumbnail_id( $id ) { return 5 === $id ? 0 : 100 + $id; }
function get_post_meta() { return 'Film image fixture'; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['jp_mods'][ $key ] ?? $default; }
function wp_get_theme() { return new class { public function get() { return 'local-candidate'; } }; }
function get_bloginfo( $field = '' ) { return 'rss2_url' === $field ? home_url( '/feed/' ) : 'Lunara Film'; }
function bloginfo() { echo 'Lunara Film'; }
function get_privacy_policy_url() { return home_url( '/privacy-policy/' ); }
function the_posts_pagination( $args ) { echo '<a href="' . esc_url( home_url( '/journal/page/2/' ) ) . '">' . esc_html( $args['pagination_next'] ?? $args['next_text'] ) . '</a>'; }
function lunara_get_journal_archive_filter_terms( $taxonomy ) { return 'journal_section' === $taxonomy ? array( new WP_Term() ) : array(); }
function get_term_link( $term ) { return home_url( '/journal-section/' . $term->slug . '/' ); }
function lunara_get_editorial_archive_sort() { return $GLOBALS['jp_sort'] ?? 'date_desc'; }
function lunara_get_dispatch_type_label( $id ) { return 7 === $id ? 'Trailer' : 'Journal'; }
function lunara_post_has_trailer( $id ) { return 7 === $id; }
function lunara_journal_archive_studio_get_public_config() { return $GLOBALS['jp_config']; }
function is_paged() { return ! empty( $GLOBALS['jp_paged'] ); }
function lunara_journal_archive_card_image_markup( $id, $attrs ) { return ! empty( $GLOBALS['jp_failed_media'] ) ? '' : '<img src="/tests/fixtures/home-carousel-landscape.svg" width="1280" height="800" alt="' . esc_attr( $attrs['alt'] ) . '">'; }
require dirname( __DIR__ ) . '/inc/journal-archive-media.php';
function lunara_get_journal_visual_context( $id ) { return array( 'has_provenance' => in_array( $id, array( 1, 2, 3, 4, 6 ), true ), 'source_label' => 6 === $id ? 'Deadline' : 'Dark Horizons', 'has_carousel' => false ); }
function jp_extract( $file, $name ) {
    $tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
    foreach ( $tokens as $i => $token ) {
        if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
        $j = $i + 1; while ( isset( $tokens[$j] ) && ( ! is_array( $tokens[$j] ) || T_STRING !== $tokens[$j][0] ) ) { ++$j; }
        if ( ( $tokens[$j][1] ?? '' ) !== $name ) { continue; }
        $code = ''; $depth = 0; $opened = false;
        for ( $k = $i; $k < count( $tokens ); ++$k ) {
            $part = is_array( $tokens[$k] ) ? $tokens[$k][1] : $tokens[$k]; $code .= $part;
            if ( '{' === $part || ( is_array( $tokens[$k] ) && in_array( $tokens[$k][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
            elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
        }
    } throw new RuntimeException( 'Missing function ' . $name );
}
foreach ( array( 'lunara_journal_archive_studio_defaults', 'lunara_journal_archive_studio_compose_retention_lane', 'lunara_journal_archive_studio_render_sections' ) as $fn ) { jp_extract( 'inc/journal-archive-studio.php', $fn ); }
foreach ( array( 'lunara_site_studio_footer_spec', 'lunara_site_studio_mod_surface_read_state', 'lunara_site_studio_footer_read_state' ) as $fn ) { jp_extract( 'inc/site-studio-adapters.php', $fn ); }
require dirname( __DIR__ ) . '/inc/site-studio-footer-navigation.php';
foreach ( array( 'lunara_render_custom_footer', 'lunara_render_footer_link_list' ) as $fn ) { jp_extract( 'inc/frontend.php', $fn ); }
jp_extract( 'functions.php', 'lunara_render_journal_card_provenance' );
jp_extract( 'inc/trailers.php', 'lunara_render_trailer_card_badge' );
$GLOBALS['jp_config'] = lunara_journal_archive_studio_defaults();
$titles = array( 'Gremlins 3 Slides to 2028, and It Needs to Pick a Lane', 'Of Course Robert Eggers Isn’t Modernizing Shakespeare', 'Stanley Tucci Is Stealing the Mona Lisa, and That’s All I Needed', 'Clayface Looks Like It’s Going to Be Gnarly', 'Casablanca and Chinatown Now Share a Landlord', 'Letterboxd Is Getting Treated Like a Taste Machine', 'Sacrifice Points the Knife at Famous People', 'Angel Finally Gets a Face That Can Fly' );
$excerpts = array( 'Gremlins 3 moves to October 2028. Chris Columbus wrote the first one, so at least it’s not a stranger playing with the toys.', 'Eggers is making Romeo and Juliet in Elizabethan English. Zeffirelli did it pretty, Luhrmann did it loud. I can’t wait to see him do it dark.', 'Stanley Tucci plays a thief going after the Mona Lisa with two strangers who turn out to be his kids. That’s all I needed to hear.', 'A body-horror movie about one of Batman’s grossest villains, from the guy who made Eden Lake. Somebody at that studio said yes to this.', 'Paramount and Warner Bros. become Skydance on October 6. One less studio is a bummer, but Hollywood has always changed hands.', 'Letterboxd being shopped is not the boring tech deal it wants to look like.', 'Gavras brought a flamethrower to celebrity culture and I want every frame of the burn.', 'Casting Asa Germann as Angel is the first smart, slightly dangerous thing this new X-Men has taken.' );
foreach ( $titles as $i => $title ) { $GLOBALS['jp_posts'][$i + 1] = new WP_Post( array( 'ID' => $i + 1, 'post_title' => $title, 'post_excerpt' => $excerpts[$i] ) ); }
function jp_render() { $GLOBALS['wp_query'] = new WP_Query(); $GLOBALS['wp_query']->posts = array_values( $GLOBALS['jp_posts'] ); ob_start(); include dirname( __DIR__ ) . '/archive-journal.php'; return ob_get_clean(); }
