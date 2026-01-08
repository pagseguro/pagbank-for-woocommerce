<?php
/**
 * Settings REST API endpoint.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class SettingsApi.
 */
class SettingsApi {

	/**
	 * Instance.
	 */
	private static ?SettingsApi $instance = null;

	/**
	 * Get instance.
	 */
	public static function get_instance(): SettingsApi {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * SettingsApi constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'pagbank/v1',
			'/connect-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_connect_status' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'environment' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'sandbox', 'production' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'pagbank/v1',
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'admin_permission_check' ),
				'args'                => array(
					'environment' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'sandbox', 'production' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if the user has admin permissions.
	 *
	 * @return bool|WP_Error
	 */
	public function admin_permission_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Você não tem permissão para acessar este recurso.', 'pagbank-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get PagBank connection status.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function get_connect_status( WP_REST_Request $request ): WP_REST_Response {
		$environment = $request->get_param( 'environment' );
		$connect     = new Connect( $environment );
		$data        = $connect->get_data();

		$response_data = array(
			'connected'      => false,
			'account_id'     => null,
			'environment'    => $environment,
			'account'        => null,
			'scopes'         => array(),
			'missing_scopes' => array(),
		);

		if ( $data ) {
			$response_data['connected']  = true;
			$response_data['account_id'] = $data['account_id'] ?? null;

			// Get current scopes and check for missing ones.
			$current_scopes                  = ! empty( $data['scope'] ) ? explode( ' ', $data['scope'] ) : array();
			$response_data['scopes']         = array_values( $current_scopes );
			$response_data['missing_scopes'] = array_values( array_diff( Api::REQUIRED_SCOPES, $current_scopes ) );

			// Use stored account data if available.
			// For older connections without account data, it will remain null.
			if ( ! empty( $data['account'] ) ) {
				$response_data['account'] = $data['account'];
			}
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Disconnect PagBank account.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$environment = $request->get_param( 'environment' );
		$option_key  = 'woocommerce_pagbank_connect_data_' . $environment;

		delete_option( $option_key );

		return new WP_REST_Response(
			array(
				'success'     => true,
				'environment' => $environment,
			),
			200
		);
	}
}
