<?php

add_filter( 'rest_request_before_callbacks', [ __CLASS__, 'rest_pre_dispatch' ], 10, 3 );

public static function rest_pre_dispatch( $response, $handler, $request ) {
	wp_set_current_user( 1 );
	if ( preg_match( '|^/wc/v3/orders/[0-9]+$|', $request->get_route() ) ) {

		error_log( print_r( $request->get_route(), true ) );

		add_filter( 'woocommerce_rest_check_permissions', [ __CLASS__, 'check' ] );
	}
	return $response;

}

public static function check( $permission, $context ) {
	// check signature with using the secret key and nonce
	if ( 'read' === $context ) {
		return true;
	}
}
