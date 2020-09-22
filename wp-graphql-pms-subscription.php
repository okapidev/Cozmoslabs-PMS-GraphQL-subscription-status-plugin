<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP GraphQL PMS Subscription
 * Plugin URI:        https://github.com/m-muhsin/wp-graphql-reading-time
 * Description:       Gives the ability to see if an user has an active subscription for the Cozmoslabs plugin as a GraphQL Field.
 * Version:           0.0.1
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Radu Nitescu
 * Author URI:        https://github.com/Nurckye
 * License:           GNU General Public License v2.0 / MIT License
 * Text Domain:       wp-graphql-pms-subscription
 * Domain Path:       /languages
 */

function graphql_register_pms_subscription() {
	register_graphql_field(
		'User',
		'hasActiveSubscription',
		[
			'type'        => 'Boolean',
			'description' => __( 'Does the current user have an active subscription', 'wp-graphql' ),
			'resolve'     => function( $user ) {
				$is_active = wpgql_rt_check_active_subscription( $user->userId );
				return $is_active;
			},
		]
	);
}
add_action( 'graphql_register_types', 'graphql_register_pms_subscription' );

function wpgql_rt_check_active_subscription( $wpgql_rt_user_id ) {
    global $wpdb;
    $result = $wpdb->get_results ( "SELECT * FROM wp_pms_member_subscriptions WHERE user_id = $wpgql_rt_user_id;" );

    return  $result[0]->status == 'active';
}
