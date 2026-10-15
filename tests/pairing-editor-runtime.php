<?php
/** Real read-only editor callbacks and review SQL, on a small SQLite fixture. */
define( 'ABSPATH', __DIR__ );
$hooks = array(); $routes = array(); $logged_in = true; $can_edit = true;
function add_action( $name, $callback ) { $GLOBALS['hooks'][$name][] = $callback; }
function register_rest_route( $ns, $path, $args ) { $GLOBALS['routes'][$path] = $args; }
function absint( $value ) { return abs( (int) $value ); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'wp_rest' === $action; }
function current_user_can( $cap, $id = 0 ) { return $GLOBALS['can_edit'] && 999 !== $id; }
function get_permalink( $id ) { return 'https://example.test/reviews/' . $id . '/'; }
function get_edit_post_link( $id, $context ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
function get_post( $id ) { return $GLOBALS['wpdb']->db->query( 'SELECT * FROM posts WHERE ID=' . (int) $id )->fetchObject(); }
function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
function get_post_meta( $id, $key, $single ) { $statement = $GLOBALS['wpdb']->db->prepare( 'SELECT meta_value FROM postmeta WHERE post_id=? AND meta_key=?' ); $statement->execute( array( $id, $key ) ); return $statement->fetchColumn() ?: ''; }
class WP_REST_Response { public function __construct( public $data, public $status ) {} }
class PairingRequest {
    public function __construct( public $params = array(), public $nonce = 'valid' ) {}
    public function get_param( $key ) { return $this->params[$key] ?? ''; }
    public function get_header( $key ) { return $this->nonce; }
}
$wpdb = new class {
    public $posts = 'posts'; public $postmeta = 'postmeta'; public $db;
    public function __construct() {
        $this->db = new PDO( 'sqlite::memory:' );
        $this->db->sqliteCreateFunction( 'regexp', fn( $pattern, $value ) => preg_match( '~' . $pattern . '~', $value ) );
        $this->db->exec( "CREATE TABLE posts (ID INTEGER, post_type TEXT, post_status TEXT, post_password TEXT DEFAULT '', post_date_gmt TEXT DEFAULT '2026-10-15'); CREATE TABLE postmeta (post_id INTEGER, meta_key TEXT, meta_value TEXT)" );
    }
    public function prepare( $sql, $args ) { foreach ( $args as $arg ) { $sql = preg_replace( '/%[sd]/', is_int( $arg ) ? (string) $arg : $this->db->quote( $arg ), $sql, 1 ); } return $sql; }
    public function get_var( $sql ) { return $this->db->query( $sql )->fetchColumn(); }
};
// Extract the actual resolver without booting unrelated theme hooks.
$tokens = token_get_all( file_get_contents( dirname(__DIR__) . '/inc/debrief.php' ) );
foreach ( $tokens as $i => $token ) {
    if ( ! is_array($token) || T_FUNCTION !== $token[0] ) { continue; }
    $j = $i + 1; while ( isset($tokens[$j]) && ( !is_array($tokens[$j]) || T_STRING !== $tokens[$j][0] ) ) { $j++; }
    if ( 'lunara_get_review_permalink_by_imdb_title_id' !== ($tokens[$j][1] ?? '') ) { continue; }
    $code = ''; $depth = 0; $opened = false;
    for ( $k = $i; $k < count($tokens); $k++ ) {
        $part = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k]; $code .= $part;
        if ( '{' === $part || (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES),true)) ) { $depth++; $opened=true; }
        elseif ( '}' === $part && 0 === --$depth && $opened ) { eval($code); break 2; }
    }
}
require dirname(__DIR__) . '/inc/pairing-review-editor.php';
$checks = 0;
function check_pair_editor( $ok, $message ) { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function pair_seed( $id, $status, $tt, $type = 'review', $password = '' ) {
    global $wpdb;
    $wpdb->db->prepare('INSERT INTO posts(ID,post_type,post_status,post_password) VALUES(?,?,?,?)')->execute(array($id,$type,$status,$password));
    $wpdb->db->prepare('INSERT INTO postmeta VALUES(?,?,?)')->execute(array($id,'movie'===$type?'imdb_title_id':'_lunara_imdb_title_id',$tt));
}
pair_seed(10,'publish','https://www.imdb.com/title/TT1234567/');
pair_seed(11,'publish','tt12345678'); pair_seed(12,'draft','tt1234567'); pair_seed(13,'private','tt1234567'); pair_seed(14,'publish','tt1234567','review','secret');
pair_seed(20,'publish','tt1234567','movie'); pair_seed(21,'draft','tt1234567','movie'); pair_seed(30,'publish','tt7654321');
check_pair_editor( get_permalink(10) === lunara_get_review_permalink_by_imdb_title_id('tt1234567'), 'Whole-ID matching ignores newer prefix collisions, drafts, private and protected reviews.' );
check_pair_editor( '' === lunara_get_review_permalink_by_imdb_title_id('tt1234567',10), 'Owning review excluded.' );
check_pair_editor( 'unidentified' === lunara_pairing_editor_review_status('A remake without an ID')['status'], 'No title-only guessing.' );
check_pair_editor( 'unreviewed' === lunara_pairing_editor_review_status('tt2222222')['status'], 'Unmatched film has honest status.' );
check_pair_editor( 'reviewed' === lunara_pairing_editor_lookup(new PairingRequest(array('movie_id'=>20)))->data['status'], 'Selected public film resolves review.' );
check_pair_editor( 'unidentified' === lunara_pairing_editor_lookup(new PairingRequest(array('movie_id'=>21)))->data['status'], 'Unavailable movie metadata not exposed.' );
foreach (array('draft','private','trash') as $status) {
    $wpdb->db->exec("UPDATE posts SET post_status='$status' WHERE ID=10");
    check_pair_editor( 'unreviewed' === lunara_pairing_editor_review_status('tt1234567')['status'], 'Status refreshed after ' . $status );
}
$wpdb->db->exec("UPDATE posts SET post_status='publish' WHERE ID=10");
check_pair_editor( lunara_pairing_editor_permission(new PairingRequest(array('review_id'=>30))), 'Authorized editor permitted.' );
check_pair_editor( !lunara_pairing_editor_permission(new PairingRequest(array('review_id'=>999))), 'Object edit permission enforced.' );
check_pair_editor( !lunara_pairing_editor_permission(new PairingRequest(array(),'bad')), 'Invalid nonce denied.' );
$logged_in=false;check_pair_editor(!lunara_pairing_editor_permission(new PairingRequest()),'Anonymous denied.');$logged_in=true;
$can_edit=false;check_pair_editor(!lunara_pairing_editor_permission(new PairingRequest()),'Noneditor denied.');$can_edit=true;
$wpdb->db->exec("INSERT INTO postmeta VALUES(30,'theme_echo_movie','20'),(30,'_lunara_counter_program','A film | tt2222222'),(30,'_lunara_craft_mirror','A film | tt1234567')");
$report=lunara_pairing_editor_report(new PairingRequest(array('review_id'=>30)));
check_pair_editor(array('reviewed','unreviewed','reviewed')===array_column($report->data['rows'],'status'),'Relational and retained legacy data read together without writes.');
check_pair_editor(array('Theme Echo','Counter Program','Career Context')===array_column($report->data['rows'],'label'),'Full role titles retained.');
check_pair_editor(400===lunara_pairing_editor_report(new PairingRequest(array('review_id'=>20)))->status,'Report requires a Review.');
eval('class Lunara_Debrief_Contract { public static function record_from_review($id) { return array("pairings"=>array(array("role"=>"theme_echo","film"=>array("imdb_title_id"=>"tt2222222")))); } }');
check_pair_editor('unreviewed'===lunara_pairing_editor_report(new PairingRequest(array('review_id'=>30)))->data['rows'][0]['status'],'Canonical contract wins over retained legacy.');
foreach($hooks['rest_api_init'] as $callback){$callback();}
foreach($routes as $route){check_pair_editor('GET'===$route['methods'] && 'lunara_pairing_editor_permission'===$route['permission_callback'],'Both routes are read-only and protected.');}
echo "Pairing editor: $checks checks passed.\n";
