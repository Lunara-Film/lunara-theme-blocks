<?php
/**
 * Theme 3.2.152: images fit their frames. The CDN candidate helpers, the slot
 * widths, the Oscars winner photo builder, and the source facts behind the
 * Oscars plates, the board art, the Debrief backdrop and the Related grid.
 * Real functions are extracted from the theme; WordPress is stubbed.
 *
 * Run: php tests/image-sizing-runtime.php
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$checks = 0;
function sizing_assert( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

function esc_url( $url ) { return str_replace( '&', '&#038;', (string) $url ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://lunarafilm.com/' . ltrim( (string) $path, '/' ); }
function remove_query_arg( $keys, $url ) {
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	foreach ( (array) $keys as $key ) { unset( $query[ $key ] ); }
	return strtok( $url, '?' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}
function add_query_arg( $args, $value = null, $url = null ) {
	if ( ! is_array( $args ) ) { $args = array( $args => $value ); } else { $url = $value; }
	$parts = parse_url( $url ); parse_str( $parts['query'] ?? '', $query );
	return strtok( $url, '?' ) . '?' . http_build_query( array_merge( $query, $args ) );
}

function sizing_extract( $file, $name ) {
	$tokens = token_get_all( file_get_contents( dirname( __DIR__ ) . '/' . $file ) );
	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) || T_FUNCTION !== $token[0] ) { continue; }
		$j = $i + 1; while ( isset( $tokens[ $j ] ) && ( ! is_array( $tokens[ $j ] ) || T_STRING !== $tokens[ $j ][0] ) ) { ++$j; }
		if ( ( $tokens[ $j ][1] ?? '' ) !== $name ) { continue; }
		$code = ''; $depth = 0; $opened = false;
		for ( $k = $i; $k < count( $tokens ); ++$k ) {
			$part = is_array( $tokens[ $k ] ) ? $tokens[ $k ][1] : $tokens[ $k ]; $code .= $part;
			if ( '{' === $part || ( is_array( $tokens[ $k ] ) && in_array( $tokens[ $k ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) { ++$depth; $opened = true; }
			elseif ( '}' === $part && 0 === --$depth && $opened ) { eval( $code ); return; }
		}
	}
	throw new RuntimeException( 'Missing function ' . $name );
}
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_cdn_width_url', 'lunara_image_cdn_crop_srcset', 'lunara_image_cdn_width_srcset', 'lunara_image_sizes_for', 'lunara_image_blur_backdrop_url', 'lunara_img_sizes_without_auto' ) as $name ) { sizing_extract( 'inc/setup.php', $name ); }
sizing_extract( 'inc/oscars-data.php', 'lunara_oscars_winner_photo_img' );

// ---- The source the CDN can resize ----------------------------------------------------------
$cdn_upload = 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt30144839-poster.jpg?fit=683%2C1024&#038;quality=82&#038;ssl=1';
sizing_assert( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt30144839-poster.jpg?quality=82&ssl=1' === lunara_image_cdn_source( $cdn_upload ), 'A CDN upload loses its size arguments and keeps the rest (got ' . lunara_image_cdn_source( $cdn_upload ) . ').' );
sizing_assert( 'https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg' === lunara_image_cdn_source( 'https://lunarafilm.com/wp-content/uploads/2026/07/still.jpg?resize=1600%2C900' ), 'A site upload keeps its host.' );
sizing_assert( 'https://i0.wp.com/image.tmdb.org/t/p/original/7jK6NuxVg1XuEV7UdNa6KO03IgZ.jpg' === lunara_image_cdn_source( 'https://image.tmdb.org/t/p/w500/7jK6NuxVg1XuEV7UdNa6KO03IgZ.jpg' ), 'A TMDB image at any size is proxied from its original.' );
sizing_assert( 'https://i0.wp.com/image.tmdb.org/t/p/original/abc.jpg?quality=86&ssl=1' === lunara_image_cdn_source( 'https://i0.wp.com/image.tmdb.org/t/p/original/abc.jpg?resize=1600%2C900&quality=86&ssl=1' ), 'An already proxied TMDB image loses its size arguments and keeps the rest (got ' . lunara_image_cdn_source( 'https://i0.wp.com/image.tmdb.org/t/p/original/abc.jpg?resize=1600%2C900&quality=86&ssl=1' ) . ').' );
sizing_assert( '' === lunara_image_cdn_source( 'https://example.org/poster.jpg' ) && '' === lunara_image_cdn_source( 'https://lunarafilm.com/about/' ) && '' === lunara_image_cdn_source( '' ), 'Hosts the CDN cannot resize, non-upload paths and empty URLs give nothing.' );

// ---- Crops and downscales -------------------------------------------------------------------------
sizing_assert( false !== strpos( lunara_image_cdn_crop_url( $cdn_upload, 96, 144 ), 'resize=96%2C144' ) && false === strpos( lunara_image_cdn_crop_url( $cdn_upload, 96, 144 ), 'fit=' ), 'A crop replaces the old fit argument with the frame (got ' . lunara_image_cdn_crop_url( $cdn_upload, 96, 144 ) . ').' );
sizing_assert( '' === lunara_image_cdn_crop_url( 'https://example.org/poster.jpg', 96, 144 ) && '' === lunara_image_cdn_crop_url( $cdn_upload, 0, 144 ), 'No crop for a host that cannot resize or a zero frame.' );
sizing_assert( false !== strpos( lunara_image_cdn_width_url( $cdn_upload, 320 ), 'w=320' ) && false === strpos( lunara_image_cdn_width_url( $cdn_upload, 320 ), 'fit=' ), 'A downscale asks for a width only (got ' . lunara_image_cdn_width_url( $cdn_upload, 320 ) . ').' );

$set = lunara_image_cdn_crop_srcset( $cdn_upload, 2, 3, array( 192, 96, 352 ), 352 );
sizing_assert( 1 === preg_match( '#^https://i0\.wp\.com/lunarafilm\.com/wp-content/uploads/2026/03/tt30144839-poster\.jpg\?[^ ]*resize=96%2C144[^ ]* 96w, [^ ]*resize=192%2C288[^ ]* 192w, [^ ]*resize=352%2C528[^ ]* 352w$#', $set['srcset'] ), 'Crop candidates are sorted, each cropped to the 2:3 frame at its width (got ' . $set['srcset'] . ').' );
sizing_assert( false !== strpos( $set['src'], 'resize=352%2C528' ), 'The src is the candidate nearest the fallback width.' );
sizing_assert( array( 'src' => '', 'srcset' => '' ) === lunara_image_cdn_crop_srcset( 'https://example.org/poster.jpg', 2, 3, array( 96, 192 ), 96 ), 'A host that cannot resize gets no invented candidates.' );
sizing_assert( array( 'src' => '', 'srcset' => '' ) === lunara_image_cdn_crop_srcset( $cdn_upload, 2, 3, array( 96 ), 96 ), 'One width is no choice: no srcset.' );
$wide = lunara_image_cdn_crop_srcset( 'https://image.tmdb.org/t/p/original/vb8ynrTs7xp7gaEIplf0zcjJZeh.jpg', 16, 9, array( 420, 1600 ), 1600 );
sizing_assert( false !== strpos( $wide['srcset'], 'https://i0.wp.com/image.tmdb.org/t/p/original/vb8ynrTs7xp7gaEIplf0zcjJZeh.jpg?resize=420%2C236' ) && false !== strpos( $wide['srcset'], 'resize=1600%2C900' ) && false !== strpos( $wide['src'], 'resize=1600%2C900' ), 'A TMDB still gets proxied 16:9 crops (got ' . $wide['srcset'] . ').' );
$whole = lunara_image_cdn_width_srcset( $cdn_upload, array( 768, 192 ), 512 );
sizing_assert( 1 === preg_match( '#w=192[^ ]* 192w, [^ ]*w=768[^ ]* 768w$#', $whole['srcset'] ) && false !== strpos( $whole['src'], 'w=768' ), 'Whole-picture candidates are plain downscales, src nearest the fallback (got ' . $whole['srcset'] . ').' );

// ---- Slot widths ---------------------------------------------------------------------------------
foreach ( array( 'oscars-grid', 'oscars-board', 'oscars-hero', 'debrief-poster', 'pair-card', 'related-grid' ) as $slot ) {
	$sizes = lunara_image_sizes_for( $slot );
	sizing_assert( '' !== $sizes && false === strpos( $sizes, 'vw' ) && 1 === preg_match( '/\d+px$/', $sizes ), "The {$slot} widths are pixel widths ending in the desktop width (got {$sizes})." );
}
sizing_assert( '' === lunara_image_sizes_for( 'no-such-slot' ), 'An unknown slot has no sizes.' );
sizing_assert( '(max-width: 480px) 96px, 120px' === lunara_image_sizes_for( 'oscars-hero' ), 'The hero poster is 96px on phones and 120px above (measured 768-1920px).' );
sizing_assert( '(max-width: 420px) 88px, (max-width: 640px) 104px, (max-width: 900px) 128px, 220px' === lunara_image_sizes_for( 'debrief-poster' ), 'The Debrief poster column is 88/104/128/220px, as the band lays it out.' );

// ---- The blurred backdrop and auto ---------------------------------------------------------------
sizing_assert( false !== strpos( lunara_image_blur_backdrop_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/10/still.jpg?fit=2841%2C1598&quality=82&ssl=1' ), 'still.jpg?quality=82&#038;ssl=1&#038;w=320' ) || false !== strpos( lunara_image_blur_backdrop_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/10/still.jpg?fit=2841%2C1598&quality=82&ssl=1' ), 'w=320' ), 'A blurred backdrop asks for a 320px source.' );
sizing_assert( 'https://example.org/still.jpg' === lunara_image_blur_backdrop_url( 'https://example.org/still.jpg' ), 'A host that cannot resize keeps its backdrop URL.' );
sizing_assert( '<img loading="eager" sizes="(max-width: 480px) 96px, 120px" src="a.jpg">' === lunara_img_sizes_without_auto( '<img loading="eager" sizes="auto, (max-width: 480px) 96px, 120px" src="a.jpg">' ), 'An eager image loses a leading auto.' );
$lazy_markup = '<img loading="lazy" sizes="auto, 96px" src="a.jpg">';
sizing_assert( $lazy_markup === lunara_img_sizes_without_auto( $lazy_markup ) && '<img src="a.jpg">' === lunara_img_sizes_without_auto( '<img src="a.jpg">' ), 'A lazy image keeps auto; markup without sizes is unchanged.' );

// ---- The winner photo ---------------------------------------------------------------------------------
$person = lunara_oscars_winner_photo_img( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg?quality=86&#038;ssl=1&#038;resize=780%2C1170', 'Paul Thomas Anderson', 'lunara-winner-photo lunara-winner-photo--person', array( 2, 3 ) );
sizing_assert( 1 === preg_match( '#^<img src="[^"]*resize=512%2C768[^"]*" srcset="[^"]*resize=192%2C288[^"]* 192w, [^"]* 256w, [^"]* 352w, [^"]* 512w, [^"]* 704w, [^"]*resize=780%2C1170[^"]* 780w" sizes="auto, \(max-width: 640px\) 96px, \(max-width: 900px\) 340px, \(max-width: 1100px\) 320px, 256px" alt="Paul Thomas Anderson" loading="lazy" decoding="async" class="lunara-winner-photo lunara-winner-photo--person" />$#', $person ), 'A portrait keeps its 2:3 crop at six widths, says the grid widths, and keeps its 780px candidate (got ' . $person . ').' );
$curated = lunara_oscars_winner_photo_img( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/best-picture-photo.jpg?fit=768%2C512&ssl=1', 'Anora', 'lunara-winner-photo', null, array( 192, 256, 352, 512, 704, 768 ) );
sizing_assert( false !== strpos( $curated, 'w=192' ) && false === strpos( $curated, 'resize=' ) && false !== strpos( $curated, ' 768w"' ) && false !== strpos( $curated, 'alt="Anora"' ), 'A curated photo of any shape is shown whole: downscales, no crop (got ' . $curated . ').' );
$remote = lunara_oscars_winner_photo_img( 'https://example.org/profile.jpg', 'Someone', 'lunara-winner-photo', array( 2, 3 ) );
sizing_assert( '<img src="https://example.org/profile.jpg" alt="Someone" loading="lazy" decoding="async" class="lunara-winner-photo" />' === $remote, 'A host that cannot resize prints the image as given (got ' . $remote . ').' );
sizing_assert( '' === lunara_oscars_winner_photo_img( '', 'Nobody' ), 'No URL, no image.' );
$tmdb_person = lunara_oscars_winner_photo_img( 'https://image.tmdb.org/t/p/w500/abc.jpg', 'Someone', 'lunara-winner-photo lunara-winner-photo--person', array( 2, 3 ) );
sizing_assert( false !== strpos( $tmdb_person, 'https://i0.wp.com/image.tmdb.org/t/p/original/abc.jpg?resize=192%2C288' ), 'A TMDB portrait is proxied and cropped like an upload.' );

// ---- Source facts -----------------------------------------------------------------------------------------
$oscars_data = file_get_contents( ABSPATH . 'inc/oscars-data.php' );
sizing_assert( 1 === preg_match( '/function lunara_render_oscars_winner_media_link\b.*?\$poster_style\s*=\s*\'\';/s', $oscars_data ) && false === strpos( $oscars_data, "style=\"background-image: url('" ), 'The winner plate prints no inline background: the deferred stylesheet hid it, but the download had already started (1.4 MB on /oscars/).' );
sizing_assert( 3 === substr_count( $oscars_data, 'lunara_oscars_winner_photo_img(' ) - 1, 'All three winner photo builders (curated, person, backdrop) go through the candidate builder.' );
$portal = file_get_contents( ABSPATH . 'inc/oscars-portal.php' );
sizing_assert( false !== strpos( $portal, "lunara_image_cdn_crop_srcset( \$art_src, 2, 3, array( 96, 192, 256, 352, 512, 704 ), 352 )" ) && false !== strpos( $portal, "lunara_image_sizes_for( 'oscars-board' )" ), 'The board art is cropped to its 2:3 frame at six widths and says the board widths.' );
$single = file_get_contents( ABSPATH . 'single-review.php' );
sizing_assert( false !== strpos( $single, 'lunara_image_blur_backdrop_url( $debrief_backdrop )' ), 'The Debrief band blurs a 320px source, not the full still.' );
sizing_assert( false !== strpos( $single, "lunara_render_review_grid_card( get_the_ID(), null, array( 'poster_class' => 'lunara-review-grid-poster--related' ) )" ), 'The Related grid names its narrower poster column.' );
$rendering = file_get_contents( ABSPATH . 'inc/review-rendering.php' );
sizing_assert( false !== strpos( $rendering, "stripos( \$class_string, 'lunara-review-grid-poster--related' )" ) && false !== strpos( $rendering, "function lunara_render_review_grid_card( \$post_id, \$card_index = null, \$args = array() )" ), 'The image profile knows the Related poster column and the card renderer takes the class.' );
$debrief_public = file_get_contents( ABSPATH . 'inc/debrief-public.php' );
sizing_assert( false !== strpos( $debrief_public, "lunara_image_sizes_for( 'pair-card' )" ) && false !== strpos( $debrief_public, "lunara_image_sizes_for( 'debrief-poster' )" ) && false === strpos( $debrief_public, '32vw, 180px' ), 'The pairing and Debrief posters say their real widths.' );
$front_desk = file_get_contents( ABSPATH . 'inc/home-front-desk.php' );
sizing_assert( false !== strpos( $front_desk, "! \$is_lead && function_exists( 'lunara_image_cdn_crop_srcset' )" ) && false !== strpos( $front_desk, "array( 420, 640, 840, 1200, 1600 ), 1600" ), 'Companion stills get 16:9 crops; the preloaded lead keeps its one source.' );

echo "Image sizing: {$checks} checks passed.\n";
