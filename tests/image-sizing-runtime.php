<?php
/**
 * Theme 3.2.152 and 3.2.154: images fit their frames. The CDN candidate
 * helpers, the source-size cap (the CDN scales a crop up when asked for more
 * than the source holds), the slot widths, the Oscars winner photo builder,
 * and the source facts behind the plates and the Debrief backdrop.
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
foreach ( array( 'lunara_resize_wpcom_image_url', 'lunara_image_cdn_source', 'lunara_image_cdn_crop_url', 'lunara_image_cdn_width_url', 'lunara_image_source_size_from_url', 'lunara_image_upload_size', 'lunara_image_cdn_crop_limit', 'lunara_image_cdn_crop_srcset', 'lunara_image_cdn_width_srcset', 'lunara_image_sizes_for', 'lunara_image_blur_backdrop_url' ) as $name ) { sizing_extract( 'inc/setup.php', $name ); }
// The attachment lookup (3.2.155): one upload is known, 666x1000, behind two URL forms.
$GLOBALS['sizing_uploads'] = array( 'https://lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg' => 759 );
function lunara_attachment_id_for_url( $url ) { return $GLOBALS['sizing_uploads'][ $url ] ?? 0; }
function wp_get_attachment_metadata( $id ) { return 759 === (int) $id ? array( 'width' => 666, 'height' => 1000 ) : array(); }
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

$set = lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt30144839-poster.jpg?quality=82&ssl=1', 2, 3, array( 192, 96, 352 ), 352 );
sizing_assert( 1 === preg_match( '#^https://i0\.wp\.com/lunarafilm\.com/wp-content/uploads/2026/03/tt30144839-poster\.jpg\?[^ ]*resize=96%2C144[^ ]* 96w, [^ ]*resize=192%2C288[^ ]* 192w, [^ ]*resize=352%2C528[^ ]* 352w$#', $set['srcset'] ), 'Crop candidates are sorted, each cropped to the 2:3 frame at its width (got ' . $set['srcset'] . ').' );
sizing_assert( false !== strpos( $set['src'], 'resize=352%2C528' ), 'The src is the candidate nearest the fallback width.' );
sizing_assert( array( 'src' => '', 'srcset' => '' ) === lunara_image_cdn_crop_srcset( 'https://example.org/poster.jpg', 2, 3, array( 96, 192 ), 96 ), 'A host that cannot resize gets no invented candidates.' );
sizing_assert( array( 'src' => '', 'srcset' => '' ) === lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt30144839-poster.jpg?quality=82&ssl=1', 2, 3, array( 96 ), 96 ), 'One width is no choice: no srcset.' );
$wide = lunara_image_cdn_crop_srcset( 'https://image.tmdb.org/t/p/original/vb8ynrTs7xp7gaEIplf0zcjJZeh.jpg', 16, 9, array( 420, 1600 ), 1600 );
sizing_assert( false !== strpos( $wide['srcset'], 'https://i0.wp.com/image.tmdb.org/t/p/original/vb8ynrTs7xp7gaEIplf0zcjJZeh.jpg?resize=420%2C236' ) && false !== strpos( $wide['srcset'], 'resize=1600%2C900' ) && false !== strpos( $wide['src'], 'resize=1600%2C900' ), 'A TMDB still gets proxied 16:9 crops (got ' . $wide['srcset'] . ').' );
$whole = lunara_image_cdn_width_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt30144839-poster.jpg?quality=82&ssl=1', array( 768, 192 ), 512 );
sizing_assert( 1 === preg_match( '#w=192[^ ]* 192w, [^ ]*w=768[^ ]* 768w$#', $whole['srcset'] ) && false !== strpos( $whole['src'], 'w=768' ), 'Whole-picture candidates are plain downscales, src nearest the fallback (got ' . $whole['srcset'] . ').' );

// ---- The source-size cap (3.2.154) -------------------------------------------------------------
// The CDN scales a `resize` crop up when asked for more than the source holds
// (a 640x385 still came back as a 704x1056 file, 31 KB against the 18 KB it
// replaced), so no candidate is wider than the widest crop the source can give.
sizing_assert( array( 'width' => 768, 'height' => 512 ) === lunara_image_source_size_from_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/still.jpg?fit=768%2C512&quality=82&ssl=1' ), 'A fit= argument says the source size.' );
sizing_assert( array( 'width' => 0, 'height' => 0 ) === lunara_image_source_size_from_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/p.jpg?quality=86&#038;ssl=1&#038;resize=780%2C1170' ), 'A resize= argument is a request the theme wrote, maybe an upscale already: it says nothing (3.2.155).' );
sizing_assert( array( 'width' => 320, 'height' => 0 ) === lunara_image_source_size_from_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/x.jpg?w=320' ) && array( 'width' => 0, 'height' => 0 ) === lunara_image_source_size_from_url( 'https://image.tmdb.org/t/p/w500/abc.jpg' ) && array( 'width' => 0, 'height' => 0 ) === lunara_image_source_size_from_url( 'https://image.tmdb.org/t/p/original/abc.jpg' ), 'A w= argument gives a width; a TMDB rendition width says nothing about the original the CDN crops (3.2.155).' );
sizing_assert( array( 'width' => 666, 'height' => 1000 ) === lunara_image_upload_size( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg?quality=86&#038;ssl=1&#038;resize=780%2C1170' ) && array() === lunara_image_upload_size( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/unknown.jpg?resize=780%2C1170' ) && array() === lunara_image_upload_size( 'https://image.tmdb.org/t/p/original/abc.jpg' ), 'A site upload behind a CDN URL is measured through its attachment; unknown uploads and TMDB images are not (3.2.155).' );
sizing_assert( 666 === lunara_image_cdn_crop_limit( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg?quality=86&ssl=1&resize=780%2C1170', 2, 3 ), 'The widest 2:3 crop of the 666x1000 portrait is 666px, whatever resize= the theme wrote.' );
sizing_assert( 341 === lunara_image_cdn_crop_limit( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/still.jpg?fit=768%2C512', 2, 3 ) && 256 === lunara_image_cdn_crop_limit( 'https://i0.wp.com/x/wp-content/uploads/a.jpg', 2, 3, array( 'width' => 640, 'height' => 385 ) ) && 0 === lunara_image_cdn_crop_limit( 'https://i0.wp.com/x/wp-content/uploads/a.jpg', 2, 3 ), 'The widest 2:3 crop of a 768x512 still is 341px wide, of a 640x385 one 256px; an unknown source has no limit.' );
$capped = lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/still.jpg?fit=768%2C512&quality=82&ssl=1', 2, 3, array( 96, 192, 256, 352, 512, 704 ), 352 );
sizing_assert( 1 === preg_match( '/ 96w, [^ ]+ 192w, [^ ]+ 256w, [^ ]+resize=341%2C512[^ ]* 341w$/', $capped['srcset'] ) && false !== strpos( $capped['src'], 'resize=341%2C512' ), 'Candidates stop at the source\'s widest crop, which joins the list; src is the nearest kept (got ' . $capped['srcset'] . ').' );
$known = lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/08/still.jpg?fit=640%2C385&ssl=1', 2, 3, array( 96, 192, 256, 352, 512, 704 ), 352, array( 'width' => 1920, 'height' => 1280 ) );
sizing_assert( false !== strpos( $known['srcset'], ' 704w' ) && false === strpos( $known['srcset'], ' 853w' ) && false === strpos( $known['srcset'], ' 341w' ) && 6 === substr_count( $known['srcset'], 'w' ) - substr_count( $known['srcset'], 'wp-content' ) * 2, 'A caller that knows the original is 1920x1280 keeps every width; the native crop never joins above the widest width asked for (3.2.155; got ' . $known['srcset'] . ').' );
$joined = lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/08/still.jpg?fit=1000%2C667&ssl=1', 2, 3, array( 96, 192, 256, 352, 512, 704 ), 352 );
sizing_assert( 1 === preg_match( '/ 352w, [^ ]+resize=444%2C666[^ ]* 444w$/', $joined['srcset'] ), 'The native crop joins only in place of widths the cap removed: a 1000x667 still stops at 444px, between the kept 352 and the removed 512 (got ' . $joined['srcset'] . ').' );
$portrait = lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg?quality=86&ssl=1&resize=780%2C1170', 2, 3, array( 192, 256, 352, 512, 704, 780 ), 512 );
sizing_assert( 1 === preg_match( '/ 512w, [^ ]+resize=666%2C999[^ ]* 666w$/', $portrait['srcset'] ) && false === strpos( $portrait['srcset'], ' 704w' ), 'A 666x1000 portrait the theme once asked for at 780px stops at its own 666px: no 704w or 780w upscale (3.2.155; got ' . $portrait['srcset'] . ').' );
sizing_assert( array( 'src' => '', 'srcset' => '' ) === lunara_image_cdn_crop_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/tiny.jpg?fit=100%2C150', 2, 3, array( 96, 192, 256 ), 192 ), 'A source too small for two candidates gets none, so the caller prints it as given.' );
$whole_capped = lunara_image_cdn_width_srcset( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/08/still.jpg?fit=640%2C385&ssl=1', array( 192, 352, 768 ), 512 );
sizing_assert( 1 === preg_match( '/ 192w, [^ ]+ 352w, [^ ]+w=640[^ ]* 640w$/', $whole_capped['srcset'] ), 'Whole-picture candidates never claim more width than the source has (got ' . $whole_capped['srcset'] . ').' );

// ---- Slot widths ---------------------------------------------------------------------------------
foreach ( array( 'oscars-grid', 'oscars-board', 'oscars-hero', 'debrief-poster', 'pair-card', 'related-grid' ) as $slot ) {
	$sizes = lunara_image_sizes_for( $slot );
	sizing_assert( '' !== $sizes && false === strpos( $sizes, 'vw' ) && 1 === preg_match( '/\d+px$/', $sizes ), "The {$slot} widths are pixel widths ending in the desktop width (got {$sizes})." );
}
sizing_assert( '' === lunara_image_sizes_for( 'no-such-slot' ), 'An unknown slot has no sizes.' );
sizing_assert( '120px' === lunara_image_sizes_for( 'oscars-hero' ), 'The hero poster paints 120px wide at every width: a 2:3 image covering a 96x180 box on phones, a 120px box above (3.2.154).' );
sizing_assert( 1 === preg_match( '/^\(max-width: 600px\) 96px, \(max-width: 640px\) 275px, \(max-width: 820px\) 340px, \(max-width: 1200px\) 320px, 256px$/', lunara_image_sizes_for( 'oscars-grid' ) ) && lunara_image_sizes_for( 'oscars-grid' ) === lunara_image_sizes_for( 'oscars-board' ), 'The Oscars grids switch from one 96px column at 600px, not 640px, and say the measured two-, three- and four-up widths (3.2.154).' );
$companion = lunara_image_sizes_for( 'front-desk-companion' );
sizing_assert( 0 === strpos( $companion, '(max-width: 820px) calc(100vw - 64px)' ) && false !== strpos( $companion, '(max-width: 1100px) 440px' ) && 1 === preg_match( '/\d+px$/', $companion ), 'A Home companion still is full width to 820px (the measured gutters), 440px at most in the two-up, and a fixed width beside the lead (got ' . $companion . ').' );
sizing_assert( '(max-width: 420px) 88px, (max-width: 640px) 104px, (max-width: 900px) 128px, 220px' === lunara_image_sizes_for( 'debrief-poster' ), 'The Debrief poster column is 88/104/128/220px, as the band lays it out.' );

// ---- The blurred backdrop and auto ---------------------------------------------------------------
sizing_assert( false !== strpos( lunara_image_blur_backdrop_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/10/still.jpg?fit=2841%2C1598&quality=82&ssl=1' ), 'still.jpg?quality=82&#038;ssl=1&#038;w=320' ) || false !== strpos( lunara_image_blur_backdrop_url( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/10/still.jpg?fit=2841%2C1598&quality=82&ssl=1' ), 'w=320' ), 'A blurred backdrop asks for a 320px source.' );
sizing_assert( 'https://example.org/still.jpg' === lunara_image_blur_backdrop_url( 'https://example.org/still.jpg' ), 'A host that cannot resize keeps its backdrop URL.' );

// ---- The winner photo ---------------------------------------------------------------------------------
$person = lunara_oscars_winner_photo_img( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/06/nm0000759-manual-profile.jpg?quality=86&#038;ssl=1&#038;resize=780%2C1170', 'Paul Thomas Anderson', 'lunara-winner-photo lunara-winner-photo--person', array( 2, 3 ) );
sizing_assert( 1 === preg_match( '#^<img src="[^"]*resize=512%2C768[^"]*" srcset="[^"]*resize=192%2C288[^"]* 192w, [^"]* 256w, [^"]* 352w, [^"]* 512w, [^"]*resize=666%2C999[^"]* 666w" sizes="auto, \(max-width: 600px\) 96px, \(max-width: 640px\) 275px, \(max-width: 820px\) 340px, \(max-width: 1200px\) 320px, 256px" alt="Paul Thomas Anderson" loading="lazy" decoding="async" class="lunara-winner-photo lunara-winner-photo--person" />$#', $person ), 'A portrait keeps its 2:3 crop at the widths its 666x1000 upload allows, says the grid widths, and never asks the CDN past its own size (got ' . $person . ').' );
$curated = lunara_oscars_winner_photo_img( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/07/best-picture-photo.jpg?fit=768%2C512&ssl=1', 'Anora', 'lunara-winner-photo', null, array( 192, 256, 352, 512, 704, 768 ) );
sizing_assert( false !== strpos( $curated, 'w=192' ) && false === strpos( $curated, 'resize=' ) && false !== strpos( $curated, ' 768w"' ) && false !== strpos( $curated, 'alt="Anora"' ), 'A curated photo of any shape is shown whole: downscales, no crop (got ' . $curated . ').' );
$remote = lunara_oscars_winner_photo_img( 'https://example.org/profile.jpg', 'Someone', 'lunara-winner-photo', array( 2, 3 ) );
sizing_assert( '<img src="https://example.org/profile.jpg" alt="Someone" loading="lazy" decoding="async" class="lunara-winner-photo" />' === $remote, 'A host that cannot resize prints the image as given (got ' . $remote . ').' );
sizing_assert( '' === lunara_oscars_winner_photo_img( '', 'Nobody' ), 'No URL, no image.' );
$plain_plate = lunara_oscars_winner_photo_img( 'https://i0.wp.com/lunarafilm.com/wp-content/uploads/2026/03/tt1.jpg?fit=683%2C1024', 'Anora poster', '', array( 2, 3 ) );
sizing_assert( false === strpos( $plain_plate, 'class=' ) && false !== strpos( $plain_plate, 'resize=192%2C288' ) && false !== strpos( $plain_plate, 'resize=682%2C1023' ) && false !== strpos( $plain_plate, ' 682w"' ) && false === strpos( $plain_plate, ' 704w' ), 'A URL-only plate poster prints no class attribute and stops at the widest 2:3 crop of its 683x1024 source, 682px (got ' . $plain_plate . ').' );
$tmdb_person = lunara_oscars_winner_photo_img( 'https://image.tmdb.org/t/p/w500/abc.jpg', 'Someone', 'lunara-winner-photo lunara-winner-photo--person', array( 2, 3 ) );
sizing_assert( false !== strpos( $tmdb_person, 'https://i0.wp.com/image.tmdb.org/t/p/original/abc.jpg?resize=192%2C288' ), 'A TMDB portrait is proxied and cropped like an upload.' );

// ---- Source facts -----------------------------------------------------------------------------------------
$oscars_data = file_get_contents( ABSPATH . 'inc/oscars-data.php' );
$page_oscars = file_get_contents( ABSPATH . 'page-oscars.php' );
sizing_assert( false === strpos( $oscars_data, "style=\"background-image: url('" ) && false === strpos( $page_oscars, "style=\"background-image: url('" ), 'No Oscars plate prints an inline background: the deferred stylesheet hid it, but the download had already started (33 plates, 1.4 MB on /oscars/; 3.2.154 took the last eleven).' );
sizing_assert( 1 === preg_match( '/lunara_oscars_winner_photo_img\( \$photo_url, \$photo_alt, \'lunara-winner-photo\', null, array\( 192, 256, 352, 512, 704, 768 \), lunara_oscars_attachment_size\( \$photo_id \) \)/', $oscars_data ), 'A curated photo is shown whole (no frame) with its attachment size as the cap.' );
sizing_assert( 1 === preg_match( '/lunara_oscars_winner_photo_img\( \$person_visual\[\'poster_url\'\], \$person_alt, \'lunara-winner-photo lunara-winner-photo--person\', array\( 2, 3 \) \)/', $oscars_data ), 'A portrait keeps its 2:3 crop.' );
sizing_assert( 1 === preg_match( '/lunara_oscars_winner_photo_img\( \$backdrop_url, \$photo_alt, \'lunara-winner-photo\', array\( 16, 9 \), array\( 320, 480, 640, 960 \) \)/', $oscars_data ), 'A still keeps its 16:9 crop at the widths the 960px crop allows.' );
$single = file_get_contents( ABSPATH . 'single-review.php' );
sizing_assert( false !== strpos( $single, 'lunara_image_blur_backdrop_url( $debrief_backdrop )' ), 'The Debrief band blurs a 320px source, not the full still.' );
sizing_assert( false !== strpos( $single, "lunara_render_review_grid_card( get_the_ID(), null, array( 'poster_class' => 'lunara-review-grid-poster--related' ) )" ), 'The Related grid names its narrower poster column.' );
$rendering = file_get_contents( ABSPATH . 'inc/review-rendering.php' );
sizing_assert( false !== strpos( $rendering, "stripos( \$class_string, 'lunara-review-grid-poster--related' )" ) && false !== strpos( $rendering, "function lunara_render_review_grid_card( \$post_id, \$card_index = null, \$args = array() )" ), 'The image profile knows the Related poster column and the card renderer takes the class.' );
$setup = file_get_contents( ABSPATH . 'inc/setup.php' );
sizing_assert( false === strpos( $setup, 'lunara_img_sizes_without_auto' ), 'The eager-sizes helper that nothing called is gone (3.2.154); lunara_oscars_poster_sizes() owns that case.' );

echo "Image sizing: {$checks} checks passed.\n";
