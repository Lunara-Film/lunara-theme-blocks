<?php
/**
 * Archive URLs (Theme 3.2.143).
 *
 * @package Lunara_Film
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lunara_archive_sort_base_url' ) ) {
	/**
	 * The current archive's first page without its sort and paging, for the
	 * archive sort and filter links (3.2.143). get_pagenum_link() escapes its
	 * URL by default (& becomes &#038;), and add_query_arg() reads "#038;..."
	 * as a fragment, so on a page with two query parameters every sort link
	 * lost the second one. The base is built unescaped; each link escapes it
	 * when it prints.
	 *
	 * @param string[] $also Further query keys to drop, such as the year
	 *                       filter's own key for the filter form.
	 * @return string Unescaped URL.
	 */
	function lunara_archive_sort_base_url( $also = array() ) {
		return remove_query_arg( array_merge( array( 'sort', 'paged' ), (array) $also ), get_pagenum_link( 1, false ) );
	}
}
