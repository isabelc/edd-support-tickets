<?php
/**
 * EDD Support Tickets User Notifications.
 *
 * This class is a helper that will generate a notification
 * message for the user, including the message and markup.
 *
 * The notification message can be passed in 3 different ways:
 * using the pre-defined messages, by passing the message directly to this class,
 * or by using a base64 encoded message (useful for passing it as a URL var).
 *
 * @package 	EDD_Support_Tickets
 * @author 		Isabel Castillo
 * @license 	GPL-2.0+
 * @copyright 	Copyright (c) 2015-2016, Isabel Castillo
 */
class EDDSTIX_Notification {

	/**
	 * Notification "case".
	 *
	 * The case defines the type of notification ot be displayed.
	 * A notification can be a success, a failure, etc.
	 * 
	 * @var string
	 */
	public $case = false;

	/**
	 * Notification message.
	 *
	 * The message to display in the notification.
	 * The message can be of various formats, including base64 encoded,
	 * and can contain HTML.
	 * 
	 * @var string
	 */
	public $message = null;

	public function __construct( $case = false, $message = false ) {

		if ( false === $message && isset( $_REQUEST['message'] ) ) {
			$message = $_REQUEST['message'];
		}

		/**
		 * If the case is set, we just need to get the message.
		 */
		if ( $case ) {

			/**
			 * If the case is decode, it means the message has been passed base64 encoded.
			 * We need to decode and sanitize it before displaying the notice.
			 */

			if ( 'decode' === $case && false !== $decoded = base64_decode( (string)$message ) ) {

				$json_decoded = json_decode( $decoded );

				if ( is_array( $json_decoded ) && count( $json_decoded ) > 1 ) {
					$contents = '<ul>';
					foreach ( $json_decoded as $each ) {
						$contents .= "<li>$each</li>";
					}
					$contents .= '</ul>';
				} elseif ( is_array( $json_decoded ) ) {
					$contents = $json_decoded[0];
				} else {
					$contents = $json_decoded;
				}

				$this->message = esc_attr( $contents );

				$greeting = apply_filters( 'eddstix_welcome_new_user', __( 'Your account has been successfully created. You can now submit a support ticket.', 'edd-support-tickets' ) );

				if ( $greeting == trim( $this->message ) ) {
					$this->case    = 'success';
				} else {
					$this->case    = 'failure'; // Set the case as a failure by default
					
				}

			} else {

				/**
				 * If the message is passed to the class we try to figure out
				 * if it is the actual message or just a reference to a predefined one.
				 */
				if ( $message ) {

					/**
					 * This is the case where the message is a reference to a pre-defined one.
					 * We can then get the message and the case from here.
					 */
					if ( is_numeric( $message ) && $this->predefined_exists( $message ) ) {
						$predefined    = $this->get_predefined_messages();
						$this->case    = esc_attr( $predefined[$message]['case'] );
						$this->message = esc_attr( $predefined[$message]['message'] );
					} 

					/**
					 * If the $message var is a string we assume it is the actual message.
					 * In this case, we just need to get the $case and $message vars to generate
					 * the notice.
					 */
					elseif ( is_string( $message ) ) {
						$this->case    = esc_attr( $case );
						$this->message = esc_attr( $message );
					}

				}

			}

		}

		/**
		 * This can only mean that we have a predefined message
		 * where the case can be retrieved from within the class.
		 */
		elseif ( false === $case && $message ) {
			if ( $this->predefined_exists( $message ) ) {
				$predefined    = $this->get_predefined_messages();
				$this->case    = esc_attr( $predefined[$message]['case'] );
				$this->message = esc_attr( $predefined[$message]['message'] );
			} elseif ( false !== $decoded = base64_decode( (string)$message ) ) {
				$this->message = esc_attr( json_decode( $decoded ) );
				$this->case    = 'failure';
			}
		}

	}

	/**
	 * Output the notification
	 */
	public function notify() {
		if ( is_null( $this->message ) || false === $this->case ) {
			return false;
		}

		ob_start();
		$this->template();
		$notification = ob_get_clean();
		
		return $notification;

	}

	public function predefined_exists( $id ) {
		if ( array_key_exists( $id, $this->get_predefined_messages() ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * List of predefined messages
	 */
	public function get_predefined_messages() {
		$messages = array(
			'0' 		=> array( 'case' => 'success', 'message' => __( 'placeholder', 'edd-support-tickets' ) ),
			'1' 		=> array( 'case' => 'success', 'message' => __( 'Your ticket has been successfully submitted. One of our agents will get in touch with you ASAP.', 'edd-support-tickets' ) ),
			'2' 		=>  array( 'case' => 'success', 'message' => __( 'Check your e-mail for the confirmation link to renew your password.', 'edd-support-tickets' ) ),
			'3' 		=> array( 'case' => 'failure', 'message' => __( 'A Subject is required. Please fill in the Subject field for your ticket request.', 'edd-support-tickets' ) ),
			'4' 		=> array( 'case' => 'failure', 'message' => __( 'The authenticity of your submission could not be validated. If this ticket is legitimate please try submitting again.', 'edd-support-tickets' ) ),
			'5' 		=> array( 'case' => 'failure', 'message' => __( 'Only registered accounts can submit a ticket. Please register first.', 'edd-support-tickets' ) ),
			'6' 		=> array( 'case' => 'failure', 'message' => __( 'The ticket couldn\'t be submitted for an unknown reason.', 'edd-support-tickets' ) ),
			'7' 		=> array( 'case' => 'failure', 'message' => __( 'Your reply could not be submitted for an unknown reason.', 'edd-support-tickets' ) ),
			'8' 		=> array( 'case' => 'success', 'message' => __( 'Your reply has been sent. Our agent will review it ASAP!', 'edd-support-tickets' ) ),
			'9' 		=> array( 'case' => 'success', 'message' => __( 'The ticket has been successfully re-opened.', 'edd-support-tickets' ) ),
			'10' 		=> array( 'case' => 'failure', 'message' => __( 'It is mandatory to provide a description for your issue.', 'edd-support-tickets' ) ),
			'11' 		=> array( 'case' => 'failure', 'message' => __( 'You do not have the capacity to open a new ticket.', 'edd-support-tickets' ) ),
			'12' 		=> array( 'case' => 'failure', 'message' => __( 'Registrations are currently not allowed.', 'edd-support-tickets' ) ),
			'13' 		=> array( 'case' => 'failure', 'message' => __( 'You are not allowed to view this ticket.', 'edd-support-tickets' ) ),
			'14' 		=> array( 'case' => 'success', 'message' => __( 'Your reply has been sent.', 'edd-support-tickets' ) ),
		);

		return apply_filters( 'eddstix_predefined_notifications', $messages );
	}

	/**
	 * Available notification templates
	 */
	public function template() {

		$case    = $this->case;
		$message = wp_kses_post( htmlspecialchars_decode( $this->message ) );

		switch( $case ):

			case 'success':

				if ( $message ) {

					?>
					<div class="eddstix-alert eddstix-alert-success">
						<?php echo $message; ?>
					</div>
					<?php

				}

			break;

			case 'failure':

				if ( $message ) {

					?>
					<div class="eddstix-alert eddstix-alert-danger">
						<?php echo $message; ?>
					</div>
					<?php

				}

			break;

			case 'info':

				if ( $message ) {

					?>
					<div class="eddstix-alert eddstix-alert-info">
						<?php echo $message; ?>
					</div>
					<?php

				}

			break;

		endswitch;

		/**
		 * eddstix_notification_markup hook
		 */
		do_action( 'eddstix_notification_markup', $case, $message );

	}

}

/**
 * Display notification.
 *
 * This function returns a notification either
 * predefined or customized by the user.
 *
 * @param  string         $case    Type of notification
 * @param  boolean|string $message Message to display
 * @param  boolean        $echo    Whether to echo or return the notification
 *
 * @return void|string           Notification (with markup)
 */
function eddstix_notification( $case, $message = '', $echo = true ) {

	$notification = new EDDSTIX_Notification( $case, $message );

	if ( true === $echo ) {
		echo $notification->notify();
	} else {
		return $notification->notify();
	}
}

/**
 * Create custom notification.
 *
 * Takes a custom message and encodes it so that it can be
 * passed safely as a URL parameter.
 *
 * @param mixed string|array $message Custom message
 * @return string          Encoded message
 */
function eddstix_create_notification( $message ) {
	$encoded = urlencode( base64_encode( json_encode( $message ) ) );
	return $encoded;
}