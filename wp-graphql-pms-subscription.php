<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WP GraphQL PMS Subscription
 * Plugin URI:        https://github.com/Nurckye/Cozmoslabs-PMS-GraphQL-subscription-status-plugin
 * Description:       Gives the ability to see if an user has an active subscription for the Cozmoslabs plugin as a GraphQL Field.
 * Version:           1.4
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
				$is_active = wpgql_rt_check_active_subscription( $user->userId, $user->roles );
				return $is_active;
			},
		]
	);

	$field_dict = array(
		"subscriptionStartDate" => 'start_date',
		"subscriptionExpirationDate" => 'billing_next_payment',
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

function wpgql_rt_check_active_subscription( $wpgql_rt_user_id, $roles ) {
	global $wpdb;

	if ( in_array("administrator", $roles) ) { 
		return true;
	}

    $result = $wpdb->get_results ( "SELECT * FROM wp_pms_member_subscriptions WHERE user_id = $wpgql_rt_user_id;" );
	if ( count( $result ) == 0 ) {
		return false;
	}

	if ( $result[0]->status == 'canceled' ) { 
		$raw_date = date_parse( $result[0]->billing_next_payment );
		$datetime = new DateTime();
		$merged_date = $raw_date["year"] * 10000 + $raw_date["month"] * 100 + $raw_date["day"];
		$merged_today = intval(date("Y")) * 10000 + intval(date("m")) * 100 + intval(date("d"));

		return $merged_date > $merged_today;
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
	
	$result = $wpdb->get_results ( "
	SELECT wp_pms_member_subscriptions.user_id, wp_pms_member_subscriptionmeta.meta_key, wp_pms_member_subscriptionmeta.meta_value
	FROM wp_pms_member_subscriptions
	INNER JOIN wp_pms_member_subscriptionmeta
	ON wp_pms_member_subscriptions.id=wp_pms_member_subscriptionmeta.member_subscription_id
	WHERE (
			wp_pms_member_subscriptionmeta.meta_key = 'pms_group_subscription_owner' OR
			wp_pms_member_subscriptionmeta.meta_key = 'pms_group_name'
		)
	AND wp_pms_member_subscriptions.user_id=$wpgql_rt_user_id;
");
	if ($result[0]->meta_key == "pms_group_name") {
		if ($result[0]->meta_value == "") return null;
		return $result[0]->meta_value;
	} else if ($result[0]->meta_key == "pms_group_subscription_owner") {
		$subscription_id = $result[0]->meta_value;
		if ($subscription_id == null) return null;

		$result = $wpdb->get_results ( "
	SELECT wp_pms_member_subscriptionmeta.meta_value
	FROM wp_pms_member_subscriptions
	INNER JOIN wp_pms_member_subscriptionmeta
	ON wp_pms_member_subscriptions.id=wp_pms_member_subscriptionmeta.member_subscription_id
	WHERE wp_pms_member_subscriptionmeta.meta_key = 'pms_group_name'
	AND wp_pms_member_subscriptions.id=$subscription_id;
");
		return $result[0]->meta_value;
	}

	return null;
}