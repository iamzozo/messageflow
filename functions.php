<?php

// Hide the admin bar on front
if ( !is_admin() ) {

	function hide_admin_bar () {
		return false;
	}

	add_filter( 'show_admin_bar', 'hide_admin_bar' );
}

add_image_size( 'thumb', 150, 150, TRUE );

add_action( 'init', 'custom_init' );

function custom_init () {

	$project_labels = array(
	    'name' => 'Projects',
	    'singular_name' => 'Project',
	    'add_new' => 'Add New',
	    'add_new_item' => 'Add New project',
	    'edit_item' => 'Edit project',
	    'new_item' => 'New project',
	    'all_items' => 'All projects',
	    'view_item' => 'View project',
	    'search_items' => 'Search projects',
	    'not_found' => 'No projects found',
	    'not_found_in_trash' => 'No projects found in Trash',
	    'parent_item_colon' => '',
	    'menu_name' => 'Projects'
	);

	$project_args = array(
	    'labels' => $project_labels,
	    'public' => true,
	    'publicly_queryable' => true,
	    'show_ui' => true,
	    'show_in_menu' => true,
	    'query_var' => true,
	    'rewrite' => array( 'slug' => 'project' ),
	    'capability_type' => 'post',
	    'has_archive' => true,
	    'hierarchical' => false,
	    'menu_position' => null,
	    'supports' => array( 'title', 'editor', 'author' ),
	    'taxonomies' => array( 'post_tag' )
	);

	register_post_type( 'project', $project_args );
}

function enqueue_scripts () {
	if ( is_user_logged_in() ) {
		wp_enqueue_script( 'app', get_template_directory_uri() . '/js/app.js', array( 'jquery' ) );
		wp_localize_script( 'app', 'app', array(
		    'date_format' => get_option( 'date_format' ),
		    'base_url' => esc_url( site_url( '/' ) ),
		    'current_user' => get_current_user_id(),
		    'polling' => defined('POLLING') ? POLLING : 1
		) );
	}
}

add_action( 'wp_enqueue_scripts', 'enqueue_scripts' );

/**
 * Include core files
 */
if ( is_user_logged_in() ) {
	require_once get_template_directory() . '/inc/class-core.php';
	require_once get_template_directory() . '/inc/class-mail.php';
	require_once get_template_directory() . '/inc/class-notification.php';

	function wp_init () {
		$core = new Core();
	}

	add_action( 'wp', 'wp_init' );
}

/**
 * Check for is ajax request
 * @return boolean
 */
function is_ajax () {
	if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' ) {
		return true;
	}
	return false;
}

/**
 * Filter project repsonse based on project members
 * @param array $responses
 * @return array
 */
function user_projects ( $responses ) {
	$uid = get_current_user_id();
	$out = array( );
	foreach ( $responses as $response ) {
		if ( $response['type'] == 'project' ) {
			if (
				(isset( $response['meta']['users'] ) && in_array( $uid, $response['meta']['users'] ))
				|| $response['author']['id'] == $uid
			) {
				$out[] = $response;
			}
		}
		else {
			$out[] = $response;
		}
	}
	return $out;
}

add_filter( 'get_response', 'user_projects' );

/**
 * Add unread field to response by notifications
 * 
 * @param array $responses
 * @return array
 */
function project_notifications ( $responses ) {

	$notifications = get_user_meta( get_current_user_id(), 'notification', TRUE );
	$out = array( );
	$notif_array = array( );

	if ( $notifications ) {
		foreach ( $notifications as $notif ) {
			$notif_array[] = $notif['parent_id'];
		}
	}
	foreach ( $responses as $response ) {
		$response['unread'] = 0;
		if ( $response['type'] == 'project' && is_array( $notif_array ) && in_array( $response['id'], $notif_array ) ) {
			$response['unread'] = 1;
		}
		$out[] = $response;
	}
	return $out;
}

add_filter( 'get_response', 'project_notifications' );

/**
 * Add current user to a project when save
 * @param type $input
 * @return array
 */
function add_author_to_project ( $input ) {
	$uid = get_current_user_id();
	if ( !isset( $input['meta']['users'] ) ) {
		$input['meta']['users'] = array( );
	}
	if ( $input['type'] == 'project' && !in_array( $uid, $input['meta']['users'] ) ) {
		$input['meta']['users'][] = (string) $uid;
	}
	return $input;
}

add_filter( 'save_input', 'add_author_to_project' );

add_action( 'after_save', array( $mail, 'send_mail' ), 0, 2 );

/**
 * Set field name for the lookup in notification array
 * $lookup[{field_name}] == $response_field
 * @return string
 */
function delete_notification_field () {
	return 'parent_id';
}

add_filter( 'delete_notification_field', 'delete_notification_field' );

/**
 * Get the id for the notification lookup to delete
 * @param array $response
 * @return int
 */
function delete_notification_id ( $response ) {
	$object = array_shift( $response );
	if ( $object['type'] == 'message' ) {
		return $object['parent_id'];
	}
}

add_filter( 'delete_notification_id', 'delete_notification_id' );


/**
 * Setup recipients for mail notifications
 * 
 * @param array $tos - Mail recipients
 * @param array $data - Saved data
 * @return array
 */
function mail_tos ( $tos, $data ) {
	if ( $data['parent_id'] != 0 ) {
		$users = get_post_meta( $data['parent_id'], 'users', TRUE );
		foreach ( $users as $user ) {
			$tos[] = get_user_by( 'id', $user )->user_email;
		}
	}
	return $tos;
}

add_filter( 'mail_tos', 'mail_tos', 0, 2 );

/**
 * Setup mail content for mail notifications
 * 
 * @param array $data
 * @return string
 */
function mail_content ( $data ) {
	$return = array( );
	if ( $data['type'] == 'message' ) {
		$return['title'] = 'Message from' . $data['author']['name'];
		$return['content'] = $data['content'];
		$return['content'] .= '<br /><a href="' . site_url( '#projects/' . $data['parent_id'] . '/messages' ) . '">View</a>';
	}
	return $return;
}

add_filter( 'mail_content', 'mail_content' );

/**
 * Get users to set notification for (unread)
 * 
 * @param array $data
 * @return array
 */
function notification_users ( $data ) {
	$return = array( );
	if ( $data['parent_id'] != 0 && $data['type'] == 'message' ) {
		$users = get_post_meta( $data['parent_id'], 'users', TRUE );
		foreach ( $users as $user ) {
			$return[] = $user;
		}
	}
	return $return;
}

add_filter( 'notification_users', 'notification_users' );

add_action( 'after_save', array( $notification, 'set_notifications' ) );
add_action( 'get_response_action', array( $notification, 'delete_notification' ) );