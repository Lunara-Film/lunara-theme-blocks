<?php
/**
 * The living desk: Journal, Reviews, and the Oscars, with freshness.
 *
 * Run: php tests/pillar-desk-runtime.php
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Post {
	public $ID;
	public $post_title;
	public $post_date;
	public $post_type;
	public function __construct( $id, $title, $date, $type ) {
		$this->ID         = $id;
		$this->post_title = $title;
		$this->post_date  = $date;
		$this->post_type  = $type;
	}
}
class WP_Query {
	public $posts = array();
	public function __construct( $args ) {
		$type = isset( $args['post_type'] ) ? $args['post_type'] : '';
		$this->posts = isset( $GLOBALS['pillar_posts'][ $type ] ) ? $GLOBALS['pillar_posts'][ $type ] : array();
	}
}

function pillar_check( $ok, $message ) {
	$GLOBALS['checks']++;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_attr__( $text ) { return $text; }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $text ) ); }
function absint( $value ) { return abs( (int) $value ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_type_archive_link( $type ) { return home_url( 'review' === $type ? '/reviews/' : '/journal/' ); }
function get_the_title( $post ) { return $post instanceof WP_Post ? $post->post_title : ''; }
function wp_timezone() { return new DateTimeZone( 'America/Chicago' ); }
function wp_date( $format, $timestamp = null ) { return wp_timezone()->getName() ? ( new DateTimeImmutable( '@' . ( null === $timestamp ? time() : $timestamp ) ) )->setTimezone( wp_timezone() )->format( $format ) : $format; }
function _n( $one, $many, $count ) { return 1 === (int) $count ? $one : $many; }
function number_format_i18n( $number ) { return (string) $number; }

$now = new DateTimeImmutable( 'now', wp_timezone() );
$GLOBALS['pillar_posts'] = array(
	'journal' => array( new WP_Post( 7, 'Eggers Put the Werewolf Back in the Mud', $now->format( 'Y-m-d H:i:s' ), 'journal' ) ),
	'review'  => array( new WP_Post( 8, 'Street Fighter – Unhinged on Purpose', $now->modify( '-1 day' )->format( 'Y-m-d H:i:s' ), 'review' ) ),
);
$GLOBALS['checks'] = 0;

require dirname( __DIR__ ) . '/inc/pillar-desk.php';

function pillar_dom( $html ) {
	$dom = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8">' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return new DOMXPath( $dom );
}

function pillar_status( $xpath, $label ) {
	$nodes = $xpath->query( '//a[.//span[contains(@class,"lunara-pillar-desk-kicker") and text()="' . $label . '"]]' );
	pillar_check( 1 === $nodes->length, $label . ' is one pillar link' );
	return $nodes->item( 0 );
}

$journal = lunara_render_pillar_desk( 'journal' );
$xpath = pillar_dom( $journal );
$nav = $xpath->query( '//nav[contains(@class,"lunara-pillar-desk")]' );
pillar_check( 1 === $nav->length && 3 === $xpath->query( '//nav[contains(@class,"lunara-pillar-desk")]/a' )->length, 'one desk nav with three links' );
$kickers = array();
foreach ( $xpath->query( '//span[contains(@class,"lunara-pillar-desk-kicker")]' ) as $kicker ) {
	$kickers[] = $kicker->textContent;
}
pillar_check( array( 'Journal', 'Reviews', 'Oscars' ) === $kickers, 'the desk reads Journal, Reviews, Oscars in that source order' );
$current = $xpath->query( '//a[@aria-current="page"]' );
pillar_check( 1 === $current->length && false !== strpos( $current->item( 0 )->getAttribute( 'class' ), 'is-current' ) && 'https://example.test/journal/' === $current->item( 0 )->getAttribute( 'href' ), 'the Journal page marks only itself current and links to the Journal' );
$journal_link = pillar_status( $xpath, 'Journal' );
$review_link = pillar_status( $xpath, 'Reviews' );
$oscars_link = pillar_status( $xpath, 'Oscars' );
pillar_check( false !== strpos( $journal_link->textContent, 'Eggers Put the Werewolf Back in the Mud' ), 'the Journal pillar names the newest entry' );
$journal_fresh = $xpath->query( './/span[contains(@class,"lunara-pillar-desk-fresh")]', $journal_link )->item( 0 );
pillar_check( $journal_fresh instanceof DOMElement && false !== strpos( $journal_fresh->getAttribute( 'class' ), 'is-live' ) && 'Updated today' === trim( $journal_fresh->textContent ) && 1 === $xpath->query( './/span[contains(@class,"lunara-pillar-desk-mark")]', $journal_fresh )->length, 'a Journal that moved today carries a live freshness mark and says so' );
$review_fresh = $xpath->query( './/span[contains(@class,"lunara-pillar-desk-fresh")]', $review_link )->item( 0 );
pillar_check( $review_fresh instanceof DOMElement && false === strpos( $review_fresh->getAttribute( 'class' ), 'is-live' ) && 'Updated yesterday' === trim( $review_fresh->textContent ) && false !== strpos( $review_link->textContent, 'Street Fighter – Unhinged on Purpose' ), 'the Reviews pillar names the newest review and keeps yesterday quiet' );
$oscars_fresh = $xpath->query( './/span[contains(@class,"lunara-pillar-desk-fresh")]', $oscars_link )->item( 0 );
pillar_check( $oscars_fresh instanceof DOMElement && false === strpos( $oscars_fresh->getAttribute( 'class' ), 'is-live' ) && 'On record' === trim( $oscars_fresh->textContent ) && false !== strpos( $oscars_link->textContent, 'The Lunara Film Oscar Ledger' ), 'without a ledger line the Oscars pillar stays the ledger, on record, and quiet' );
pillar_check( 1 === $xpath->query( '//span[contains(@class,"is-live")]' )->length, 'only the pillar that moved today is live' );
pillar_check( 0 === $xpath->query( '//*[@style]' )->length, 'the desk does not carry an inline style' );
pillar_check( 'Updated today' === lunara_pillar_desk_dateline_freshness(), 'Home can say updated today when the Journal moved today' );

$GLOBALS['pillar_posts']['journal'][0]->post_date = '2026-01-02 12:00:00';
$GLOBALS['pillar_posts']['review'][0]->post_date = '2026-01-02 12:00:00';
pillar_check( '' === lunara_pillar_desk_dateline_freshness(), 'an old Journal and an old review do not claim the day is new' );
$stale = pillar_dom( lunara_render_pillar_desk( 'reviews' ) );
pillar_check( 0 === $stale->query( '//span[contains(@class,"is-live")]' )->length, 'an older date does not light the freshness mark' );
$stale_current = $stale->query( '//a[@aria-current="page"]' );
pillar_check( 1 === $stale_current->length && 'https://example.test/reviews/' === $stale_current->item( 0 )->getAttribute( 'href' ) && false !== strpos( $stale_current->item( 0 )->textContent, 'Updated January 2' ), 'the Reviews page can be the lead while its own date stays quiet' );

function add_action() { return true; }
require dirname( __DIR__ ) . '/inc/home-front-desk.php';
$oscars = lunara_render_pillar_desk( 'oscars' );
pillar_check( false !== strpos( $oscars, 'Every ceremony, every nominee, and Sweep Watch.' ) && 1 === substr_count( $oscars, 'aria-current="page"' ) && false !== strpos( $oscars, 'href="https://example.test/oscars/"' ), 'the Oscars pillar uses the same door line the Home masthead uses when the ledger has no counts' );
$GLOBALS['pillar_posts']['journal'][0]->post_date = $now->format( 'Y-m-d H:i:s' );
$items = lunara_home_front_door_dateline_items( 248 );
pillar_check( in_array( 'Dallas', $items, true ) && in_array( '248 reviews', $items, true ) && in_array( 'Updated today', $items, true ), 'the Home dateline keeps place and the review count and adds updated today' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/lunara-public-guardrails.css' );
$start = strpos( $css, '/* Living desk:' );
$end = false !== $start ? strpos( $css, '/* Living desk end */', $start ) : false;
$rule = ( false !== $start && false !== $end ) ? substr( $css, $start, $end - $start ) : '';
pillar_check( '' !== $rule, 'the desk rules are a bounded block' );
preg_match_all( '/#([0-9a-fA-F]{3,8})\b/', $rule, $colors );
$allowed_colors = array( 'c9a961', '0f1d2e', 'fafbfc' );
pillar_check( array() === array_diff( array_map( 'strtolower', $colors[1] ), $allowed_colors ), 'the desk introduces no color outside the gold, surface navy, and text tokens' );
pillar_check( false !== strpos( $rule, 'min-height: 44px' ) && false !== strpos( $rule, 'font-size: 0.8125rem' ) && false === strpos( $rule, 'border-radius' ), 'the desk control is 44px, type stays at the 13px floor, and the corners stay square' );
pillar_check( false === strpos( $rule, 'transition' ) && false === strpos( $rule, 'filter' ) && false === stripos( $rule, 'scale(' ), 'the desk does not transition, filter, or zoom' );
preg_match( '/@keyframes lunara-pillar-desk-lead\s*\{(.*?)\n\}/s', $rule, $lead_motion );
preg_match( '/@keyframes lunara-pillar-desk-pulse\s*\{(.*?)\n\}/s', $rule, $pulse_motion );
pillar_check( isset( $lead_motion[1] ) && false !== strpos( $lead_motion[1], 'transform:' ) && false === strpos( $lead_motion[1], 'opacity' ), 'the current pillar shifts with transform only' );
pillar_check( isset( $pulse_motion[1] ) && false !== strpos( $pulse_motion[1], 'opacity:' ) && false === strpos( $pulse_motion[1], 'transform' ), 'the live freshness mark pulses with opacity only' );
pillar_check( false !== strpos( $rule, 'prefers-reduced-motion: no-preference' ) && false !== strpos( $rule, 'prefers-reduced-motion: reduce' ) && false !== strpos( $rule, 'animation: none' ), 'reduced motion keeps the lead and the mark still' );
pillar_check( false !== strpos( $rule, 'max-width: 759px' ) && false !== strpos( $rule, 'order: -1' ) && false !== strpos( $rule, 'grid-area: lead' ), 'on a phone the lead stacks first, and on a wider screen it takes the lead cell' );
preg_match( '/\.lunara-pillar-desk-title\s*\{[^}]*font-size:\s*clamp\(\s*([0-9.]+)rem/', $rule, $support_size );
preg_match( '/\.is-current \.lunara-pillar-desk-title\s*\{[^}]*font-size:\s*clamp\(\s*([0-9.]+)rem/', $rule, $lead_size );
pillar_check( isset( $support_size[1], $lead_size[1] ) && (float) $lead_size[1] > (float) $support_size[1] && (float) $support_size[1] >= 0.8125, 'the lead title is display type larger than the supporting titles, and neither drops under 13px' );

fwrite( STDOUT, "Pillar desk runtime passed: {$GLOBALS['checks']} checks.\n" );
