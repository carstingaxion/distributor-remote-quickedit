<?php
/**
 * Plugin Name:     Distributor - Remote Quickedit
 * Plugin URI:      https://github.com/carstingaxion/distributor-remote-quickedit
 * Description:     Re-enable quickedit for distributed posts on the receiving site. This allows to make changes to the original post from the remote site.
 * Author:          Carsten Bach
 * Author URI:      https://carsten-bach.de
 * Text Domain:     distributor-remote-quickedit
 * Domain Path:     /languages
 * Version:         0.2.0
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @package         Distributor_Remote_Quickedit
 */

namespace Distributor_Remote_Quickedit;

use function _wp_die_process_input;
use function add_action;
use function add_filter;
use function get_current_screen;
use function get_post_meta;
use function get_post_types_by_support;
use function nocache_headers;
// use function remove_action;
use function remove_filter;
use function restore_current_blog;
use function status_header;
use function switch_to_blog;
use function wp_ajax_inline_save;
use function wp_parse_args;

const POST_TYPE_SUPPORT = 'distributor-remote-quickedit';


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// we need 'admin_init' because, we are using ajax
add_action( 'admin_init', __NAMESPACE__ . '\\bootstrap' );


function bootstrap() {

	// Check to see if 'Distributor' plugin is active
	if ( ! function_exists('Distributor\SyndicatedPostUI\remove_quick_edit') ) {
		return;
	}

	// Bring back Quick-Edit for syndicated posts
	add_action( 'load-edit.php', __NAMESPACE__ . '\\re_enable_quick_edit' , 11, 2 ); 

	// Enable custom routine for inline-editing of posts 
	// from the syndicated side of things
	//
	// Core calls this action with priority = 1, and since it's an AJAX request,
	// the whole process is terminated early before the original action can be reached out
	//
	// @see https://github.com/WordPress/wordpress-develop/blob/132984c20f4a539dddeeaf7f9f65716854a89b55/src/wp-admin/admin-ajax.php#L163-L165
	add_action( 'wp_ajax_inline-save', __NAMESPACE__ . '\\_wp_ajax_inline_save', -1 ); 
}


function re_enable_quick_edit() {

	if ( ! is_allowed( get_current_screen()->post_type ) )
		return;

	remove_filter( 'post_row_actions', 'Distributor\SyndicatedPostUI\remove_quick_edit', 10 );
}


/**
 * Routes 'save_post' requests for distributed posts 
 * to their originals and back to the synced version, 
 * which trriggered the action;
 * to kinda-enable inline-editing for distributed posts.
 *
 * NO bulk-editing, yet!
*/
function _wp_ajax_inline_save() {

	if ( ! isset( $_POST['post_ID'], $_POST['post_type'] ) )
		return;

	if ( ! is_allowed( $_POST['post_type'] ) )
		return;

	// the syndicated post on the receiving site|side
	$post_ID = (int) $_POST['post_ID'];

	// the post on the original site|side
	$dt_original_post_id = (int) get_post_meta( $post_ID, 'dt_original_post_id', true );
	
	if ( ! $dt_original_post_id )
		return;

	// Need to change the $_POST global, 
	// as it seems the only way to modify, 
	// where things get saved during wp_insert_post (via an ajax request).
	$_POST['post_ID'] = $dt_original_post_id;

	// goto original
	switch_to_original( $post_ID );

	// replace the default AJAX die handler
	// with a slightly modified one.
	// 
	// This is our place to restore_current_blog(),
	// 
	add_filter( 'wp_die_ajax_handler', function(){
		return __NAMESPACE__ . '\\_ajax_wp_die_handler';
	} );

	// feels weird,
	// but is important!
	// 
	// In ..\plugins\distributor\includes\classes\InternalConnections\NetworkSiteConnection.php
	// around the lines 717ff the 'update_syndicated()' action would be short circuited,
	// because our request is coming on an unusual path.
	// 
	// Luckily there is exactly one filter on the path 
	// to prevent this behaviour and run the full function.
	add_filter( 'use_block_editor_for_post', '__return_false' );

	// after all preparation,
	// we can now run WPs native method for handling inline-saving, 
	// that was removed in the first hand
	wp_ajax_inline_save();
}


function switch_to_original( int $post_ID ) : void {

	$dt_original_blog_id = (int) get_post_meta( $post_ID, 'dt_original_blog_id', true );

	if ( $dt_original_blog_id ) {
		switch_to_blog( $dt_original_blog_id );
	}
}


/**
 * Kills WordPress execution and displays Ajax response with an error message.
 *
 * This is the handler for wp_die() when processing Ajax requests.
 *
 * @since 3.4.0
 * @access private
 *
 * @param string       $message Error message.
 * @param string       $title   Optional. Error title (unused). Default empty.
 * @param string|array $args    Optional. Arguments to control behavior. Default empty array.
 */
function _ajax_wp_die_handler( $message, $title = '', $args = array() ) {
	// Set default 'response' to 200 for Ajax requests.
	$args = wp_parse_args(
		$args,
		array( 'response' => 200 )
	);

	list( $message, $title, $parsed_args ) = _wp_die_process_input( $message, $title, $args );

	if ( ! headers_sent() ) {
		// This is intentional. For backward-compatibility, support passing null here.
		if ( null !== $args['response'] ) {
			status_header( $parsed_args['response'] );
		}
		nocache_headers();
	}

	if ( is_scalar( $message ) ) {
		$message = (string) $message;
	} else {
		$message = '0';
	}

	// !!! THIS IS THE only CHANGE
	restore_current_blog();
	// !!! THIS IS THE only CHANGE // END

	if ( $parsed_args['exit'] ) {
		die( $message );
	}

	echo $message;
}


function allowed_post_types() : array {
	return get_post_types_by_support( POST_TYPE_SUPPORT );
}


function is_allowed( string $post_type ) : bool {
	return isset( array_flip( allowed_post_types() )[ $post_type ] );
}
