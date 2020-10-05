<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP GraphQL PMS Subscription
 * Plugin URI:        https://github.com/Nurckye/Cozmoslabs-PMS-GraphQL-subscription-status-plugin
 * Description:       Gives the ability to see if an user has an active subscription for the Cozmoslabs plugin as a GraphQL Field.
 * Version:           1.1
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

	$field_dict = array(
		"subscriptionStartDate" => 'start_date',
		"subscriptionExpirationDate" => 'expiration_date',
		"billingDuration" => 'billing_duration',
		"billingDurationUnit" => 'billing_duration_unit',
		"status" => 'status'
	);

	foreach ($field_dict as $key => $value) {
		register_graphql_field(
			'User',
			$key,
			[
				'type'        => 'String',
				'description' => __( 'Retrieves the specified field for the user subscription', 'wp-graphql' ),
				'resolve'     =>  curry_resolver($value),
			]
		);
	}

	register_graphql_field(
		'User',
		'planName',
		[
			'type'        => 'String',
			'description' => __( 'Retrieves the subscription plan name for an user', 'wp-graphql' ),
			'resolve'     => function( $user ) {
				$name = get_subscription_name( $user->userId );
				return $name;
			},
		]
	);

	register_graphql_field(
		'User',
		'companyName',
		[
			'type'        => 'String',
			'description' => __( 'Retrieves the company name for a subscribed user', 'wp-graphql' ),
			'resolve'     => function( $user ) {
				$name = get_company_subscription_name( $user->userId );
				return $name;
			},
		]
	);
}

add_action( 'graphql_register_types', 'graphql_register_pms_subscription' );

function curry_resolver($value) {
	return function( $user ) use($value) {
		$field = query_subscription_field( $user->userId, $value );
		return $field;
	};
}

function wpgql_rt_check_active_subscription( $wpgql_rt_user_id ) {
    global $wpdb;
    $result = $wpdb->get_results ( "SELECT * FROM wp_pms_member_subscriptions WHERE user_id = $wpgql_rt_user_id;" );
	if ( count( $result ) == 0 ) {
		return null;
	}
    return  $result[0]->status == 'active';
}

function query_subscription_field( $wpgql_rt_user_id, $field ) {
	global $wpdb;
	$result = $wpdb->get_results ( "SELECT * FROM wp_pms_member_subscriptions WHERE user_id = $wpgql_rt_user_id;" );

    return  $result[0]->{$field};
}

function get_subscription_name( $wpgql_rt_user_id ) {
	global $wpdb;
	$result = $wpdb->get_results ( "SELECT subscription_plan_id FROM wp_pms_member_subscriptions WHERE user_id = $wpgql_rt_user_id;" );
	if ( count( $result ) == 0 ) {
		return null;
	}
	$subscription_plan_id = $result[0]->subscription_plan_id; 
	
	$result = $wpdb->get_results ( "SELECT post_title from wp_posts where id=$subscription_plan_id;" );
	if ( count( $result ) == 0 ) {
		return null;
	}
	return $result[0]->post_title; 
}

function get_company_subscription_name( $wpgql_rt_user_id ) {
	global $wpdb;
	$result = $wpdb->get_results ( "SELECT meta_value FROM wp_usermeta WHERE meta_key='pms_billing_company' AND user_id = $wpgql_rt_user_id;" );
	return $result[0]->meta_value;
}