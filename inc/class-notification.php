<?php

class Notification {

	function mark_as_read ( $id ) {
		$meta = get_user_meta( get_current_user_id(), 'notification', TRUE );
		$new = array( );
		if ( $meta ) {
			foreach ( $meta as $m ) {
				if ( (int) $m['id'] == $id ) {
					continue;
				}
				$new[] = $m;
			}
		}
		update_user_meta( get_current_user_id(), 'notification', $new );
	}

	function set_notifications ( $data ) {

		$users = apply_filters( 'notification_users', $data );
				
		if ( count( $users ) == 0 )
			return;
		
		foreach ( $users as $user ) {

			if ( $user == get_current_user_id() )
				continue;

			$existing = get_user_meta( $user, 'notification', TRUE );

			if ( !$existing )
				$existing = array( );

			$skip = FALSE;
			foreach ( $existing as $e ) {
				if ( $e['id'] == $data['id'] ) {
					$skip = TRUE;
				}
			}

			if ( $skip ) {
				continue;
			}

			$existing[] = array(
			    'id' => $data['id'],
			    'parent_id' => $data['parent_id']
			);

			update_user_meta( $user, 'notification', $existing );
		}
	}

	function delete_notification ($data) {
		
		$field = apply_filters('delete_notification_field', 'id');
		$id = apply_filters('delete_notification_id', $data);
		
		if( ! $id) {
			return;
		}		
				
		$notifications = get_user_meta(get_current_user_id(), 'notification', TRUE);
		$new_notifications = array();
		foreach($notifications as $notif) {
			if($notif[$field] == $id) {
				continue;
			}
			$new_notifications[] = $notif;
		}
		update_user_meta( get_current_user_id(), 'notification', $new_notifications );
	}

}

function get_notifications ( $project_id, $type = FALSE, $echo = FALSE, $id = NULL ) {
	$notifications = get_user_meta( get_current_user_id(), 'notification', TRUE );
	$_projects = FALSE;
	foreach ( $notifications as $notif ) {
		if ( $type && $type != $notif['type'] ) {
			continue;
		}
		if ( $id && $id != $notif['item'] ) {
			continue;
		}
		if ( $notif['project'] == $project_id ) {
			$_projects[] = $notif;
		}
	}
	if ( $echo && $_projects ) {
		return '<span class="counter">' . count( $_projects ) . '</span>';
	}
	return $_projects;
}

$notification = new Notification();