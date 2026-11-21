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

$journal = lunara_render_pillar_desk( 'journal' );
pillar_check( 1 === substr_count( $journal, '<nav class="lunara-pillar-desk"' ), 'one desk nav' );
pillar_check( false !== strpos( $journal, '>Journal<' ) && false !== strpos( $journal, '>Reviews<' ) && false !== strpos( $journal, '>Oscars<' ), 'the desk reads Journal, Reviews, Oscars' );
pillar_check( 1 === substr_count( $journal, 'aria-current="page"' ) && false !== strpos( $journal, 'lunara-pillar-desk-link is-current' ) && false !== strpos( $journal, 'href="https://example.test/journal/"' ), 'the Journal page marks only itself current and links to the Journal' );
pillar_check( false !== strpos( $journal, 'Eggers Put the Werewolf Back in the Mud' ) && false !== strpos( $journal, 'Updated today' ), 'the Journal pillar names the newest entry and says it moved today' );
pillar_check( false !== strpos( $journal, 'Street Fighter – Unhinged on Purpose' ) && false !== strpos( $journal, 'Updated yesterday' ), 'the Reviews pillar names the newest review and its day' );
pillar_check( false !== strpos( $journal, 'The Lunara Film Oscar Ledger' ) && false !== strpos( $journal, 'On record' ), 'without a ledger line the Oscars pillar stays the ledger, on record' );
pillar_check( false === strpos( $journal, 'style=' ), 'the desk does not carry an inline style' );
pillar_check( 'Updated today' === lunara_pillar_desk_dateline_freshness(), 'Home can say updated today when the Journal moved today' );

$GLOBALS['pillar_posts']['journal'][0]->post_date = '2026-01-02 12:00:00';
$GLOBALS['pillar_posts']['review'][0]->post_date = '2026-01-02 12:00:00';
pillar_check( '' === lunara_pillar_desk_dateline_freshness(), 'an old Journal and an old review do not claim the day is new' );

function add_action() { return true; }
require dirname( __DIR__ ) . '/inc/home-front-desk.php';
$oscars = lunara_render_pillar_desk( 'oscars' );
pillar_check( false !== strpos( $oscars, 'Every ceremony, every nominee, and Sweep Watch.' ) && 1 === substr_count( $oscars, 'aria-current="page"' ) && false !== strpos( $oscars, 'href="https://example.test/oscars/"' ), 'the Oscars pillar uses the same door line the Home masthead uses when the ledger has no counts' );
$GLOBALS['pillar_posts']['journal'][0]->post_date = $now->format( 'Y-m-d H:i:s' );
$items = lunara_home_front_door_dateline_items( 248 );
pillar_check( in_array( 'Dallas', $items, true ) && in_array( '248 reviews', $items, true ) && in_array( 'Updated today', $items, true ), 'the Home dateline keeps place and the review count and adds updated today' );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/lunara-public-guardrails.css' );
$start = strpos( $css, '.lunara-pillar-desk {' );
$rule = false !== $start ? substr( $css, $start ) : '';
pillar_check( false !== strpos( $rule, 'min-height: 44px' ) && false !== strpos( $rule, 'font-size: 0.8125rem' ) && false === strpos( $rule, 'transition' ) && false === strpos( $rule, 'border-radius' ), 'the desk control is 44px, type stays at the 13px floor, and it does not move or curve' );

fwrite( STDOUT, "Pillar desk runtime passed: {$GLOBALS['checks']} checks.\n" );
