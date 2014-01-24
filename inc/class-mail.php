<?php

class Mail
{

	function send_mail ( $data, $tos = array() )
	{
		$tos = apply_filters('mail_tos', $tos, $data);

		if ( isset( $tos ) && count($tos) > 0 )
		{
			// Set headers & content type
			$headers = 'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>' . "\r\n";
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
			
			// Create body text			
			$mail_content = apply_filters('mail_content', $data);
			
			$title = $mail_content['title'];
			$body = $mail_content['content'];

			// Get mail template			
			ob_start();
			include(get_template_directory() . '/email.php');
			$message = ob_get_contents();
			ob_end_clean();

			// Send mail						
			wp_mail( $tos, get_bloginfo( 'name' ) . ' - ' . $title, $message, $headers );

			remove_filter( 'wp_mail_content_type', 'set_html_content_type' );
		}
	}

	/**
	 * Set mail content type
	 * @return string
	 */
	function set_html_content_type ()
	{
		return 'text/html';
	}

}
$mail = new Mail();