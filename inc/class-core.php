<?php

if ( !defined( 'ABSPATH' ) )
	exit;

class Core {

	function __construct () {
		if ( !is_user_logged_in() )
			return;

		add_action( 'init', array( &$this, 'init' ) );
		load_theme_textdomain( 'projectflow', get_template_directory() . '/languages' );

		if ( is_ajax() ) {

			// Check for section, if not set call simple index and the method
			// PUT, POST methods call {section}_save
			// GET method call {section}_get
			// DELETE method call {section}_delete
			// {section} default is index
			// forexample: 'commment' section with put will call comment_save
			if ( !isset( $_GET['section'] ) ) {
				$section = 'index';
			}
			else {
				$section = $_GET['section'];
			}
			switch ( $_SERVER['REQUEST_METHOD'] ) {
				case 'POST' :
				case 'PUT' : $action = 'save';
					$input = json_decode( file_get_contents( 'php://input' ), true );
					break;
				case 'GET' : $action = 'get';
					$input = $_GET['id'];
					break;
				case 'DELETE' : $action = 'delete';
					$input = $_GET['id'];
					break;
			}

			// Call the method if exists
			if ( method_exists( $this, $section . '_' . $action ) ) {
				call_user_method( $section . '_' . $action, $this, $input );
			}
			else {
				echo 'Error! No such method: ' . $action;
			}
		}
	}

	function init () {
		
	}

	/**
	 * Insert a new comment for an object
	 */
	function comment_save ( $input ) {
		$time = current_time( 'mysql' );

		$user = wp_get_current_user();

		$data = array(
		    'comment_post_ID' => $input['object_id'],
		    'comment_author' => $user->user_nicename,
		    'comment_author_email' => $user->user_email,
		    'comment_content' => $input['content'],
		    'comment_type' => '',
		    'comment_parent' => 0,
		    'user_id' => $user->ID,
		    'comment_author_IP' => $_SERVER['REMOTE_ADDR'],
		    'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
		    'comment_date' => $time,
		    'comment_approved' => 1,
		);
		$id = wp_insert_comment( $data );



		if ( isset( $input['files'] ) ) {
			$files = explode( ',', $input['files'] );
			$files = array_filter( $files, 'strlen' );
			if ( count( $files ) > 0 ) {
				update_comment_meta( $id, 'files', $files );
			}
		}

		$comment = get_comment( $id );
		wp_send_json( array(
		    'id' => $comment->comment_ID,
		    'author' => $comment->comment_author,
		    'date' => strtotime( $comment->comment_date ),
		    'comment' => $comment->comment_content
		) );
	}

	/**
	 * Delete a comment
	 */
	function comment_delete ( $input ) {
		if ( current_user_can( 'edit_others_posts' ) ) {
			wp_delete_comment( $_GET['id'] );
		}
		exit;
	}

	/**
	 * Get posts by running WP_Query
	 * 
	 * Params set by REQUEST_URI for WP_Query
	 * @global object $wpdb
	 */
	function index_get () {

		if ( $_GET['id'] ) {
			$query = new WP_Query( array( 'p' => $_GET['id'], 'post_type' => $_GET['post_type'] ) );
		}
		else {
			$defaults = array(
			    'post_type' => 'post',
			    'post_status' => 'publish'
			);
			$url = parse_url( $_SERVER['REQUEST_URI'] );
			$args = wp_parse_args( $url['query'] );

			$query_args = apply_filters( 'get_query_args', array_merge( $defaults, $args ) );

			$query = new WP_Query( $query_args );
		}

		// Get message IDs for further usage
		$m_ids = array( );
		while ( $query->have_posts() ) {
			$query->the_post();
			$m_ids[] = get_the_ID();
		}
		wp_reset_query();

		// Get files for messages
		$_files = new WP_Query( array(
			    'post_type' => 'attachment',
			    'post_status' => 'inherit',
			    'post_parent__in' => $m_ids
				) );
		
		$files = array( );

		// Loop through on files result
		foreach ( $_files->posts as $file ) {
			$_files->the_post();
			$image = wp_get_attachment_image_src( $file->ID, 'thumb');
			$tmp = wp_get_attachment_metadata( $file->ID );
			$tmp['id'] = $file->ID;
			$tmp['file_url'] = wp_get_attachment_url( $file->ID );
			$tmp['file_name'] = basename(wp_get_attachment_url( $file->ID ));
			$tmp['file_thumb'] = $image[0] ? $image[0] : get_template_directory_uri() . '/img/icons/default.png';
			$files[$file->post_parent][] = $tmp;
		}

		// Get comments for messages
		global $wpdb;
		$where = implode( ',', $m_ids );
		$_comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE comment_post_ID IN ($where)" );
		$comments = array( );
		foreach ( $_comments as $comment ) {
			$comments[$comment->comment_post_ID][] = array(
			    'id' => $comment->comment_ID,
			    'author' => $comment->comment_author,
			    'date' => strtotime( $comment->comment_date ),
			    'comment' => $comment->comment_content
			);
		}

		// Build final response
		$posts = array( );
		global $post;
		while ( $query->have_posts() ) {
			$query->the_post();
			$meta = get_post_meta( get_the_ID() );
			$_metas = array( );
			foreach ( $meta as $k => $m ) {
				$_metas[$k] = get_post_meta( get_the_ID(), $k, TRUE );
			}
			$posts[] = array(
			    'id' => get_the_ID(),
			    'date' => get_the_date( 'U' ),
			    'updated_on' => get_the_modified_date( 'U' ),
			    'title' => get_the_title(),
			    'type' => get_post_type(),
			    'excerpt' => get_the_excerpt(),
			    'content' => get_the_content(),
			    'author' => array(
				'id' => get_the_author_meta( 'ID' ),
				'name' => get_the_author_meta( 'display_name' ),
				'avatar' => get_avatar( get_the_author_meta( 'email' ) ),
				'email' => get_the_author_meta( 'email' )
			    ),
			    'parent_id' => $post->post_parent,
			    'meta' => $_metas,
			    'comments' => $comments[get_the_ID()],
			    'files' => $files[get_the_ID()]
			);
		}
		wp_reset_query();

		// Apply a response filter
		$posts = apply_filters( 'get_response', $posts );
		do_action('get_response_action', $posts);

		// If single return only one element
		if ( $_GET['id'] ) {
			wp_send_json( $posts[0] );
		}
		else {
			wp_send_json( $posts );
		}
	}

	/**
	 * Save content
	 * 
	 */
	function index_save ( $input ) {

		if ( current_user_can( 'edit_posts' ) ) {

			if ( isset( $input['id'] ) && !current_user_can( 'edit_others_posts' ) && get_current_user_id() != $input['author']['id'] ) {
				return;
			}

			$input = apply_filters( 'save_input', $input );

			// Simple check
			if ( empty( $input['title'] ) )
				$input['title'] = substr( wp_strip_all_tags( $input['content'] ), 0, 20 );

			// Setup data			
			if ( isset( $input['title'] ) ) {
				$data['post_title'] = wp_strip_all_tags( $input['title'] );
			}
			if ( isset( $input['content'] ) ) {
				$data['post_content'] = $input['content'];
			}
			if ( isset( $input['type'] ) ) {
				$data['post_type'] = $input['type'];
			}

			if ( isset( $input['parent_id'] ) ) {
				$data['post_parent'] = $input['parent_id'];
			}

			// Update content
			if ( isset( $input['id'] ) && !empty( $input['id'] ) ) {
				$data['ID'] = $input['id'];
				unset( $data['author'] );
				wp_update_post( $data, true );
				$id = $input['id'];
			}
			else {
				// Get current user
				$user = wp_get_current_user();
				
				$data['post_status'] = 'publish';
				$data['post_author'] = $user->ID;
				$id = wp_insert_post( $data, true );
				
				if( ! empty($input['files'])) {
					foreach($input['files'] as $file) {

						$update = array(
						    'ID' => $file['id'],
						    'post_parent' => $id
						);
						wp_update_post($update, true);
					}					
				}
			}

			// Set meta
			if ( isset( $input['meta'] ) ) {
				foreach ( $input['meta'] as $key => $value ) {
					if ( $value != '' ) {

						// Check if its date type
						if ( is_string( $value ) && strtotime( $value ) ) {
							$value = date( 'Y-m-d H:i:s', strtotime( $value ) );
						}

						// If it's a list
						update_post_meta( $id, $key, $value );
					}
					else {
						delete_post_meta( $id, $key );
					}
				}
			}
			
			$input['id'] = $id;
			
			do_action('after_save', $input);
			
			$_GET['id'] = $id;
			$_GET['post_type'] = $input['type'];
			$this->index_get();
			exit;
		}
	}

	/**
	 * Delete objects
	 * @global object $wpdb
	 */
	function index_delete ( $input ) {
		if ( current_user_can( 'delete_posts' ) ) {

			$id = $_GET['id'];

			do_action( 'before_delete', $id );

			$_ids = array( );
			global $wpdb;
			$childs = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $wpdb->posts WHERE post_parent = %d ", $id ) );

			foreach ( $childs as $child ) {
				$_ids[] = $child->id;
				wp_delete_post( $child->id, true );
			}

			$_ids[] = $id;
			$files = new WP_Query( array(
				    'post_parent__in' => $_ids,
				    'post_type' => 'attachment'
					) );

			foreach ( $files->posts as $file ) {
				wp_delete_attachment( $file->ID, TRUE );
			}

			wp_delete_post( $id, true );
			do_action( 'after_delete', $id );
			exit;
		}
	}

	/**
	 * Common fileupload process
	 */
	function file_save () {
		if ( current_user_can( 'upload_files' ) ) {
			if ( !function_exists( 'media_handle_upload' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				require_once( ABSPATH . 'wp-admin/includes/media.php' );
			}

			$movefile = wp_handle_upload( $_FILES['userfile'], array( 'test_form' => FALSE ) );
			if ( !isset( $movefile['error'] ) ) {
				$attachment = array(
				    'post_mime_type' => $movefile['type'],
				    'post_title' => '',
				    'post_content' => '',
				    'post_status' => 'inherit',
				    'post_parent' => $_GET['id']
				);
				$attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
				require_once(ABSPATH . "wp-admin" . '/includes/image.php');
				$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );

				// Set a custom metadata for the attachment, 
				// to retrieve easily on filelisting
				update_post_meta( $attach_id, 'project_id', $_POST['project_id'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				$attach_data['file_url'] = wp_get_attachment_image_src( $attach_id, 'thumbnail' );

				$image = wp_get_attachment_image_src( $attach_id, 'thumb');
				$file = wp_get_attachment_metadata( $attach_id );
				$file['id'] = $attach_id;
				$file['file_url'] = wp_get_attachment_url( $attach_id );
				$file['file_name'] = basename(wp_get_attachment_url( $attach_id ));
				$file['file_thumb'] = $image[0] ? $image[0] : get_template_directory_uri() . '/img/icons/default.png';

				wp_send_json( $file );
			}
			else {
				wp_send_json_error( $movefile['error'] );
			}
		}
	}

	/**
	 * Delete a file
	 */
	function file_delete () {
		wp_delete_attachment( $_GET['id'] );
	}

	/**
	 * Get one or more users
	 * @param int $id
	 */
	function users_get ( $id = NULL ) {
		$args = NULL;
		if ( $id ) {
			$args = array( 'include' => array( $id ) );
		}
		$users = get_users( $args );
		$return = array( );
		$meta_include = array( 'favorites' );
		foreach ( $users as $user ) {
			$metas = array( );
			foreach ( $meta_include as $meta ) {
				$metas[$meta] = get_user_meta( $user->id, $meta, TRUE );
			}
			$return[] = array(
			    'id' => $user->ID,
			    'display_name' => $user->display_name,
			    'username' => $user->user_nicename,
			    'avatar' => get_avatar( $user->user_email ),
			    'email' => $user->user_email,
			    'meta' => $metas
			);
		}
		wp_send_json( $return );
	}

	/**
	 * Save a user
	 */
	function user_save () {

		if ( current_user_can( 'manage_options' ) ) {
			$id = wp_update_user( $_POST );
			if ( isset( $_POST['meta'] ) && $id ) {
				foreach ( $_POST['meta'] as $key => $value ) {
					if ( $value != '' ) {

						// Check if its date type
						if ( is_string( $value ) && strtotime( $value ) ) {
							$value = date( 'Y-m-d H:i:s', strtotime( $value ) );
						}

						update_user_meta( $id, $key, $value );
					}
					else {
						delete_user_meta( $id, $key );
					}
				}
			}

			// Return the saved user
			$this->users_get( $id );
		}
	}

}