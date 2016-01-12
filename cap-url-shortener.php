<?php
/*
Plugin Name: CAP URL Shortener
Plugin URI: http://americanprogress.org
Description: Provides bit.ly like url shortening of posts automatically and allows users to create their own custom short urls to redirect wherever they want.
Version: 0.1
Author: Seth Rubenstein for Center for American Progress
Author URI: http://sethrubenstein.info
*/

/**
* Copyright (c) 2014 . All rights reserved.
*
* Released under the GPL license
* http://www.opensource.org/licenses/gpl-license.php
*
* This is an add-on for WordPress
* http://wordpress.org/
*
* **********************************************************************
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* **********************************************************************
*/

function cap_short_urls_register() {
	$labels = array(
		'name' => _x( 'Short URLs', 'post type general name' ),
		'singular_name' => _x( 'Short URL', 'post type singular name' ),
		'add_new' => __( 'Add New' ),
		'add_new_item' => __( 'Short URL' ),
		'edit_item' => __( 'Edit Short URL' ),
		'new_item' => __( 'New Short URL' ),
		'view_item' => __( 'View Short URL' ),
		'search_items' => __( 'Search Short URL' ),
		'not_found' => __( 'No Short URL Found' ),
		'not_found_in_trash' => __( 'No Short URL in Trash' ),
		'parent_item_colon' => __( 'Short URL' ),
		'menu_name' => __( 'Short URL' )
	);

	$taxonomies = array();

	$supports = array( 'title', 'revisions' );

	$post_type_args = array(
		'labels' => $labels,
		'singular_label' => __('Short URL'),
		'public' => true,
		'exclude_from_search' => true,
		'show_ui'       => true,
		'publicly_queryable' => true,
		'query_var' => true,
		'capability_type' => 'post',
		'has_archive' => false,
		'hierarchical' => false,
		'rewrite' => array('slug' => 's', 'with_front' => false ),
		'supports' => $supports,
		'menu_position' => 15,
		'taxonomies' => $taxonomies
	);
	register_post_type('cap_short_urls',$post_type_args);
}
add_action('init', 'cap_short_urls_register');

if(function_exists("register_field_group")){
	register_field_group(array (
		'id' => 'acf_short-url-options',
		'title' => 'Short URL Options',
		'fields' => array (
			array (
				'key' => 'field_53ecd7d73bc7f',
				'label' => 'URL to redirect to',
				'name' => 'url_redirect_target',
				'type' => 'text',
				'default_value' => '',
				'placeholder' => 'http://thinkprogress.org/post-name',
				'prepend' => '',
				'append' => '',
				'formatting' => 'none',
				'maxlength' => '',
			),
			array (
				'key' => 'field_53ecd7d73bc00',
				'label' => 'LEGACY URL to redirect to',
				'name' => 'legacy_url_redirect_target',
				'type' => 'text',
				'default_value' => '',
				'placeholder' => 'http://thinkprogress.org/post-name',
				'prepend' => '',
				'append' => '',
				'formatting' => 'none',
				'maxlength' => '',
			),
			array (
				'key' => 'field_53ecf5f30bfbb',
				'label' => 'Post to redirect to',
				'name' => 'post_redirect_target',
				'type' => 'number',
				'instructions' => 'Enter a posts ID number here',
				'default_value' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'min' => '',
				'max' => '',
				'step' => '',
			),
		),
		'location' => array (
			array (
				array (
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'cap_short_urls',
					'order_no' => 0,
					'group_no' => 0,
				),
			),
		),
		'options' => array (
			'position' => 'acf_after_title',
			'layout' => 'no_box',
			'hide_on_screen' => array (
			),
		),
		'menu_order' => 0,
	));
}

/**
* Register the post type template
* @todo figure out why this breaks things.
*/
function cap_short_url_template_register($single_template) {
	global $post;

	/**
	 * Checks for single template by post type
	 * @todo this is interferring with the normal template loading and causes the post to return to a loop view with just that one post. Could literally be anywhere.
	 */
	if ( $post->post_type == "cap_short_urls" && is_user_logged_in() ){
		if(file_exists(plugin_dir_path( __FILE__ ). '/single-cap_short_urls.php')) {
			return plugin_dir_path( __FILE__ ) . '/single-cap_short_urls.php';
		}
		return $single_template;
	}
}
// add_filter('single_template', 'cap_short_url_template_register', 11);

/**
 * This function handles the actual redirection to the target.
 */
function cap_redirect_short_urls() {
	global $post;
	if (is_singular('cap_short_urls')) {
		// Check for the existance of legacy url information. Lead with that if present.
		if ( get_post_meta($post->ID, 'legacy_url_redirect_target', true) ) {
			$legacy_url = get_post_meta($post->ID, 'legacy_url_redirect_target', true);
			// Let's get the actual domain from the legacy url...
			$parse = parse_url($legacy_url);
			$domain = $parse['host'];
			// ... if it is thinkprogress.org then proceed with idenfication of the post targeted. Otherwise just do a straight up redirect.
			if ( 'thinkprogress.org' === $domain || 'www.thinkprogress.org' === $domain ) {
				// We could probably just get away with passing the old url in however... we want to make sure that if in the future the target posts permalink is changed then this short url will still work. So to that end we're going to use url_to_postid() to get the ID of the target post to past into get_permalink().
				$post_id = url_to_postid( $legacy_url );
				$target = get_permalink( $post_id );
				// This will add the ID of the short url post to the target post so that the get_shortlink function will work. It will only do so once bc it checks for unique existence.
				add_post_meta( $post_id, 'post_short_url_target', ''.$post->ID.'', true );
			} else {
				$target = $legacy_url;
			}

	    } elseif ( get_post_meta($post->ID, 'url_redirect_target', true) ) {
			$target = get_post_meta($post->ID, 'url_redirect_target', true);
		} elseif ( get_post_meta($post->ID, 'post_redirect_target', true) ) {
			$target = get_permalink(get_post_meta($post->ID, 'post_redirect_target', true));
		} else {
			$target = get_bloginfo('url');
		}
	    wp_redirect( $target ); exit;
		echo '<!--Short URL Away-->';
	}
}
add_action( 'wp_head', 'cap_redirect_short_urls', 1 );

/**
 * Auto generates a new "short url" post upon publish of an actual post.
 */
function cap_generate_short_url($post_id) {
	$ID = $post_id;
	// Check to make sure we're only running this on 'post' post types
	if ( get_post_type($ID) == 'post' ) {
		// Create a title for the new post type, the original post title + Short URL
		$new_title = get_the_title($ID) . ' Short URL';
		// Double check to make sure a post with the new title doesn't already exist. Prevents duplicate issue.
		if ( !get_page_by_title( $new_title, 'OBJECT', 'cap_short_urls' ) && $_POST['post_status'] == 'publish' && $_POST['original_post_status'] != 'publish' ) {
			// Setup new post variables
			$new_post = array(
				'post_title' => $new_title,
				'post_content' => 'This is a short url',
				// Okay so this is getting a little confusing and I apologize...
				// But I basically want the slug of the new short url to be the ID of the original post.
				'post_name' => ''.$ID.'',
				'post_status' => 'publish',
				'post_type' => 'cap_short_urls'
			);
			// Run insertion
			$new_post_id = wp_insert_post( $new_post, true );
			// Add the id of the post we want to redirect to to the new short url post
			add_post_meta( $new_post_id, 'post_redirect_target', ''.$ID.'', true );
			// Add the id of the short url post to the post we want to redirect to
			add_post_meta( $ID, 'post_short_url_target', ''.$new_post_id.'', true );
		}
	}
}
add_action( 'save_post', 'cap_generate_short_url', 11 );

/**
 * @todo in the future let's rework this so this action happens on the first save whether its a draft, straight to published or future publish.
 *
 * This action will go ahead and create the short url on the initial save of a future published post.
 */
add_action( 'future_post', 'cap_generate_short_url', 10, 2 );

/**
* Override Get Shortlink Button
* @return Looks up the given posts short_url and returns the slug "post_name".
*/
function cap_get_shortlink_handler() {
	global $post, $current_screen;

	// Get the current admin screens post type
	//TP-1392 - Check if variable exists
	if($current_screen) {
		$post_type = $current_screen->post_type;
	}
	else {
	$post_type = null;
	}

	if ( 'cap_short_urls' == $post_type || is_singular('cap_short_urls') ) {
		// If this is a short url then return the redirect target
		$short_url_slug = $post->post_name;
	} elseif ( 'post' == $post_type || is_singular('post') ) {
		// If this is a post lookup the short url slug/post_name and return that.

		// When auto creating the short url we also attach post meta
		// to the original post with the short url id. So we can use this to lookup the short urls slug.
		$short_url_id = get_post_meta( $post->ID, 'post_short_url_target', true );
		if (!empty($short_url_id)) {
			$short_url_post_data = get_post( $short_url_id, ARRAY_A );
			$short_url_slug = $short_url_post_data['post_name'];
		} else {
			$short_url_slug = $post->ID;
		}
	} else {
		$short_url_slug = 'null';
	}

	return 'http://thkpr.gs/'.$short_url_slug;
}
add_filter( 'get_shortlink', 'cap_get_shortlink_handler', 1, 4 );

function cap_hide_shortlink_button() {
	$style = '<style>#edit-slug-box a[href="#"] { display: none; }</style>';
	echo $style;
}
// We may want to hide the shortlink button on say pages, the shortlink post type it self as we aren't auto creating a short url there.
