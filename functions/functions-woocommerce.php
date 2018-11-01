<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// CART

	/**
	 * Insert or update a booking in cart
	 * @since 1.1.0 (replace bookacti_insert_booking_in_cart)
	 * @version 1.5.7
	 * @param int $product_id
	 * @param int $variation_id
	 * @param int $user_id
	 * @param int $event_id
	 * @param string $event_start
	 * @param string $event_end
	 * @param int $quantity
	 * @param int $form_id
	 * @return array
	 */
	function bookacti_add_booking_to_cart( $product_id, $variation_id, $user_id, $event_id, $event_start, $event_end, $quantity, $form_id = NULL ) {

		$return_array = array( 'status' => 'failed' );

		// Check if the booking already exists 
		$booking_ids = bookacti_booking_exists( $user_id, $event_id, $event_start, $event_end, 'in_cart' );
		
		// If booking already exist in cart, just update its quantity and expiration date
		$booking_id = 0;
		if( $booking_ids ) {
			// Find the booking id
			global $woocommerce;
			$cart_contents = $woocommerce->cart->get_cart();
			foreach( $cart_contents as $cart_item_id => $cart_item ) {
				// Same product
				if( $product_id !== $cart_item[ 'product_id' ] ) { continue; }
				// Same variation
				if( ( empty( $variation_id ) && ! empty( $cart_item[ 'variation_id' ] ) )
				||  ( ! empty( $variation_id ) && ( empty( $cart_item[ 'variation_id' ] ) || $variation_id !== $cart_item[ 'variation_id' ] ) ) ) { continue; }
				// Same booking
				if( empty( $cart_item[ '_bookacti_options' ][ 'bookacti_booking_id' ] ) || ! in_array( intval( $cart_item[ '_bookacti_options' ][ 'bookacti_booking_id' ] ), $booking_ids, true ) ) { continue; }
				// Same Third-party data
				if( ! apply_filters( 'bookacti_merge_cart_item', true, $cart_item, $product_id, $variation_id, $quantity ) ) { continue; }
				
				$booking_id = $cart_item[ '_bookacti_options' ][ 'bookacti_booking_id' ];
				break;
			}
		}
		
		if( $booking_id ) {
			// Get booking and add new quantity to old one
			$booking = bookacti_get_booking_by_id( $booking_id );
			
			if( $booking ) { 
				$new_quantity = intval( $booking->quantity ) + intval( $quantity );

				// Update quantity
				$updated = bookacti_controller_update_booking_quantity( $booking_id, $new_quantity );

				if( $updated[ 'status' ] === 'success' ) {
					$return_array[ 'status' ] = 'success';
					$return_array[ 'action' ] = 'updated';
					$return_array[ 'id' ] = $booking->id;
				}
			} else {
				$booking_id = 0;
			}
		} 
		
		if( ! $booking_id ) {
			
			$expiration_date = bookacti_get_expiration_time();

			// Insert a new booking
			$booking_id = bookacti_insert_booking( $user_id, $event_id, $event_start, $event_end, $quantity, 'in_cart', 'none', $expiration_date, null, $form_id );

			if( ! is_null( $booking_id ) ) {
				$return_array[ 'status' ] = 'success';
				$return_array[ 'action' ] = 'inserted';
				$return_array[ 'id' ] = $booking_id;
			} else {
				$return_array[ 'message' ]= __( 'Error occurs when trying to temporarily book your event. Please try later.', BOOKACTI_PLUGIN_NAME );
			}
		}
		
		return $return_array;
	}


	/**
	 * Insert or update a booking group in cart
	 * 
	 * @since 1.1.0
	 * @version 1.5.7
	 * @param int $product_id
	 * @param int $variation_id
	 * @param int $user_id
	 * @param int $event_group_id
	 * @param int $quantity
	 * @param int $form_id
	 * @return array
	 */
	function bookacti_add_booking_group_to_cart( $product_id, $variation_id, $user_id, $event_group_id, $quantity, $form_id = NULL ) {

		$return_array = array( 'status' => 'failed' );

		// Check if the booking already exists 
		$booking_group_ids = bookacti_booking_group_exists( $user_id, $event_group_id, 'in_cart' );

		// If booking group already exist in cart, just update its bookings quantity and expiration date
		$booking_group_id = 0;
		if( $booking_group_ids ) {
			// Find the booking id
			global $woocommerce;
			$cart_contents = $woocommerce->cart->get_cart();
			foreach( $cart_contents as $cart_item ) {
				// Same product
				if( $product_id !== $cart_item[ 'product_id' ] ) { continue; }
				// Same variation
				if( ( empty( $variation_id ) && ! empty( $cart_item[ 'variation_id' ] ) )
				||  ( ! empty( $variation_id ) && ( empty( $cart_item[ 'variation_id' ] ) || $variation_id !== $cart_item[ 'variation_id' ] ) ) ) { continue; }
				// Same booking
				if( empty( $cart_item[ '_bookacti_options' ][ 'bookacti_booking_group_id' ] ) || ! in_array( intval( $cart_item[ '_bookacti_options' ][ 'bookacti_booking_group_id' ] ), $booking_group_ids, true ) ) { continue; }
				// Same Third-party data
				if( ! apply_filters( 'bookacti_merge_cart_item', true, $cart_item, $product_id, $variation_id, $quantity ) ) { continue; }
				
				$booking_group_id = $cart_item[ '_bookacti_options' ][ 'bookacti_booking_group_id' ];
				break;
			}
		}
		
		if( $booking_group_id ) {
			// Update quantity of each bookings
			$group_updated = bookacti_controller_update_booking_group_quantity( $booking_group_id, $quantity, true );

			// If each event has been updated return $booking_group_id
			if( $group_updated[ 'status' ] === 'success' ) {
				$return_array[ 'status' ] = 'success';
				$return_array[ 'action' ] = 'updated';
				$return_array[ 'id' ] = $booking_group_id;
			} else if( isset( $group_updated[ 'message' ] ) ) {
				$return_array[ 'message' ]= $group_updated[ 'message' ];
			}

		} else {

			$expiration_date = bookacti_get_expiration_time();

			// Book all events of the group
			$booking_group_id = bookacti_book_group_of_events( $user_id, $event_group_id, $quantity, 'in_cart', 'none', $expiration_date, $form_id );

			if( ! is_null( $booking_group_id ) ) {
				$return_array['status'] = 'success';
				$return_array['action'] = 'inserted';
				$return_array['id']		= $booking_group_id;
			} else if( isset( $group_updated[ 'message' ] ) ) {
				$return_array['message'] = __( 'Error occurs when trying to temporarily book the group of events. Please try later.', BOOKACTI_PLUGIN_NAME );
			}
		}

		return $return_array;
	}


	/**
	 * Update quantity, control the results ans display feedback accordingly
	 * @version 1.5.7
	 * @global woocommerce $woocommerce
	 * @param int $booking_id
	 * @param int $new_quantity
	 * @param string $context
	 * @return array
	 */
	function bookacti_controller_update_booking_quantity( $booking_id, $new_quantity, $context = 'frontend' ) {
		global $woocommerce;
		
		$response = array( 'status' => '' );
		
		// Get cart data and the expiration date
		if( $context === 'frontend' ) {
			$current_cart_expiration_date = bookacti_get_cart_timeout();

			$is_cart_empty_and_expired	= ( $woocommerce->cart->get_cart_contents_count() === $new_quantity && strtotime( $current_cart_expiration_date ) <= time() );
			$is_per_product_expiration	= bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_per_product' );
			$reset_timeout_on_change	= bookacti_get_setting_value( 'bookacti_cart_settings', 'reset_cart_timeout_on_change' );
			$is_expiration_active		= bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_active' );
			$timeout					= bookacti_get_setting_value( 'bookacti_cart_settings', 'cart_timeout' );

			$new_expiration_date = NULL;
			if( $is_expiration_active && ( $reset_timeout_on_change || $is_cart_empty_and_expired ) ) {
				$new_expiration_date = date( 'c', strtotime( '+' . $timeout . ' minutes' ) );
			}
		}
		
		// Check booking availability before updating quantity
		$booking		= bookacti_get_booking_by_id( $booking_id );
		$old_quantity	= intval( $booking->quantity );
		$availability	= intval( bookacti_get_event_availability( $booking->event_id, $booking->event_start, $booking->event_end ) );

		// If the updated booking is active, you must count the quantity already booked by this user in the total quantity available for him
		if( $booking->active ) {
			$new_availability = $availability + $old_quantity - $new_quantity;
			$availability += $old_quantity;
		} else {
			$new_availability = $availability - $new_quantity;
		}
		
		if( intval( $new_availability ) < 0 ) {
			$response[ 'status' ] = 'failed';
			if( $availability > 0 ) { $response[ 'error' ] = 'qty_sup_to_avail'; } 
			else { $response[ 'error' ] = 'no_availability'; }
		}
		
		// Check if the booking number is superior or equal to min quantity
		// Check only single events (group of events are checked in bookacti_controller_update_booking_group_quantity)
		if( $response[ 'status' ] !== 'failed' && $context === 'frontend' && $new_quantity !== 0 && ! $booking->group_id ) {
			
			$event			= bookacti_get_event_by_id( $booking->event_id );
			$title			= apply_filters( 'bookacti_translate_text', $event->title );
			$activity_data	= bookacti_get_metadata( 'activity', $event->activity_id );
			$min_quantity	= isset( $activity_data[ 'min_bookings_per_user' ] ) ? intval( $activity_data[ 'min_bookings_per_user' ] ) : 0;
			$max_quantity	= isset( $activity_data[ 'max_bookings_per_user' ] ) ? intval( $activity_data[ 'max_bookings_per_user' ] ) : 0;
			$max_users		= isset( $activity_data[ 'max_users_per_event' ] ) ? intval( $activity_data[ 'max_users_per_event' ] ) : 0;
			
			$quantity_already_booked	= 0;
			$number_of_users			= 0;
			$current_quantity			= 0;
			
			if( $min_quantity || $max_quantity || $max_users ) {
				// Check if the user has already booked this event
				$filters = bookacti_format_booking_filters( array(
					'event_id'				=> $booking->event_id,
					'event_start'			=> $booking->event_start,
					'event_end'				=> $booking->event_end,
					'user_id'				=> $booking->user_id,
					'active'				=> 1,
					'not_in__booking_id'	=> array( $booking->id )
				) );
				$quantity_already_booked = bookacti_get_number_of_bookings( $filters );
				
				// Check if the event has already been booked by other users
				$bookings_made_by_other_users = bookacti_get_number_of_bookings_per_user_by_event( $booking->event_id, $booking->event_start, $booking->event_end );
				$number_of_users	= count( $bookings_made_by_other_users );
				$current_quantity	= isset( $bookings_made_by_other_users[ $booking->user_id ] ) ? $bookings_made_by_other_users[ $booking->user_id ] : 0;
			}
			
			if( $min_quantity !== 0 && ( $new_quantity + $quantity_already_booked ) < $min_quantity ) { 
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'qty_inf_to_min';
			}
			if( $max_quantity !== 0 && $new_quantity > ( $max_quantity - $quantity_already_booked ) ) { 
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'qty_sup_to_max';
			}
			if( $max_users !== 0 && $current_quantity === 0 && $number_of_users >= $max_users ) { 
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'users_sup_to_max';
			}
		}
		
		// Update booking quantity
		if( $response[ 'status' ] !== 'failed' ) {
			$response = bookacti_update_booking_quantity( $booking_id, $new_quantity, $new_expiration_date, $context );
		}
		
		// Add the availability to the returned result
		$response[ 'availability' ] = $availability;
		
		// Update cart expiration date if needed
		if( $context === 'frontend' ) {
			if( $response[ 'status' ] === 'success'
			&&  $is_expiration_active 
			&&  ( $reset_timeout_on_change || $is_cart_empty_and_expired ) 
			&&  ! $is_per_product_expiration ) {

				bookacti_reset_cart_expiration_dates( $new_expiration_date );
				
			} else if( $response[ 'status' ] === 'failed' ) {
				$message = '';
				if( $response[ 'error' ] === 'qty_sup_to_avail' ) {
					$message = /* translators: %1$s is a variable number of bookings. This sentence is followed by two others : 'but only %1$s is available on this time slot.' and 'Please choose another event or decrease the quantity.' */
								sprintf( _n( 'You want to add %1$s booking to your cart', 'You want to add %1$s bookings to your cart', $new_quantity, BOOKACTI_PLUGIN_NAME ), $new_quantity )
								/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to add %1$s booking to your cart' and followed by 'Please choose another event or decrease the quantity.' */
						. ' ' . sprintf( _n( 'but only %1$s is available on this time slot.', 'but only %1$s are available on this time slot. ', $response[ 'availability' ], BOOKACTI_PLUGIN_NAME ), $response[ 'availability' ] )
								/* translators: This sentence is preceded by two others : 'You want to add %1$s booking to your cart' and 'but only %1$s is available on this time slot.' */
						. ' ' . __( 'Please choose another event or decrease the quantity.', BOOKACTI_PLUGIN_NAME );
					
				} else if( $response[ 'error' ] === 'qty_inf_to_min' ) {
					 /* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the minimum number of reservations required per user is %1$s.' and 'Please choose another event or increase the quantity.' */
					$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $new_quantity, BOOKACTI_PLUGIN_NAME ), $new_quantity, $title );
					if( $quantity_already_booked ) {
						/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or increase the quantity.' */
						$message .= ' ' . sprintf( _n( 'and you have already booked %1$s place, but the minimum number of reservations required per user is %2$s.', 'and you have already booked %1$s places, but the minimum number of reservations required per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $min_quantity );
					} else {
						/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or increase the quantity.' */
						$message .= ' ' . sprintf( __( 'but the minimum number of reservations required per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $min_quantity );
					}
					/* translators: %1$s is a variable quantity. This sentence is preceded by two others : 'You want to make %1$s booking of "%2$s"' and 'but the minimum number of reservations required per user is %1$s.' */
					$message .=	$min_quantity - $current_quantity > 0  ? ' ' . sprintf( __( 'Please choose another event or increase the quantity to %1$s.', BOOKACTI_PLUGIN_NAME ), $min_quantity - $current_quantity ) : ' ' . __( 'Please choose another event', BOOKACTI_PLUGIN_NAME );
				
				} else if( $response[ 'error' ] === 'qty_sup_to_max' ) {
					/* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the maximum number of reservations allowed per user is %1$s.' and 'Please choose another event or decrease the quantity.' */
					$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $new_quantity, BOOKACTI_PLUGIN_NAME ), $new_quantity, $title );
					if( $quantity_already_booked ) {
						/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or decrease the quantity.' */
						$message .= ' ' . sprintf( _n( 'but you have already booked %1$s place and the maximum number of reservations allowed per user is %2$s.', 'but you have already booked %1$s places and the maximum number of reservations allowed per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $max_quantity );
					} else {
						/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or decrease the quantity.' */
						$message .= ' ' . sprintf( __( 'but the maximum number of reservations allowed per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $max_quantity );
					}
					/* translators: %1$s is a variable quantity. This sentence is preceded by two others : 'You want to make %1$s booking of "%2$s"' and 'but the maximum number of reservations allowed per user is %1$s.' */
					$message .= $max_quantity - $current_quantity > 0 ? ' ' . sprintf( __( 'Please choose another event or decrease the quantity to %1$s.', BOOKACTI_PLUGIN_NAME ), $max_quantity - $current_quantity ) : ' ' . __( 'Please choose another event', BOOKACTI_PLUGIN_NAME );
				} else if( $response[ 'error' ] === 'users_sup_to_max' ) {
					$message = __( 'This event has reached the maximum number of users allowed. Bookings from other users are no longer accepted. Please choose another event.', BOOKACTI_PLUGIN_NAME );

				} else if( $response[ 'error' ] === 'no_availability' ) {
					// If the event is no longer available, notify the user
					$message = __( 'This event is no longer available. Please choose another event.', BOOKACTI_PLUGIN_NAME );

				} else if( $response[ 'error' ] === 'failed' ) {
					// If an unknown error has occured during the database operation
					$message = __( 'An error occurs while trying to change a product quantity. Please try again later.', BOOKACTI_PLUGIN_NAME );
				}
				
				if( $message && ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); }
			}
		}

		return $response;
	}


	/**
	 * Update booking group quantity, control the results ans display feedback accordingly
	 * @since 1.1.0
	 * @version 1.5.8
	 * @param int $booking_group_id
	 * @param int $quantity
	 * @param boolean $add_quantity
	 * @param string $context
	 * @return boolean
	 */
	function bookacti_controller_update_booking_group_quantity( $booking_group_id, $quantity, $add_quantity = false, $context = 'frontend' ) {
		
		// Sanitize
		$quantity		= intval( $quantity );
		$add_quantity	= $add_quantity ? true : false;
		
		$response	= array( 'status' => 'success' );
		$message	= '';

		// Get bookings of the group
		$bookings			= bookacti_get_bookings_by_booking_group_id( $booking_group_id );

		// Get group availability
		$group				= bookacti_get_booking_group_by_id( $booking_group_id );
		$group_availability	= bookacti_get_group_of_events_availability( $group->event_group_id );

		// Make sure all events have enough places available
		// Look for the most booked event of the booking group
		$max_booked = 0;
		foreach( $bookings as $booking ) {
			if( ( $booking->active || $booking->state === 'in_cart' ) && $booking->quantity > $max_booked ) {
				$max_booked = intval( $booking->quantity );
			}
		}

		$booking_max_new_quantity = $add_quantity ? $quantity + $max_booked : $quantity;
		
		// If quantity is superior to availablity, return the error
		if( $booking_max_new_quantity > ( $group_availability + $max_booked ) ) {
			
			$response[ 'status' ]		= 'failed';
			$response[ 'error' ]		= 'qty_sup_to_avail';
			$response[ 'availability' ]	= $add_quantity ? $group_availability : $group_availability + $max_booked;
			
			if( $context === 'frontend' ) {
				if( $response[ 'availability' ] > 0 ) {
					$message =	/* translators: %1$s is a variable number of bookings. This sentence is followed by two others : 'but only %1$s is available on this time slot.' and 'Please choose another event or decrease the quantity.' */
								sprintf( _n( 'You want to add %1$s booking to your cart', 'You want to add %1$s bookings to your cart', $quantity, BOOKACTI_PLUGIN_NAME ), $quantity )
								/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to add %1$s booking to your cart' and followed by 'Please choose another event or decrease the quantity.' */
						. ' ' . sprintf( _n( 'but only %1$s is available on this time slot.', 'but only %1$s are available on this time slot. ', $response[ 'availability' ], BOOKACTI_PLUGIN_NAME ), $response[ 'availability' ] )
								/* translators: This sentence is preceded by two others : 'You want to add %1$s booking to your cart' and 'but only %1$s is available on this time slot.' */
						. ' ' . __( 'Please choose another event or decrease the quantity.', BOOKACTI_PLUGIN_NAME );
				} else {
					$message = __( 'This event is no longer available. Please choose another event.', BOOKACTI_PLUGIN_NAME );
				}
				if( $message && ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); }
			}
		}
		
		// Check if min quantity <= booking number <= max quantity
		if( $context === 'frontend' && $booking_max_new_quantity !== 0 ) {
			$event_group	= bookacti_get_group_of_events( $group->event_group_id );
			$title			= apply_filters( 'bookacti_translate_text', $event_group->title );
			$category_data	= bookacti_get_metadata( 'group_category', $event_group->category_id );
			$min_quantity	= isset( $category_data[ 'min_bookings_per_user' ] ) ? intval( $category_data[ 'min_bookings_per_user' ] ) : 0;
			$max_quantity	= isset( $category_data[ 'max_bookings_per_user' ] ) ? intval( $category_data[ 'max_bookings_per_user' ] ) : 0;
			$max_users		= isset( $category_data[ 'max_users_per_event' ] ) ? intval( $category_data[ 'max_users_per_event' ] ) : 0;
			
			$quantity_already_booked	= 0;
			$number_of_users			= 0;
			$current_quantity			= 0;
			
			if( $min_quantity || $max_quantity || $max_users ) {
				// Check if the user has already booked this event
				$filters = bookacti_format_booking_filters( array(
					'event_group_id'			=> $group->event_group_id,
					'user_id'					=> $group->user_id,
					'active'					=> 1,
					'not_in__booking_group_id'	=> array( $group->id ),
					'group_by'					=> 'booking_group'
				) );
				$quantity_already_booked = bookacti_get_number_of_bookings( $filters );
				
				// Check if the event has already been booked by other users
				$bookings_made_by_other_users = bookacti_get_number_of_bookings_per_user_by_group_of_events( $group->event_group_id );
				$number_of_users	= count( $bookings_made_by_other_users );
				$current_quantity	= isset( $bookings_made_by_other_users[ $booking->user_id ] ) ? $bookings_made_by_other_users[ $booking->user_id ] : 0;
			}
			
			if( $min_quantity !== 0 && ( $booking_max_new_quantity + $quantity_already_booked ) < $min_quantity ) {
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'qty_inf_to_min';
				/* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the minimum number of reservations required per user is %1$s.' and 'Please choose another event or increase the quantity.' */
				$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $booking_max_new_quantity, BOOKACTI_PLUGIN_NAME ), $booking_max_new_quantity, $title );
				if( $quantity_already_booked ) {
					/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or increase the quantity.' */
					$message .= ' ' . sprintf( _n( 'and you have already booked %1$s place, but the minimum number of reservations required per user is %2$s.', 'and you have already booked %1$s places, but the minimum number of reservations required per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $min_quantity );
				} else {
					/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or increase the quantity.' */
					$message .= ' ' . sprintf( __( 'but the minimum number of reservations required per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $min_quantity );
				}
				/* translators: %1$s is a variable quantity. This sentence is preceded by two others : 'You want to make %1$s booking of "%2$s"' and 'but the minimum number of reservations required per user is %1$s.' */
				$message .= $min_quantity - $quantity_already_booked > 0 ? ' ' . sprintf( __( 'Please choose another event or increase the quantity to %1$s.', BOOKACTI_PLUGIN_NAME ), $min_quantity - $quantity_already_booked ) : ' ' . __( 'Please choose another event', BOOKACTI_PLUGIN_NAME );
			}
			
			if( $max_quantity !== 0 && $booking_max_new_quantity > ( $max_quantity - $quantity_already_booked ) ) {
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'qty_sup_to_max';
				/* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the maximum number of reservations allowed per user is %1$s.' and 'Please choose another event or decrease the quantity.' */
				$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $booking_max_new_quantity, BOOKACTI_PLUGIN_NAME ), $booking_max_new_quantity, $title );
				if( $quantity_already_booked ) {
					/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or decrease the quantity.' */
					$message .= ' ' . sprintf( _n( 'but you have already booked %1$s place and the maximum number of reservations allowed per user is %2$s.', 'but you have already booked %1$s places and the maximum number of reservations allowed per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $max_quantity );
				} else {
					/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s booking of "%2$s"' and followed by 'Please choose another event or decrease the quantity.' */
					$message .= ' ' . sprintf( __( 'but the maximum number of reservations allowed per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $max_quantity );
				}
				/* translators: %1$s is a variable quantity. This sentence is preceded by two others : 'You want to make %1$s booking of "%2$s"' and 'but the maximum number of reservations allowed per user is %1$s.' */
				$message .= $max_quantity - $quantity_already_booked > 0 ? ' ' . sprintf( __( 'Please choose another event or decrease the quantity to %1$s.', BOOKACTI_PLUGIN_NAME ), $max_quantity - $quantity_already_booked ) : ' ' . __( 'Please choose another event', BOOKACTI_PLUGIN_NAME );
			}
			
			if( $max_users !== 0 && $current_quantity === 0 && $number_of_users >= $max_users ) { 
				$response[ 'status' ] = 'failed';
				$response[ 'error' ] = 'users_sup_to_max';
				$message = __( 'This event has reached the maximum number of users allowed. Bookings from other users are no longer accepted. Please choose another event.', BOOKACTI_PLUGIN_NAME );
			}
			
			if( $message && ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); }
		}
		
		if( $response[ 'status' ] === 'success' ) {
			$response = bookacti_update_booking_group_quantity( $booking_group_id, $quantity, $add_quantity, $context );
		}
		
		return $response;
	}
	
	
	/**
	 * Update booking group and its bookings quantity
	 * @since 1.5.8
	 * @param int $booking_group_id
	 * @param int $quantity
	 * @param boolean $add_quantity
	 * @param string $context
	 * @return array
	 */
	function bookacti_update_booking_group_quantity( $booking_group_id, $quantity, $add_quantity = false, $context = 'frontend' ) {
		$response = array( 'status' => 'success' );

		// Get bookings of the group
		$bookings = bookacti_get_bookings_by_booking_group_id( $booking_group_id );
		
		// Get group availability
		$group				= bookacti_get_booking_group_by_id( $booking_group_id );
		$group_availability	= bookacti_get_group_of_events_availability( $group->event_group_id );
		
		// Update each booking quantity
		$no_changes = 0;
		foreach( $bookings as $booking ) {

			$booking_qty	= $booking->active || $booking->state === 'in_cart' ? intval( $booking->quantity ) : 0;

			// Make sure new quantity isn't over group availability
			$new_quantity = $add_quantity ? $quantity + $booking_qty : $quantity;
			if( $new_quantity > ( $group_availability + $booking_qty ) ){
				$new_quantity = $add_quantity ? $group_availability : $group_availability + $booking_qty;
			}

			// Update quantity
			if( $new_quantity !== 0 ) {
				$updated1 = bookacti_controller_update_booking_quantity( $booking->id, $new_quantity, $context );
			} else {
				$updated1 = bookacti_update_booking_quantity( $booking->id, $new_quantity, '', $context );
			}
			
			// If one fails, set the whole group update status to failed
			if( ! isset( $updated1[ 'status' ] ) || $updated1[ 'status' ] === 'failed' ) {
				$response[ 'status' ]	= 'failed';
				$response[ 'error' ]	= $updated1[ 'error' ];
			} 

			// Count how many booking doesn't change
			else if( $updated1[ 'status' ] === 'no_change' ) {
				$no_changes++;
			}
		}

		// If no bookings were updated
		if( $no_changes >= count( $bookings ) ) {
			$response[ 'status' ]	= 'no_change';
		}

		// Change booking group state
		if( $response[ 'status' ] === 'success' ) {

			$is_admin = $context === 'admin' ? true : false;

			// Change booking group state to remove if quantity = 0
			if( ! $add_quantity && $quantity === 0 ) {
				$new_state	= $context === 'frontend' ? 'removed' : 'cancelled';
				$updated2	= bookacti_update_booking_group_state( $booking_group_id, $new_state );
				do_action( 'bookacti_booking_group_state_changed', $booking_group_id, $new_state, array( 'is_admin' => $is_admin ) );
			}

			// If the group used to be removed (quantity = 0), turn its state to in_cart
			else if( $group->state === 'removed' && $quantity > 0 ) {
				$new_state = $context === 'frontend' ? 'in_cart' : 'pending';
				$updated2 = bookacti_update_booking_group_state( $booking_group_id, $new_state );
				do_action( 'bookacti_booking_group_state_changed', $booking_group_id, $new_state, array( 'is_admin' => $is_admin ) );
			}

			if( isset( $updated2 ) && ! $updated2 ) {
				$response[ 'status' ]	= 'failed';
				$response[ 'error' ]	= 'update_booking_group_state';
				$response[ 'message' ]	= __( 'An error occurs while trying to update booking group state. Please try again later.', BOOKACTI_PLUGIN_NAME );
			}
		}
		
		return apply_filters( 'bookacti_update_booking_group_quantity', $response, $booking_group_id, $quantity, $add_quantity, $context );
	}


	/**
	 * Check if the booking group has expired
	 * 
	 * @since 1.1.0
	 * 
	 * @param type $booking_group_id
	 * @return boolean
	 */
	function bookacti_is_expired_booking_group( $booking_group_id ) {

		$booking_ids = bookacti_get_booking_group_bookings_ids( $booking_group_id );

		$is_expired = false;
		foreach( $booking_ids as $booking_id ) {
			$is_booking_expired = bookacti_is_expired_booking( $booking_id );

			// If one booking of the group is expired, consider the whole group has expired
			if( $is_booking_expired ) {
				$is_expired = true;
				break;
			}
		}

		return $is_expired;
	}
	
	
	/**
	 * Get new booking expiration date
	 * 
	 * @since 1.2.0
	 * @param int $booking_id Booking (group) ID
	 * @param string $booking_type 'single' or 'group'
	 * @param int $quantity Quantity added to cart
	 * @return string
	 */
	function bookacti_get_new_booking_expiration_date( $booking_id, $booking_type, $quantity ) {
		// Retrieve user params about expiration
		global $woocommerce;
		$is_per_product_expiration	= bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_per_product' );
		$reset_timeout_on_change	= bookacti_get_setting_value( 'bookacti_cart_settings', 'reset_cart_timeout_on_change' );
		$timeout					= bookacti_get_setting_value( 'bookacti_cart_settings', 'cart_timeout' );

		// Compute expiration datetime
		$expiration_date = date( 'c', strtotime( '+' . $timeout . ' minutes' ) );

		// If all cart item expire at once, set cart expiration date
		if( ! $is_per_product_expiration ) {

			$cart_expiration_date = bookacti_get_cart_timeout();

			if(	! $reset_timeout_on_change 
			&&  ! empty( $cart_expiration_date ) 
			&&  strtotime( $cart_expiration_date ) > time()
			&&  $woocommerce->cart->get_cart_contents_count() !== $quantity ) {

				$expiration_date = $cart_expiration_date;
			}
		}

		// Change added to cart product expiration date
		// if it doesn't have one, 
		// if the old one is expired (that is to say the product is not in cart anymore), or 
		// if admin set to reset expiration on cart change

		// Single event
		if( $booking_type === 'single' ) {
			$is_expired					= bookacti_is_expired_booking( $booking_id );
			$current_expiration_date	= bookacti_get_booking_expiration_date( $booking_id );

		// Group of events
		} else if( $booking_type === 'group' ) {
			$is_expired					= bookacti_is_expired_booking_group( $booking_id );
			$current_expiration_date	= bookacti_get_booking_group_expiration_date( $booking_id );
		}


		if( ! $reset_timeout_on_change && ! $is_expired && ! is_null( $current_expiration_date ) ) {
			$expiration_date = $current_expiration_date;
		}
		
		return $expiration_date;
	}
	
	
	/**
	 * Reset expiration dates of all cart items
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * 
	 * @global woocommerce $woocommerce
	 * @param string $expiration_date
	 * @return int|false
	 */
	function bookacti_reset_cart_expiration_dates( $expiration_date ) {
		global $woocommerce;

		$cart_contents = $woocommerce->cart->get_cart();
		$updated = null;
		if( ! empty( $cart_contents ) ) {

			bookacti_set_cart_timeout( $expiration_date );

			$cart_keys = array_keys( $cart_contents );

			$booking_id_array = array();
			foreach ( $cart_keys as $key ) {
				// Single event
				if( isset( $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'] ) && ! empty( $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'] ) ) {
					array_push( $booking_id_array, $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'] );

				// Group of events
				} else if( isset( $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'] ) && ! empty( $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'] ) ) {
					// Add the group booking ids to the bookings array to change state
					$booking_group_id	= $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'];
					$booking_ids		= bookacti_get_booking_group_bookings_ids( $booking_group_id );
					$booking_id_array	= array_merge( $booking_id_array, $booking_ids );
				}
			}

			$user_id = $woocommerce->session->get_customer_id();
			if( is_user_logged_in() ) { $user_id = get_current_user_id(); }

			$updated = bookacti_update_in_cart_bookings_expiration_date( $user_id, $booking_id_array, $expiration_date );

		} else {

			bookacti_set_cart_timeout( null );
		}

		return $updated;
	}


	/**
	 * Get expiration time
	 * 
	 * @global woocommerce $woocommerce
	 * @param string $date_format
	 * @return string
	 */
	function bookacti_get_expiration_time( $date_format = 'c' ) {

		$is_expiration_active = bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_active' );

		if( $is_expiration_active ) {

			$is_per_product_expiration = bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_per_product' );

			if( ! $is_per_product_expiration ) {

				global $woocommerce;

				$expiration_date = bookacti_get_cart_timeout();

				if( is_null ( $expiration_date ) 
				|| strtotime( $expiration_date ) <= time() 
				|| $woocommerce->cart->get_cart_contents_count() === 0 ) {

					$timeout = bookacti_get_setting_value( 'bookacti_cart_settings', 'cart_timeout' );
					$expiration_date = date( $date_format, strtotime( '+' . $timeout . ' minutes' ) );

					bookacti_set_cart_timeout( $expiration_date );
				}

			} else {
				$timeout = bookacti_get_setting_value( 'bookacti_cart_settings', 'cart_timeout' );
				$expiration_date = date( $date_format, strtotime( '+' . $timeout . ' minutes' ) );
			}

		} else {
			$expiration_date = NULL;
		}

		return $expiration_date;
	}


	/**
	 * Get timeout for a cart item
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * 
	 * @global woocommerce $woocommerce
	 * @param type $cart_item_key
	 * @return string
	 */
	function bookacti_get_cart_item_timeout( $cart_item_key ) {

		global $woocommerce;

		$item = $woocommerce->cart->get_cart_item( $cart_item_key );

		// Single event
		if( isset( $item[ '_bookacti_options' ][ 'bookacti_booking_id' ] ) ) {

			$booking_id			= $item[ '_bookacti_options' ][ 'bookacti_booking_id' ];
			$expiration_date	= bookacti_get_booking_expiration_date( $booking_id );
			$state				= bookacti_get_booking_state( $booking_id );

		// group of events
		} else if( isset( $item[ '_bookacti_options' ][ 'bookacti_booking_group_id' ] ) ) {

			$booking_group_id	= $item[ '_bookacti_options' ][ 'bookacti_booking_group_id' ];
			$expiration_date	= bookacti_get_booking_group_expiration_date( $booking_group_id );
			$state				= bookacti_get_booking_group_state( $booking_group_id );

		}

		if( ! isset( $expiration_date ) || empty( $expiration_date ) || ! in_array( $state, array( 'in_cart', 'pending' ), true ) ) {
			return '';
		}

		$is_per_product_expiration	= bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_per_product' );

		$timeout = '<div class="bookacti-cart-item-expires-with-cart"></div>';

		if( $is_per_product_expiration && $state === 'in_cart' ) {

			$timeout = '<div class="bookacti-countdown-container">'
						. '<div class="bookacti-countdown" data-expiration-date="' . esc_attr( $expiration_date ) . '" ></div>'
					. '</div>';

		} else if( $state === 'pending' ) {

			$state_text = __( 'Pending payment', BOOKACTI_PLUGIN_NAME );
			$timeout = '<div class="bookacti-cart-item-state bookacti-cart-item-state-pending">' . esc_html( $state_text ) . '</div>';
		}

		return $timeout;
	}


	/**
	 * Get cart timeout
	 * 
	 * @global woocommerce $woocommerce
	 * @param int $user_id
	 * @return string
	 */
	function bookacti_get_cart_timeout( $user_id = NULL ) {

		global $woocommerce;

		if( is_user_logged_in() || ! is_null( $user_id ) ) {
			if( is_null ( $user_id ) ) { $user_id = get_current_user_id(); }
			$cart_expiration_date	= get_user_meta( $user_id, 'bookacti_expiration_cart', true );
		} else {
			$cart_expiration_date	= $woocommerce->session->get( 'bookacti_expiration_cart' );
		}

		return $cart_expiration_date;
	}


	/**
	 * Set cart timeout
	 * 
	 * @global woocommerce $woocommerce
	 * @param type $expiration_date
	 * @param type $user_id
	 */
	function bookacti_set_cart_timeout( $expiration_date, $user_id = NULL ) {

		global $woocommerce;

		if( is_user_logged_in() || ! is_null( $user_id ) ) {
			if( is_null ( $user_id ) ) { $user_id = get_current_user_id(); }
			update_user_meta( $user_id, 'bookacti_expiration_cart', $expiration_date );
		} else {
			$woocommerce->session->set( 'bookacti_expiration_cart', $expiration_date );
		}
	}
	
	
	/**
	 * Get formatted remaining time before expiration
	 * 
	 * @since 1.2.0
	 * @param string $expiration_date 
	 * @return string
	 */
	function bookacti_get_formatted_time_before_expiration( $expiration_date ) {
		$time = bookacti_seconds_to_explode_time( round( abs( strtotime( $expiration_date ) - time() ) ) );
		$remaining_time = ''; $days_formated = ''; $hours_formated = ''; $minutes_formated = '';

		if( intval( $time['days'] ) > 0 ) { 
			/* translators: %d is a variable number of days */
			$days_formated = sprintf( _n( '%d day', '%d days', $time['days'], BOOKACTI_PLUGIN_NAME ), $time['days'] );
			$remaining_time .= $days_formated;
		}
		if( intval( $time['hours'] ) > 0 ) { 
			/* translators: %d is a variable number of hours */
			$hours_formated = sprintf( _n( '%d hour', '%d hours', $time['hours'], BOOKACTI_PLUGIN_NAME ), $time['hours'] );
			$remaining_time .= ' ' . $hours_formated;
		}
		if( intval( $time['minutes'] ) > 0 ) { 
			/* translators: %d is a variable number of minutes */
			$minutes_formated = sprintf( _n( '%d minute', '%d minutes', $time['minutes'], BOOKACTI_PLUGIN_NAME ), $time['minutes'] );
			$remaining_time .= ' ' . $minutes_formated;
		}
		
		return apply_filters( 'bookacti_formatted_time_before_expiration', $remaining_time, $expiration_date );
	}
	
	
	/**
	 * Change cart item quantity to make them respect the booking restrictions (min booking per user, max...)
	 * 
	 * @since 1.4.0
	 * @global woocommerce $woocommerce
	 * @param int $user_id
	 * @return void|int
	 */
	function bookacti_update_cart_item_quantity_according_to_booking_restrictions( $user_id = 0 ) {
		
		global $woocommerce;
		if( ! $woocommerce ) { return; }
		if( ! $woocommerce->cart ) { return; }
		
		$cart_contents = $woocommerce->cart->get_cart();

		if( ! $cart_contents ) { return; }
		
		$updated_items = 0;
		$cart_keys = array_keys( $cart_contents );
		foreach ( $cart_keys as $key ) {
			if( isset( $cart_contents[$key]['_bookacti_options'] ) ) {
				
				$booking_type	= '';
				$quantity		= $cart_contents[$key]['quantity'];
				$allowed_roles	= array();
				$message		= '';
				
				// Single event
				if( isset( $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'] ) && $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'] ) {
					$booking_type	= 'single';
					$booking_id		= $cart_contents[$key]['_bookacti_options']['bookacti_booking_id'];
					$booking		= bookacti_get_booking_by_id( $booking_id );
					$event			= bookacti_get_event_by_id( $booking->event_id );
					$title			= apply_filters( 'bookacti_translate_text', $event->title );
					$activity_data	= bookacti_get_metadata( 'activity', $event->activity_id );
					$min_quantity	= isset( $activity_data[ 'min_bookings_per_user' ] ) ? intval( $activity_data[ 'min_bookings_per_user' ] ) : 0;
					$max_quantity	= isset( $activity_data[ 'max_bookings_per_user' ] ) ? intval( $activity_data[ 'max_bookings_per_user' ] ) : 0;

					// Check if the user has already booked this event
					$quantity_already_booked = 0;
					if( $min_quantity || $max_quantity ) {
						$filters = bookacti_format_booking_filters( array(
							'event_id'				=> $booking->event_id,
							'event_start'			=> $booking->event_start,
							'event_end'				=> $booking->event_end,
							'user_id'				=> $booking->user_id,
							'active'				=> 1,
							'not_in__booking_id'	=> array( $booking->id )
						) );
						$quantity_already_booked = bookacti_get_number_of_bookings( $filters );
					}
					
					// Check allowed roles
					if( isset( $activity_data[ 'allowed_roles' ] ) && $activity_data[ 'allowed_roles' ] ) {
						$allowed_roles = $activity_data[ 'allowed_roles' ];
					}
					
				// Group of events
				} else if( isset( $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'] ) && $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'] ) {
					$booking_type		= 'group';
					$booking_group_id	= $cart_contents[$key]['_bookacti_options']['bookacti_booking_group_id'];
					$booking_group		= bookacti_get_booking_group_by_id( $booking_group_id );
					$event_group		= bookacti_get_group_of_events( $booking_group->event_group_id );
					$title				= apply_filters( 'bookacti_translate_text', $event_group->title );
					$category_data		= bookacti_get_metadata( 'group_category', $event_group->category_id );
					$min_quantity		= isset( $category_data[ 'min_bookings_per_user' ] ) ? intval( $category_data[ 'min_bookings_per_user' ] ) : 0;
					$max_quantity		= isset( $category_data[ 'max_bookings_per_user' ] ) ? intval( $category_data[ 'max_bookings_per_user' ] ) : 0;
			
					// Check if the user has already booked this event
					$quantity_already_booked = 0;
					if( $min_quantity || $max_quantity ) {
						$filters = bookacti_format_booking_filters( array(
							'event_group_id'			=> $booking_group->event_group_id,
							'user_id'					=> $booking_group->user_id,
							'active'					=> 1,
							'not_in__booking_group_id'	=> array( $booking_group->id ),
							'group_by'					=> 'booking_group'
						) );
						$quantity_already_booked = bookacti_get_number_of_bookings( $filters );
					}
					
					// Check allowed roles
					if( isset( $category_data[ 'allowed_roles' ] ) && $category_data[ 'allowed_roles' ] ) {
						$allowed_roles = $category_data[ 'allowed_roles' ];
					}
				}
				
				// Check if the quantity has to be changed
				$restricted_quantity = false;
				
				if( $min_quantity !== 0 && ( $quantity + $quantity_already_booked ) < $min_quantity ) { 
					$restricted_quantity = $min_quantity - $quantity_already_booked;
					
					/* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the minimum number of reservations required per user is %1$s.' and 'The quantity has been automatically increased to %1$s.' */
					$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $quantity, BOOKACTI_PLUGIN_NAME ), $quantity, $title );
					if( $quantity_already_booked ) {
						/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s bookings of "%2$s"' and followed by 'The quantity has been automatically increased to %1$s.' */
						$message .= ' ' . sprintf( _n( 'and you have already booked %1$s place, but the minimum number of reservations required per user is %2$s.', 'and you have already booked %1$s places, but the minimum number of reservations required per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $min_quantity );
					} else {
						/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s bookings of "%2$s"' and followed by 'The quantity has been automatically increased to %1$s.' */
						$message .= ' ' . sprintf( __( 'but the minimum number of reservations required per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $min_quantity );
					}
					/* translators: %1$s is a variable number of bookings. This sentence is preceded by two others : 'You want to make %1$s bookings of "%2$s"' and 'but the minimum number of reservations required per user is %1$s.' */
					$message .= ' ' . sprintf( __( 'The quantity has been automatically increased to %1$s.', BOOKACTI_PLUGIN_NAME ), $restricted_quantity );
				}
				
				if( $max_quantity !== 0 && $quantity > ( $max_quantity - $quantity_already_booked ) ) { 
					$restricted_quantity = $max_quantity - $quantity_already_booked;
					
					/* translators: %1$s is a variable number of bookings, %2$s is the event title. This sentence is followed by two others : 'but the maximum number of reservations allowed per user is %1$s.' and 'The quantity has been automatically decreased to %1$s.' */
					$message = sprintf( _n( 'You want to make %1$s booking of "%2$s"', 'You want to make %1$s bookings of "%2$s"', $quantity, BOOKACTI_PLUGIN_NAME ), $quantity, $title );
					if( $quantity_already_booked ) {
						/* translators: %1$s and %2$s are variable numbers of bookings, always >= 1. This sentence is preceded by : 'You want to make %1$s bookings of "%2$s"' and followed by 'The quantity has been automatically decreased to %1$s.' */
						$message .= ' ' . sprintf( _n( 'but you have already booked %1$s place and the maximum number of reservations allowed per user is %2$s.', 'but you have already booked %1$s places and the maximum number of reservations allowed per user is %2$s.', $quantity_already_booked, BOOKACTI_PLUGIN_NAME ), $quantity_already_booked, $max_quantity );
					} else {
						/* translators: %1$s is a variable number of bookings. This sentence is preceded by : 'You want to make %1$s bookings of "%2$s"' and followed by 'The quantity has been automatically decreased to %1$s.' */
						$message .= ' ' . sprintf( __( 'but the maximum number of reservations allowed per user is %1$s.', BOOKACTI_PLUGIN_NAME ), $max_quantity );
					}
					/* translators: %1$s is a variable quantity of bookings. This sentence is preceded by two others : 'You want to make %1$s bookings of "%2$s"' and 'but the maximum number of reservations allowed per user is %1$s.' */
					$message .= ' ' . sprintf( __( 'The quantity has been automatically decreased to %1$s.', BOOKACTI_PLUGIN_NAME ), $restricted_quantity );
				}
				
				// Check if the product has to be removed
				if( $allowed_roles && ! apply_filters( 'bookacti_bypass_roles_check', false ) ) {
					$current_user	= $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
					$roles			= $current_user->roles;
					$is_allowed		= array_intersect( $roles, $allowed_roles );
					if( ! $is_allowed ) { 
						$restricted_quantity = 0;
						/* translators: %1$s is the event title. This sentence is followed by: 'This event has been automatically removed from your cart.' */
						$message = ' ' . sprintf( __( 'The event "%1$s" is not available in your user category.', BOOKACTI_PLUGIN_NAME ), $title );
						$message .= ' ' . __( 'This event has been automatically removed from your cart.', BOOKACTI_PLUGIN_NAME );
					}
				}
				
				// Change the quantity if necessary and notify the user
				if( $restricted_quantity !== false ) {
					$updated = $woocommerce->cart->set_quantity( $key, $restricted_quantity, true );
					if( $updated ) {
						if( $booking_type === 'single' ) {
							bookacti_controller_update_booking_quantity( $booking_id, $restricted_quantity );
						} else if( $booking_type === 'group' ) {
							bookacti_controller_update_booking_group_quantity( $booking_group_id, $restricted_quantity );
						}
						if( $message && ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); }
						++$updated_items;
					}
				}
			}
		}
		
		return $updated_items;
	}




// ORDERS
	
	/**
	 * Turn all bookings of an order to the desired status. 
	 * Also make sure that bookings are bound to the order and the associated user.
	 * 
	 * @version 1.5.8
	 * @param WC_Order $order
	 * @param string $state
	 * @param string $payment_status
	 * @param boolean $alert_if_fails
	 * @param array $args
	 * @return int|false
	 */
	function bookacti_turn_order_bookings_to( $order, $state = 'booked', $payment_status = NULL, $alert_if_fails = false, $args = array() ) {

		// Retrieve order data
		if( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}		
		
		if( ! $order ) { return false; }
		
		$states_in = ! empty( $args[ 'states_in' ] ) ? $args[ 'states_in' ] : array();
		
		// Retrieve bought items and user id
		$items		= $order->get_items();
		$user_id	= $order->get_user_id();
		if( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
			$order_id	= $order->get_id();
		} else {
			$order_id	= $order->id;
		}
		
		// Create an array with order booking ids
		$booking_id_array		= array();
		$booking_group_id_array = array();
		foreach( $items as $key => $item ) {
			// Reset item booking id
			$booking_id = 0;
			$booking_group_id = 0;
			
			if( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
				if( ! empty( $item[ 'bookacti_booking_id' ] )  ) {
					$booking_id = $item[ 'bookacti_booking_id' ];
				} else if( ! empty( $item[ 'bookacti_booking_group_id' ] ) ) {
					$booking_group_id = $item[ 'bookacti_booking_group_id' ];
				}
			} else {
				if( ! empty( $item[ 'item_meta' ][ 'bookacti_booking_id' ] ) ) {
					$booking_id = $item[ 'item_meta' ][ 'bookacti_booking_id' ][ 0 ];
				} else if( ! empty( $item[ 'item_meta' ][ 'bookacti_booking_group_id' ] ) ) {
					$booking_group_id = $item[ 'item_meta' ][ 'bookacti_booking_group_id' ][ 0 ];
				}
			}
			
			// Single event
			if( ! empty( $booking_id ) ) {				
				// Add the booking id to the bookings array to change state
				array_push( $booking_id_array, $booking_id );

			// Group of events
			} else if( ! empty( $booking_group_id ) ) {
				// Add the group booking ids to the bookings array to change state
				$booking_group_id_array[] = $booking_group_id;
				
				$booking_ids		= bookacti_get_booking_group_bookings_ids( $booking_group_id );
				$booking_id_array	= array_merge( $booking_id_array, $booking_ids );
				
				// Change the booking group state accordingly
				// Also change its user_id and order_id to make sure it is up to date
				bookacti_update_booking_group( $booking_group_id, $state, $payment_status, $user_id, $order_id, NULL, 'auto', $states_in );
			}
		}
		
		$response = array();
		$response[ 'booking_ids' ]			= $booking_id_array;
		$response[ 'booking_group_ids' ]	= $booking_group_id_array;
		
		// If no bookings return error
		if( ! $booking_id_array ) {
			$response[ 'status' ]	= 'failed';
			$response[ 'errors' ][]	= 'no_bookings';
			$response[ 'updated' ]	= 0;
			return $response;
		}
		
		$updated = bookacti_change_order_bookings_state( $user_id, $order_id, $booking_id_array, $state, $payment_status, $states_in );

		$response[ 'status' ]		= 'success';
		$response[ 'errors' ]		= array();
		$response[ 'updated' ]		= $updated;
		
		// Check if an error occured during booking state update
		if( $updated === false ) {
			$response[ 'status' ] = 'failed';
			array_push( $response[ 'errors' ], 'update_failed' );
		}
		
		// If bookings have not updated correctly, send an e-mail to alert the administrator
		if( $alert_if_fails && $response[ 'status' ] !== 'success' ) {

			$errors_list = '<ul>';
			foreach( $response[ 'errors' ] as $error ) {
				if( $error === 'invalid_user_id' )		{ $errors_list .= '<li>' . esc_html__( 'Invalid user ID.', BOOKACTI_PLUGIN_NAME ) . '</li>'; }
				if( $error === 'invalid_order_id' )		{ $errors_list .= '<li>' . esc_html__( 'Invalid order ID.', BOOKACTI_PLUGIN_NAME ) . '</li>'; }
				if( $error === 'update_failed' )		{ $errors_list .= '<li>' . esc_html__( 'Database failed to update.', BOOKACTI_PLUGIN_NAME ) . '</li>'; }
				if( $error === 'no_booking_ids' )		{ $errors_list .= '<li>' . esc_html__( "The order doesn't contains any booking IDs.", BOOKACTI_PLUGIN_NAME ) . '</li>'; }
			}
			$errors_list .= '</ul>';

			$booking_ids	= implode( ', ', $booking_id_array );

			$to				= array( get_option( 'woocommerce_stock_email_recipient' ), get_option( 'admin_email' ) );

			/* translators: %1$s stands for booking ids and %2$s stands for the order id */
			$subject		= '/!\\ ' . sprintf( _n( 'Booking %1$s has not been correctly validated for order %2$s', 'Bookings %1$s have not been correctly validated for order %2$s.', count( $booking_id_array ), BOOKACTI_PLUGIN_NAME ), $booking_ids, $order_id );

			/* translators: %1$s stands for booking ids and %2$s stands for the order id */
			$message		= esc_html( sprintf( _n( 'Booking %1$s has not been correctly validated for order %2$s', 'Bookings %1$s have not been correctly validated for order %2$s.', count( $booking_id_array ), BOOKACTI_PLUGIN_NAME ), $booking_ids, $order_id ) )
							. ' ' . esc_html__( 'Here is the errors list:', BOOKACTI_PLUGIN_NAME );
			$message	   .= '<br/>' . $errors_list;
			$message	   .= '<br/>' . esc_html__( 'Please verify the order and its bookings, and validate bookings manually if necessary.', BOOKACTI_PLUGIN_NAME );

			$headers		= array( 'Content-Type: text/html; charset=UTF-8' );

			wp_mail( $to, $subject, $message, $headers );
		}
		
		if( is_numeric( $response[ 'updated' ] ) && intval( $response[ 'updated' ] ) > 0 ) {
			do_action( 'bookacti_order_bookings_state_changed', $order, $state, $args );
		}
		
		return $response;
	}
	
	
	/**
	 * Update order item booking status meta to new status
	 *
	 * @since 1.2.0
	 * @version 1.5.6
	 * 
	 * @param int $item
	 * @param string $new_state
	 * @param WC_Order $order
	 * @param array $args
	 */
	function bookacti_update_order_item_booking_status( $item, $new_state, $order, $args ) {
		
		if( ! $item || ( ! isset( $item[ 'bookacti_booking_id' ] ) && ! isset( $item[ 'bookacti_booking_group_id' ] ) ) ) { return; }
		
		// WOOCOMMERCE 3.0.0 backward compatibility 
		$order_item_id = is_array( $item ) ? $item[ 'id' ] : $item->get_id();
		
		// Get old state
		$old_state = wc_get_order_item_meta( $order_item_id, 'bookacti_state', true );
		$states_in = ! empty( $args[ 'states_in' ] ) ? $args[ 'states_in' ] : array();
		
		if( $states_in ) {
			if( ! in_array( $old_state, $states_in, true ) ) { return; }
		}
		
		// Turn meta state to new state
		wc_update_order_item_meta( $order_item_id, 'bookacti_state', $new_state );
		
		// Add refund metadata
		if( in_array( $new_state, array( 'refunded', 'refund_requested' ), true ) ) {
			$refund_action = $args[ 'refund_action' ] ? $args[ 'refund_action' ] : 'manual';
			wc_update_order_item_meta( $order_item_id, '_bookacti_refund_method', $refund_action );
		}
		
		if( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		if( ! $order ) { return; }
		
		// Log booking state change
		if( $old_state !== $new_state ) {
			
			// Single event
			if( isset( $item[ 'bookacti_booking_id' ] ) && $item[ 'bookacti_booking_id' ] ) {				
				$booking_id		= $item[ 'bookacti_booking_id' ];
				$booking_owner	= bookacti_get_booking_owner( $booking_id );
				/* translators: %1$s is booking id, %2$s is old state, %3$s is new state */
				$message		= __( 'Booking #%1$s state has been updated from %2$s to %3$s.', BOOKACTI_PLUGIN_NAME );
			// Group of events
			} else if( isset( $item[ 'bookacti_booking_group_id' ] ) && $item[ 'bookacti_booking_group_id' ] ) {
				$booking_id		= $item[ 'bookacti_booking_group_id' ];
				$booking_owner	= bookacti_get_booking_group_owner( $booking_id );
				/* translators: %1$s is booking group id, %2$s is old state, %3$s is new state */
				$message		= __( 'Booking group #%1$s state has been updated from %2$s to %3$s.', BOOKACTI_PLUGIN_NAME );
			}
			
			$status_labels		= bookacti_get_booking_state_labels();
			$is_customer_action	= get_current_user_id() == $booking_owner;		
			
			if( $order ) { 
				$order->add_order_note( 
					sprintf( $message, 
							$booking_id, 
							$status_labels[ $old_state ][ 'label' ], 
							$status_labels[ $new_state ][ 'label' ] ), 
					0, 
					$is_customer_action );
			}
		}
		
		// Turn the order state if it is composed of inactive / pending / booked bookings only
		if( ! isset( $args[ 'update_order_status' ] ) || $args[ 'update_order_status' ] ) {
			bookacti_change_order_state_based_on_its_bookings_state( $order->get_id() );
		}
	}
	
	
	/**
	 * Turn the order state if it is composed of inactive / pending / booked bookings only
	 * 
	 * @since 1.1.0
	 * @version 1.5.6
	 * 
	 * @param int $order_id
	 */
	function bookacti_change_order_state_based_on_its_bookings_state( $order_id ) {
		
		// Get a fresh instance of WC_Order because some of its items may have changed
		$order = wc_get_order( $order_id );
		
		if( ! $order ) { return; }
		
		if( ! in_array( $order->get_status(), array( 'processing', 'on-hold', 'completed' ), true ) ) { return; }
		
		$items = $order->get_items();
		
		if( ! $items ) { return; }
		
		// Get items booking states and
		// Determine if the order is only composed of activities
		$states = array();
		$only_activities = true;
		foreach( $items as $item ) {
			if( ! isset( $item[ 'bookacti_booking_id' ] ) && ! isset( $item[ 'bookacti_booking_group_id' ] ) ) { 
				$only_activities = false; 
				break;
			}
			$states[] = $item[ 'bookacti_state' ];
		}
		
		if( ! $only_activities || empty( $states ) || in_array( 'in_cart', $states, true ) ) {
			return;	
		}
		
		$new_order_status = 'cancelled';
		
		if( in_array( 'pending', $states, true ) ) {
			// Turn order status to processing (or let it on on-hold)
			$new_order_status = $order->get_status() === 'on-hold' ? 'on-hold' : 'processing';
		} else if( in_array( 'booked', $states, true ) ) {
			// Turn order status to completed
			$new_order_status = 'completed';
		} else if( in_array( 'refunded', $states, true ) && ! in_array( 'refund_requested', $states, true ) ) {
			// Turn order status to refunded
			$new_order_status = 'refunded';
		}
		
		$new_order_status = apply_filters( 'bookacti_woocommerce_order_status_automatically_updated', $new_order_status, $order );
		
		if( $new_order_status !== $order->get_status() ) {
			$order->update_status( $new_order_status );
		}
	}
	
	
	/**
	 * Get woocommerce order item id by booking id
	 * @version 1.6.0
	 * @param int|object $booking_id
	 * @return WC_Order_Item|array|false
	 */
	function bookacti_get_order_item_by_booking_id( $booking_id ) {
		
		if( ! $booking_id ) { return false; }
		
		if( is_object( $booking_id ) && ! empty( $booking_id->id ) ) {
			$booking = $booking_id;
			$booking_id = $booking->id;
			$order_id = $booking->order_id;
		} else {
			$order_id = bookacti_get_booking_order_id( $booking_id );
		}

		if( ! $order_id ) { return false; }

		$order = wc_get_order( $order_id );

		if( empty( $order ) ) { return false; }

		$order_items = $order->get_items();

		$item = array();
		foreach( $order_items as $order_item_id => $order_item ) {
			$is_in_item = false;
			// Check if the item is bound to a the desired booking
			if( isset( $order_item[ 'bookacti_booking_id' ] ) && $order_item[ 'bookacti_booking_id' ] == $booking_id ) {
				$is_in_item = true;

			// Check if the item is bound to a group of bookings
			} else if( isset( $order_item[ 'bookacti_booking_group_id' ] ) ) {
				$booking_group_id = $order_item[ 'bookacti_booking_group_id' ];
				$booking_ids = bookacti_get_booking_group_bookings_ids( $booking_group_id );
				// Check if the desired booking is in the group
				if( in_array( $booking_id, $booking_ids ) ) {
					$is_in_item = true;
				}
			}

			if( $is_in_item ) {
				$item = $order_items[ $order_item_id ];
				if( is_array( $item ) ) {
					$item[ 'id' ]		= $order_item_id;
					$item[ 'order_id' ]	= $order_id;
				}
			}
		}

		return $item;
	}

	
	/**
	 * Get woocommerce order item id by booking group id
	 * @since 1.1.0
	 * @version 1.6.0
	 * @param int|object $booking_group_id
	 * @return array|false
	 */
	function bookacti_get_order_item_by_booking_group_id( $booking_group_id ) {

		if( ! $booking_group_id ) { return false; }
		
		if( is_object( $booking_group_id ) && ! empty( $booking_group_id->id ) ) {
			$booking_group = $booking_group_id;
			$order_id = $booking_group->order_id;
		} else {
			$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
		}
		
		if( ! $order_id ) { return false; }

		$order = wc_get_order( $order_id );

		if( empty( $order ) ) { return false; }

		$order_items = $order->get_items();

		$item = array();
		foreach( $order_items as $order_item_id => $order_item ) {
			// Check if the item is bound to a the desired booking
			if( ! isset( $order_item[ 'bookacti_booking_group_id' ] ) || $order_item[ 'bookacti_booking_group_id' ] != $booking_group_id ) { continue; }

			$item = $order_items[ $order_item_id ];
			if( is_array( $item ) ) {
				$item[ 'id' ]		= $order_item_id;
				$item[ 'order_id' ]	= $order_id;
			}
		}

		return $item;
	}
	
	
	/**
	 * Get booking actions according to its order status
	 * @since 1.6.0 (replace bookacti_display_actions_buttons_on_items)
	 * @param array $booking_actions
	 * @param int $order_id
	 * @return array
	 */
	function bookacti_wc_booking_actions_per_order_id( $booking_actions, $order_id ) {
		
		if( ! $order_id || ! is_numeric( $order_id ) ) {
			return $booking_actions;
		}
		
		$order = wc_get_order( $order_id );

		// Check view order
		if( empty( $order ) ) {
			if( isset( $booking_actions['view-order'] ) ) { unset( $booking_actions['view-order'] ); } 
			return $booking_actions;
		} else {
			if( isset( $booking_actions['view-order'] ) ) { $booking_actions[ 'view-order' ][ 'link' ] = get_edit_post_link( $order_id ); }
		}

		// Check cancel / reschedule
		if( ! current_user_can( 'bookacti_edit_bookings' ) && $order->get_status() === 'pending' )	{ 
			if( isset( $booking_actions['cancel'] ) )		{ unset( $booking_actions['cancel'] ); } 
			if( isset( $booking_actions['reschedule'] ) )	{ unset( $booking_actions['reschedule'] ); }
		}
		
		return $booking_actions;
	}

	
	/**
	 * Update order bookings if a partial refund is perfomed (refund of one or more items)
	 * @since 1.2.0 (was part of bookacti_update_booking_when_order_item_is_refunded before)
	 * @version 1.5.4
	 * @param array $refunded_items
	 * @param int $refund_id
	 */
	function bookacti_update_order_bookings_on_items_refund( $refunded_items, $refund_id ) {
		
		if( ! $refunded_items ) { return; }
		
		// Add refunds of the same bookings to calculate the new quantity
		$init_qty = array();
		$new_qty = array();
		$booking_groups = array();
		foreach( $refunded_items as $item_id => $refunded_item ) {

			$refunded_qty = intval( $refunded_item[ 'qty' ] );

			if( $refunded_qty <= 0 ) {
				continue;
			}

			$booking_id			= wc_get_order_item_meta( $item_id, 'bookacti_booking_id', true );
			$booking_group_id	= wc_get_order_item_meta( $item_id, 'bookacti_booking_group_id', true );

			// Single booking
			if( $booking_id ) {

				$booking = bookacti_get_booking_by_id( $booking_id );
				if( $booking ) {
					$init_qty[ $booking->id ]= $booking->quantity;
					$new_qty[ $booking->id ][ 'new_qty' ]		= $init_qty[ $booking->id ] - $refunded_qty;
					$new_qty[ $booking->id ][ 'booking_type' ]	= 'single';
				}
				
			// Booking group
			} else if( $booking_group_id ) {

				$bookings = bookacti_get_bookings_by_booking_group_id( $booking_group_id );

				foreach( $bookings as $booking ) {
					$init_qty[ $booking->id ]= $booking->quantity;
					$new_qty[ $booking->id ][ 'new_qty' ]		= $init_qty[ $booking->id ] - $refunded_qty;
					$new_qty[ $booking->id ][ 'booking_type' ]	= 'group';
				}

				$booking_groups[] = $booking_group_id;
			}
		}


		// Set the new quantity or mark the booking as refunded
		foreach( $new_qty as $booking_id => $refund ) {
			if( $refund[ 'new_qty' ] > 0 ) {

				// Update quantity by substracting the refunded quantity
				$response = bookacti_controller_update_booking_quantity( $booking_id, $refund[ 'new_qty' ], 'admin' );

				// If something went wrong, delete the refund and die
				if( ! in_array( $response[ 'status' ], array( 'success', 'no_change' ), true ) ) {
					bookacti_delete_refund_and_die( $refund_id );
				}

			} else {

				// Update state to refunded
				$updated1 = bookacti_update_booking_state( $booking_id, 'refunded' );
				if( $updated1 && $refund[ 'booking_type' ] === 'single' ) {
					do_action( 'bookacti_booking_state_changed', $booking_id, 'refunded', array( 'is_admin' => true, 'refund_action' => 'manual', 'send_notifications' => false ) );
				}

				// Set the quantity back to the old value
				$updated2 = bookacti_force_update_booking_quantity( $booking_id, $init_qty[ $booking_id ] );

				// If something went wrong, delete the refund and die
				if( $updated1 === false || $updated2 === false ) {
					bookacti_delete_refund_and_die( $refund_id );
				} 
			}

			if( $refund[ 'booking_type' ] === 'single' ) {
				// Update refunds ids array bound to the booking
				$refunds = bookacti_get_metadata( 'booking', $booking_id, 'refunds', true );
				$refunds = is_array( $refunds ) ? $refunds : array();
				$refunds[] = $refund_id;
				bookacti_update_metadata( 'booking', $booking_id, array( 'refunds' => $refunds ) );
			}
		}


		// Update booking group state
		foreach( $booking_groups as $booking_group_id ) {

			$booking_group_old_qty = bookacti_get_booking_group_quantity( $booking_group_id );
			$booking_group_new_qty = $booking_group_old_qty - $refunded_qty;

			// If the group will be totally refunded
			if( $booking_group_new_qty <= 0 ) {

				$updated_group = bookacti_update_booking_group_state( $booking_group_id, 'refunded' );

				if( $updated_group ) {
					do_action( 'bookacti_booking_group_state_changed', $booking_group_id, 'refunded', array( 'is_admin' => true, 'refund_action' => 'manual', 'send_notifications' => false ) );
				}
			}

			// Update refunds ids array bound to the booking group
			$group_refunds = bookacti_get_metadata( 'booking_group', $booking_group_id, 'refunds', true );
			$group_refunds = is_array( $group_refunds ) ? $group_refunds : array();
			$group_refunds[] = $refund_id;
			bookacti_update_metadata( 'booking_group', $booking_group_id, array( 'refunds' => $group_refunds ) );
		}
	}
	
	
	/**
	 * Update order bookings if a total refund is perfomed (refund of the whole order)
	 * 
	 * @since 1.2.0 (was part of bookacti_update_booking_when_order_item_is_refunded before)
	 * @param int $order_id
	 * @param int $refund_id
	 */
	function bookacti_update_order_bookings_on_order_refund( $order_id, $refund_id ) {
		// Double check that the refund is total
		
		$order				= wc_get_order( $order_id );
		$is_total_refund	= floatval( $order->get_total() ) == floatval( $order->get_total_refunded() );

		if( ! $is_total_refund ) { return; }
		
		$items = $order->get_items();
		foreach( $items as $item_id => $item ) {
			$booking_id			= wc_get_order_item_meta( $item_id, 'bookacti_booking_id', true );
			$booking_group_id	= wc_get_order_item_meta( $item_id, 'bookacti_booking_group_id', true );

			// Single booking
			if( $booking_id ) {

				// Update booking state to 'refunded'
				bookacti_update_booking_state( $booking_id, 'refunded' );

				// Update refunds ids array bound to the booking
				$refunds = bookacti_get_metadata( 'booking', $booking_id, 'refunds', true );
				$refunds = is_array( $refunds ) ? $refunds : array();
				$refunds[] = $refund_id;
				bookacti_update_metadata( 'booking', $booking_id, array( 'refunds' => $refunds ) );

				// Add the refund method and yell the booking state change
				wc_update_order_item_meta( $item_id, '_bookacti_refund_method', 'manual' );

				do_action( 'bookacti_booking_state_changed', $booking_id, 'refunded', array( 'is_admin' => true, 'refund_action' => 'manual', 'send_notifications' => false ) );

			// Booking group
			} else if( $booking_group_id ) {

				// Update bookings states to 'refunded'
				bookacti_update_booking_group_state( $booking_group_id, 'refunded', 'auto', true );

				// Update refunds ids array bound to the booking
				$refunds = bookacti_get_metadata( 'booking_group', $booking_group_id, 'refunds', true );
				$refunds = is_array( $refunds ) ? $refunds : array();
				$refunds[] = $refund_id;
				bookacti_update_metadata( 'booking_group', $booking_group_id, array( 'refunds' => $refunds ) );

				// Add the refund method and yell the booking state change
				wc_update_order_item_meta( $item_id, '_bookacti_refund_method', 'manual' );

				do_action( 'bookacti_booking_group_state_changed', $booking_group_id, 'refunded', array( 'is_admin' => true, 'refund_action' => 'manual', 'send_notifications' => false ) );
			}
		}
	}
	
	


// PRODUCT

	/**
	 * Get the product id bound to a booking
	 * 
	 * @since 1.0.4
	 * 
	 * @param type $booking_id
	 * @return int
	 */
	function bookacti_get_booking_product_id( $booking_id ) {

		$item = bookacti_get_order_item_by_booking_id( $booking_id );

		if( empty( $item ) ) { return false; }

		// WOOCOMMERCE 3.0.0 backward compatibility
		if( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
			$product_id = $item->get_product_id();
		} else {
			$order_id = bookacti_get_booking_order_id( $booking_id );

			if( ! $order_id ) { return false; }

			$order = wc_get_order( $order_id );
			
			if( empty( $order ) ) { return false; }
			
			$_product  = $order->get_product_from_item( $item );

			if( empty( $_product ) ) { return false; }

			$product_id = absint( $_product->id );
		}

		return $product_id;
	}
	
	
	/**
	 * Get the product id bound to a booking group
	 * 
	 * @since 1.1.0
	 * 
	 * @param type $booking_group_id
	 * @return int|false
	 */
	function bookacti_get_booking_group_product_id( $booking_group_id ) {

		$item = bookacti_get_order_item_by_booking_group_id( $booking_group_id );
		
		if( empty( $item ) ) { return false; }

		// WOOCOMMERCE 3.0.0 backward compatibility
		if( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
			$product_id = $item->get_product_id();
		} else {
			$order_id = bookacti_get_booking_group_order_id( $booking_group_id );
			
			if( ! $order_id ) { return false; }

			$order = wc_get_order( $order_id );
			
			if( empty( $order ) ) { return false; }
			
			$_product  = $order->get_product_from_item( $item );

			if( empty( $_product ) ) { return false; }

			$product_id = absint( $_product->id );
		}

		return $product_id;
	}
	
	
	/**
	 * Tell if the product is activity or has variations that are activities
	 * @version 1.5.2
	 * @param WC_Product|int $product
	 * @return boolean
	 */
	function bookacti_product_is_activity( $product ) {
		
		// Get product or variation from ID
		if( ! is_object( $product ) ) {
			$product_id	= intval( $product );
			$product	= wc_get_product( $product_id );
			if( ! $product ) { return false; }
		}
		
		$is_activity = false;
		
		if( $product->is_type( 'simple' ) ) {
			$is_activity = get_post_meta( $product->get_id(), '_bookacti_is_activity', true ) === 'yes';
		} 
		
		else if( $product->is_type( 'variation' ) ) {
			$is_activity = get_post_meta( $product->get_id(), 'bookacti_variable_is_activity', true ) === 'yes';
		}
		
		else if( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			foreach( $variations as $variation ) {
				if( empty( $variation[ 'bookacti_is_activity' ] ) ) { continue; }
				$is_activity = $variation[ 'bookacti_is_activity' ];
				break;
			}
		}
		
		if( $is_activity ) { return true; }
		return false;
	}
	
	
	/**
	 * Find matching product variation
	 * @since 1.5.0
	 * @version 1.5.8
	 * @param WC_Product $product
	 * @param array $attributes
	 * @return int Matching variation ID or 0.
	 */
	function bookacti_get_product_variation_matching_attributes( $product, $attributes ) {
		$product_attributes = $product->get_attributes();
		
		// Format attributes array
		foreach( $attributes as $key => $value ) {
			// Take the untranslated value (in case of translated attributes)
			foreach( $product_attributes as $product_attribute_key => $product_attribute ) {
				if( $key !== apply_filters( 'bookacti_translate_text', $product_attribute_key ) 
				&&  $key !== $product_attribute_key ) { continue; }
				$options = version_compare( WC_VERSION, '3.0.0', '>=' ) ? $product_attribute->get_options() : array_map( 'trim', explode( '|', $product_attribute[ 'value' ] ) );
				foreach( $options as $option_value ) {
					if( $value !== apply_filters( 'bookacti_translate_text', $option_value ) 
					&&  $value !== $option_value ) { continue; }
					$value = $option_value;
				}
			}
			
			if( strpos( $key, 'attribute_' ) === 0 && $attributes[ $key ] === $value ) { continue; }
			unset( $attributes[ $key ] );
			$attributes[ sprintf( 'attribute_%s', $key ) ] = $value;
		}
		// Find matching variation
		if( class_exists( 'WC_Data_Store' ) ) {
			$data_store = WC_Data_Store::load( 'product' );
			return $data_store->find_matching_product_variation( $product, $attributes );
		} else {
			return $product->get_matching_variation( $attributes );
		}
	}
	
	
	/**
	 * Get variation default attributes
	 * @since 1.5.0
	 * @param WC_Product $product
	 * @return array
	 */
	function bookacti_get_product_default_attributes( $product ) {
		if( method_exists( $product, 'get_default_attributes' ) ) {
			return $product->get_default_attributes();
		} else {
			return $product->get_variation_default_attributes();
		}
	}


	

// REFUND

	/**
	 * Filter refund actions by order id
	 * 
	 * @since 1.1.0
	 * 
	 * @param array $possible_actions
	 * @param int $order_id
	 * @return type
	 */
	function bookacti_filter_refund_actions_by_order( $possible_actions, $order_id ) {
		if( $order_id ) {
			foreach( $possible_actions as $key => $possible_action ){
				// Allow auto-refund only if gateway allows it
				if( $possible_action['id'] === 'auto' && ! bookacti_does_order_support_auto_refund( $order_id ) ){
					unset( $possible_actions[ $key ] );
				}
			}
		} else {
			// If the booking has not been taken with WooCommerce, remove WooCommerce refund methods
			$woocommerce_actions = bookacti_add_woocommerce_refund_actions( array() );
			foreach( $woocommerce_actions as $woocommerce_action ) {
				unset( $possible_actions[ $woocommerce_action[ 'id' ] ] );
			}
		}

		return $possible_actions;
	}



	/**
	 * Check if an order support auto refund
	 * @version 1.6.0
	 * @param WC_Order|int $order_id
	 * @return boolean
	 */
	function bookacti_does_order_support_auto_refund( $order_id ) {
		
		if( ! $order_id || ! is_numeric( $order_id ) ) { return false; }
		
		$order = wc_get_order( intval( $order_id ) );
		
		if( empty( $order ) ) { return false; }
		
		// WOOCOMMERCE 3.0.0 BW compability
		if( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
			$payment_method = $order->get_payment_method();
		} else {
			$payment_method = $order->payment_method;
		}
		
		$allow_auto_refund = false;
		if ( WC()->payment_gateways() ) {
			$payment_gateways = WC()->payment_gateways->payment_gateways();
		}
		if ( isset( $payment_gateways[ $payment_method ] ) && $payment_gateways[ $payment_method ]->supports( 'refunds' ) ) {
			$allow_auto_refund = true;
		}
		
		return $allow_auto_refund;
	}

	
	/**
	 * Create a coupon to refund a booking
	 * @version 1.5.8
	 * @param int $booking_id
	 * @param string $booking_type Determine if the given id is a booking id or a booking group. Accepted values are 'single' or 'group'.
	 * @param string $refund_message
	 * @return array
	 */
	function bookacti_refund_booking_with_coupon( $booking_id, $booking_type, $refund_message ) {

		// Include & load API classes
		if( ! class_exists( 'WC_API_Coupons' ) ) {
			WC()->api->includes();
			WC()->api->register_resources( new WC_API_Server( '/' ) );
		}

		// Get variables
		if( $booking_type === 'single' ) {
			$user_id	= bookacti_get_booking_owner( $booking_id );
			$item		= bookacti_get_order_item_by_booking_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$user_id	= bookacti_get_booking_group_owner( $booking_id );
			$item		= bookacti_get_order_item_by_booking_group_id( $booking_id );
		}
		
		$user_data		= get_userdata( $user_id );
		
		$amount				= (float) $item[ 'line_total' ] + (float) $item[ 'line_tax' ];
		$user_billing_email = get_user_meta( $user_id, 'billing_email', true );

		// Write code description
		$refund_desc	= __( 'Coupon created as a refund for:', BOOKACTI_PLUGIN_NAME );
		$refund_desc	.= PHP_EOL . __( 'User', BOOKACTI_PLUGIN_NAME )	. ' ' . $user_data->ID . ' (' . $user_data->user_login . ' / ' . $user_data->user_email . ')';
		$refund_desc	.= PHP_EOL . __( 'Order', BOOKACTI_PLUGIN_NAME )	. ' ' . $item[ 'order_id' ];
		
		if( $booking_type === 'single' ) {
			$refund_desc	.= PHP_EOL . __( 'Booking number', BOOKACTI_PLUGIN_NAME )	. ' ' . $booking_id;
		} else if( $booking_type === 'group' ) {
			$refund_desc	.= PHP_EOL . __( 'Booking group number', BOOKACTI_PLUGIN_NAME )	. ' ' . $booking_id;
		}
		
		$refund_desc	.= PHP_EOL . '     ' . $item[ 'name' ];
		
		// Deprecated data
		if( $booking_type === 'single' && isset( $item[ 'bookacti_event_start' ] ) && isset( $item[ 'bookacti_event_end' ] ) ) {
			$refund_desc	.= PHP_EOL . '     ' . bookacti_format_datetime( $item[ 'bookacti_event_start' ] );
			$refund_desc	.= PHP_EOL . '     ' . bookacti_format_datetime( $item[ 'bookacti_event_end' ] );
		}
		
		if( ! empty( $refund_message ) ) {
			$refund_desc .= PHP_EOL . PHP_EOL . __( 'User message:', BOOKACTI_PLUGIN_NAME ) . PHP_EOL . $refund_message;
		}
		
		// Sanitize
		// WP < 4.7 backward compatibility
		if( function_exists( 'sanitize_textarea_field' ) ) {
			$refund_desc = sanitize_textarea_field( stripslashes( $refund_desc ) );
		} else {
			$refund_desc = sanitize_text_field( stripslashes( $refund_desc ) );
		}

		// Coupon data
		$data = array();
		$data['coupon'] = array(
			'type'                         => 'fixed_cart',
			'amount'                       => $amount,
			'individual_use'               => false,
			'product_ids'                  => array(),
			'exclude_product_ids'          => array(),
			'usage_limit'                  => '1',
			'usage_limit_per_user'         => '',
			'limit_usage_to_x_items'       => '',
			'usage_count'                  => '',
			'expiry_date'                  => '',
			'enable_free_shipping'         => false,
			'product_category_ids'         => array(),
			'exclude_product_category_ids' => array(),
			'exclude_sale_items'           => false,
			'minimum_amount'               => '',
			'maximum_amount'               => '',
			'customer_emails'              => array( $user_billing_email ),
			'description'                  => stripslashes( $refund_desc )
		);


		// If coupon already exists, return it
		$order_item_id = is_array( $item ) ? $item[ 'id' ] : $item->get_id();
		$existing_coupon_code = wc_get_order_item_meta( $order_item_id, 'bookacti_refund_coupon', true );
		if( $existing_coupon_code ) {
			$existing_coupon = WC()->api->WC_API_Coupons->get_coupon_by_code( $existing_coupon_code );

			$return_data = array( 'status' => 'success', 'coupon_amount' => wc_price( $existing_coupon['coupon']['amount'] ), 'coupon_code' => $existing_coupon['coupon']['code'], 'new_state' => 'refunded' );
			return $return_data;
		}


		// Grant user cap to create coupon
		$current_user = wp_get_current_user();
		$user_basically_can_publish_shop_coupons		= current_user_can( 'publish_shop_coupons' );
		$user_basically_can_read_private_shop_coupons	= current_user_can( 'read_private_shop_coupons' );
		if( ! $user_basically_can_publish_shop_coupons )		{ $current_user->add_cap( 'publish_shop_coupons' ); }
		if( ! $user_basically_can_read_private_shop_coupons )	{ $current_user->add_cap( 'read_private_shop_coupons' ); }

		// Generate coupon code and create the coupon
		$i = 1;
		$coupon = array();
		$code_template = apply_filters( 'bookacti_refund_coupon_code_template', 'R{user_id}N{refund_number}' );
		$code_template = str_replace( '{user_id}', '%1$s', $code_template );
		$code_template = str_replace( '{refund_number}', '%2$s', $code_template );
		$data['coupon']['code'] = sprintf( $code_template, $user_id, $i );
		
		$data = apply_filters( 'bookacti_refund_coupon_data', $data, $user_data, $item );
		
		do {
			// For the first occurence, try to use the code that may have been changed with 'bookacti_refund_coupon_data' hook
			if( $i !== 1 ) { 
				$data['coupon']['code'] = sprintf( $code_template, $user_id, $i ); 
			}
			$coupon = WC()->api->WC_API_Coupons->create_coupon( $data );
			$i++;
		}
		while( is_wp_error( $coupon ) && $coupon->get_error_code() === 'woocommerce_api_coupon_code_already_exists' );

		if( ! empty( $coupon ) && ! is_wp_error( $coupon ) ) {

			// Bind coupon to order item
			$code = apply_filters( 'bookacti_refund_coupon_code', $coupon[ 'coupon' ][ 'code' ], $data, $coupon, $user_data, $item );
			wc_update_order_item_meta( $order_item_id, 'bookacti_refund_coupon', $code );

			$return_data = array( 'status' => 'success', 'coupon_amount' => wc_price( $data['coupon']['amount'] ), 'coupon_code' => $code, 'new_state' => 'refunded' );

		} else if( is_wp_error( $coupon ) ) {
			$return_data = array( 'status' => 'failed', 'error' => $coupon, 'message' => $coupon->get_error_message() );
		}

		// Remove user cap to create coupon
		if( ! $user_basically_can_publish_shop_coupons )		{ $current_user->remove_cap( 'publish_shop_coupons' );	}
		if( ! $user_basically_can_read_private_shop_coupons )	{ $current_user->remove_cap( 'read_private_shop_coupons' );	}
		
		return $return_data;
	}
	
	
	/**
	 * Auto refund (for supported gateway)
	 * @version 1.5.8
	 * @param int $booking_id
	 * @param string $booking_type Determine if the given id is a booking id or a booking group id. Accepted values are 'single' or 'group'.
	 * @param string $refund_message
	 * @return array
	 */
	function bookacti_auto_refund_booking( $booking_id, $booking_type, $refund_message ) {
		if( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			return bookacti_deprecated_auto_refund_booking( $booking_id, $booking_type, $refund_message );
		}	
		
		// Get variables
		if( $booking_type === 'single' ) {
			$order_id	= bookacti_get_booking_order_id( $booking_id );
			$item		= bookacti_get_order_item_by_booking_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$order_id	= bookacti_get_booking_group_order_id( $booking_id );
			$item		= bookacti_get_order_item_by_booking_group_id( $booking_id );
		}
		
		if( ! $item ) {
			return array( 
				'status'	=> 'failed', 
				'error'		=> 'no_order_item_found',
				'message'	=> esc_html__( 'The order item bound to the booking was not found.', BOOKACTI_PLUGIN_NAME )
			);
		}
		
		$order_item_id = $item->get_id();
		$amount = $item->get_total() + $item->get_total_tax();

		$reason = __( 'Auto refund proceeded by user.', BOOKACTI_PLUGIN_NAME );
		if( $refund_message !== '' ) {
			$reason	.= PHP_EOL . __( 'User message:', BOOKACTI_PLUGIN_NAME ) . PHP_EOL . $refund_message;
		}

		$line_items	= array();
		$line_items[ $order_item_id ] = array(
			'qty'			=> $item->get_quantity(),
			'refund_total'	=> $item->get_total(),
			'refund_tax'	=> $item->get_total_tax()
		);

		$data = array(
			'amount'			=> $amount,
			'reason'			=> $reason,
			'order_id'			=> $order_id,
			'line_items'		=> $line_items,
			'refund_payment'	=> true
		);
		
		$refund = wc_create_refund( $data );
		
		if( is_wp_error( $refund ) ) {
			return array( 'status' => 'failed', 'error' => $refund->get_error_code(), 'message' => $refund->get_error_message() );
		}
		
		return array( 'status' => 'success', 'new_state' => 'refunded', 'refund' => $refund );
	}

	
	/**
	 * Deprecated Auto refund function (for supported gateway)
	 * To be used with WooCommerce < 3.0
	 * @since 1.5.8 (was bookacti_auto_refund_booking)
	 * @param array $data
	 * @return array
	 */
	function bookacti_deprecated_auto_refund_booking( $booking_id, $booking_type, $refund_message ) {
		// Include & load API classes
		if( ! class_exists( 'WC_API_Orders' ) ) {
			WC()->api->includes();
			WC()->api->register_resources( new WC_API_Server( '/' ) );
		}

		// Get variables
		if( $booking_type === 'single' ) {
			$order_id	= bookacti_get_booking_order_id( $booking_id );
			$item		= bookacti_get_order_item_by_booking_id( $booking_id );
		} else if( $booking_type === 'group' ) {
			$order_id	= bookacti_get_booking_group_order_id( $booking_id );
			$item		= bookacti_get_order_item_by_booking_group_id( $booking_id );
		}
		
		if( ! $item ) {
			return array( 
				'status'	=> 'failed', 
				'error'		=> 'no_order_item_found',
				'message'	=> esc_html__( 'The order item bound to the booking was not found.', BOOKACTI_PLUGIN_NAME )
			);
		}
		
		$order_item_id = is_array( $item ) ? $item[ 'id' ] : $item->get_id();
		$amount = (float) $item[ 'line_total' ] + (float) $item[ 'line_tax' ];

		$reason = __( 'Auto refund proceeded by user.', BOOKACTI_PLUGIN_NAME );
		if( $refund_message !== '' ) {
			$reason	.= PHP_EOL . __( 'User message:', BOOKACTI_PLUGIN_NAME ) . PHP_EOL . $refund_message;
		}

		$line_items	= array();
		$line_items[ $order_item_id ] = array(
			'qty'			=> $item[ 'qty' ],
			'refund_total'	=> $item[ 'line_total' ],
			'refund_tax'	=> $item[ 'line_tax' ]
		);

		$data = array();
		$data['order_refund'] = array(
			'amount'     => $amount,
			'reason'     => $reason,
			'order_id'   => $order_id,
			'line_items' => $line_items,
		);

		// Grant user cap to process refund
		$current_user		= wp_get_current_user();
		$user_basically_can = current_user_can( 'publish_shop_orders' );
		if( ! $user_basically_can ) {
			$current_user->add_cap( 'publish_shop_orders' );
		}

		// Process refund
		$refund = WC()->api->WC_API_Orders->create_order_refund( $order_id, $data, true );

		// Remove user cap to create coupon
		if( ! $user_basically_can ) { $current_user->remove_cap( 'publish_shop_orders' ); }

		if( is_wp_error( $refund ) ) {
			// Delete order refund
			$order_refunds = WC()->api->WC_API_Orders->get_order_refunds( $order_id );
			if( ! empty( $order_refunds['order_refunds'] ) ) {
				foreach( $order_refunds['order_refunds'] as $order_refund ) {
					if( $order_refund['line_items'][0]['refunded_item_id'] === $item['id'] ) {
						WC()->api->WC_API_Orders->delete_order_refund( $order_id, $order_refund['id'] );
					}
				}
			}
			return array( 'status' => 'failed', 'error' => $refund->get_error_code(), 'message' => $refund->get_error_message() );
		}
		
		// Trigger notifications and status changes
		$order = wc_get_order( $order_id );
		if ( $order->get_remaining_refund_amount() > 0 || ( $order->has_free_item() && $order->get_remaining_refund_items() > 0 ) ) {
			do_action( 'woocommerce_order_partially_refunded', $order_id, $refund->id, $refund->id );
		} else {
			do_action( 'woocommerce_order_fully_refunded', $order_id, $refund->id );
			$order->update_status( apply_filters( 'woocommerce_order_fully_refunded_status', 'refunded', $order_id, $refund->id ) );
		}

		do_action( 'woocommerce_order_refunded', $order_id, $refund->id );

		return array( 'status' => 'success', 'new_state' => 'refunded' );
	}
	
	
	/**
	 * Delete a refund and die
	 * 
	 * @param type $refund_id
	 */
	function bookacti_delete_refund_and_die( $refund_id ) {
		// Delete the refund
		if ( $refund_id && 'shop_order_refund' === get_post_type( $refund_id ) ) {
			$order_id = wp_get_post_parent_id( $refund_id );
			wc_delete_shop_order_transients( $order_id );
			wp_delete_post( $refund_id );
			do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );
		}

		// Stop the script execution
		$message = __( 'Error occurs while trying to refund a booking.', BOOKACTI_PLUGIN_NAME );
		wp_die( $message );
	}




// FORMS
	/**
	 * Get WC unsupported form fields names
	 * @since 1.5.0
	 * @return array
	 */
	function bookacti_get_wc_unsupported_form_fields() {
		return apply_filters( 'bookacti_wc_unsupported_form_fields', array( 'login', 'quantity', 'submit' ) );
	}




// SETTINGS
	
	function bookacti_settings_section_cart_callback() {}
	
	/**
	 * Setting for: Activate cart expiration
	 */
	function bookacti_settings_field_activate_cart_expiration_callback() {
		
		$is_active = bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_active' );
		
		// Display the field
		$name	= 'bookacti_cart_settings[is_cart_expiration_active]';
		$id		= 'is_cart_expiration_active';
		bookacti_onoffswitch( $name, $is_active, $id );
		
		// Display the tip
		$tip = __( "If cart expiration is off, the booking is made at the end of the checkout process. It means that an event available at the moment you add it to cart can be no longer available at the moment you wish to complete the order. With cart expiration on, the booking is made when it is added to cart and remains temporary until the end of the checkout process.", BOOKACTI_PLUGIN_NAME );
		bookacti_help_tip( $tip );
	}
	
	
	/**
	 * Setting for: Activate per product expiration
	 */
	function bookacti_settings_field_per_product_expiration_callback() {
		
		$is_active = bookacti_get_setting_value( 'bookacti_cart_settings', 'is_cart_expiration_per_product' );
		
		//Display the field
		$name	= 'bookacti_cart_settings[is_cart_expiration_per_product]';
		$id		= 'is_cart_expiration_per_product';
		
		bookacti_onoffswitch( $name, $is_active, $id );
		
		//Display the tip
		$tip = __( "The expiration time will be set for each product independantly, each with their own countdown before being removed from cart.", BOOKACTI_PLUGIN_NAME );
		bookacti_help_tip( $tip );
	}
	
	
	/**
	 * Setting for: Set amount of time before expiration
	 */
	function bookacti_settings_field_cart_timeout_callback() { 
		
		$timeout = bookacti_get_setting_value( 'bookacti_cart_settings', 'cart_timeout' );
		
		//Display the field
		?>
		<input name='bookacti_cart_settings[cart_timeout]' 
			   id='cart_expiration_time' 
			   type='number' 
			   min='1'
			   value='<?php echo $timeout; ?>' />
		<?php
		
		//Display the tip
		$tip = __( 'Define the amount of time a user has before his cart gets empty.', BOOKACTI_PLUGIN_NAME );
		bookacti_help_tip( $tip );
	}

	
	/**
	 * Setting for: Reset the countdown each time a change occur to cart
	 */
	function bookacti_settings_field_reset_cart_timeout_on_change_callback() {
		
		$is_active = bookacti_get_setting_value( 'bookacti_cart_settings', 'reset_cart_timeout_on_change' );
		
		//Display the field
		$name	= 'bookacti_cart_settings[reset_cart_timeout_on_change]';
		$id		= 'reset_cart_timeout_on_change';
		
		bookacti_onoffswitch( $name, $is_active, $id );
		
		//Display the tip
		$tip = __( 'The countdown will be reset each time a product is added, or when a product quantity is changed.', BOOKACTI_PLUGIN_NAME );
		bookacti_help_tip( $tip );
	}




// GENERAL 

	/**
	 * Determines if user is shop manager
	 * 
	 * @param int $user_id
	 * @return boolean
	 */
	function bookacti_is_shop_manager( $user_id = 0 ) {

		if( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		$user = get_userdata( $user_id );
		if ( isset( $user->roles ) && in_array( 'shop_manager', $user->roles, true ) ) {
			return true;
		}
		return false;
	}