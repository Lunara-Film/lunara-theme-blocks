<?php
/**
 * Attachment lookups without the slow scan (Theme 3.2.143).
 *
 * attachment_url_to_postid() runs one scan of every `_wp_attached_file` row
 * per call and caches nothing. On the live Home it took about 23 ms a call
 * (the 3.2.142 timer, 2026-10-31), and the theme asked it about 20 times per
 * render: once per Running Order poster, hero slide and front-desk still,
 * mostly for TMDB images that can never be attachments here.
 *
 * lunara_attachment_id_for_url() gives the same answer faster:
 *
 * - A URL outside this site's uploads is never an attachment here. Every
 *   attachment on the site is a WordPress sideload or upload, whose file path
 *   is relative to the uploads folder, so core's scan for such a URL finds
 *   nothing. The answer is 0 at once.
 * - A URL in the uploads gets core's answer, kept in the object cache. The
 *   key carries the posts' last-changed time, which every upload, edit and
 *   deletion moves, so a kept answer is never older than the media library.
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_url_is_local_upload' ) ) {
	/**
	 * True when a URL points into this site's uploads folder, whatever its
	 * scheme (http, https or protocol-relative).
	 *
	 * @param string $url A URL.
	 * @return bool
	 */
	function lunara_url_is_local_upload( $url ) {
		$dir  = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : array();
		$base = isset( $dir['baseurl'] ) ? (string) preg_replace( '#^https?:#i', '', (string) $dir['baseurl'] ) : '';
		if ( '' === $base ) {
			return true; // Unknown uploads location: let core decide.
		}
		$bare = (string) preg_replace( '#^https?:#i', '', trim( (string) $url ) );
		return 0 === stripos( $bare, rtrim( $base, '/' ) . '/' );
	}
}

if ( ! function_exists( 'lunara_attachment_id_for_url' ) ) {
	/**
	 * The attachment ID for an image URL, or 0: attachment_url_to_postid()'s
	 * answer, without its scan where the answer is known.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	function lunara_attachment_id_for_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! function_exists( 'attachment_url_to_postid' ) || ! lunara_url_is_local_upload( $url ) ) {
			return 0;
		}

		$changed = function_exists( 'wp_cache_get_last_changed' ) ? (string) wp_cache_get_last_changed( 'posts' ) : '';
		$key     = md5( $url ) . ':' . $changed;
		$found   = false;
		$cached  = function_exists( 'wp_cache_get' ) ? wp_cache_get( $key, 'lunara_attachment_ids', false, $found ) : false;
		if ( $found ) {
			return max( 0, (int) $cached );
		}

		$id = max( 0, (int) attachment_url_to_postid( $url ) );
		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $key, $id, 'lunara_attachment_ids', DAY_IN_SECONDS );
		}
		return $id;
	}
}
