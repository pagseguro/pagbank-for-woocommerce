<?php
/**
 * The Connect class is responsible for saving and retrieving the Connect data from the database.
 * If the expiration date is past, the data is considered invalid.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Carbon\Carbon;
use Exception;

/**
 * Class Connect.
 */
class Connect {

	/**
	 * The environment.
	 *
	 * @var string production or sandbox.
	 */
	private $environment;

	/**
	 * The connect data key.
	 *
	 * @param string $environment The environment.
	 */
	public function __construct( string $environment ) {
		$this->environment = $environment === 'production' ? 'production' : 'sandbox';
	}

	/**
	 * Get connect applications.
	 *
	 * @param string $environment The environment.
	 *
	 * @return array
	 */
	public static function get_connect_applications( string $environment = null ) {
		$applications = array(
			'fa1553af-5f0c-4ff2-92c3-a0dd8984b6a1' => array(
				'id'           => 'fa1553af-5f0c-4ff2-92c3-a0dd8984b6a1',
				'title'        => 'Aplicação de demonstração',
				'app_name'     => 'WOOCOMMERCE–SANDBOX',
				'access_token' => 'qmoSqvkWfyOeT18k7K/6PZgkeNFiS9xxFK+pWlZWYbV4BqqqVCjCDgq3Gp86iyji8sSYc/pNDK5EQUAYfmUMBlNM5hJm9w6sHXXY4XsQAPnO0qbjYyx1CrudhLQTdePwIhPLvuRhLovUzAsN5gPZCMpon8D2Bt/5Jh57fvHdfBRpKthxMPZub785cAXx9SiOfRJ3SAN2SdHJg94XuSaa+9GQD6mPoQZpLpC/XfW5Ub4bZy2IiHUcHysMCUnfY3W9zXBphPLb7NAWDDhKP9ULFkEYgi+bZIDs+5ZTZdAf5eGAbEO9uCdghZoEMI1vWyFn+H3x0MoGvtqCItFqD+CsGA==',
				'environment'  => 'sandbox',
			),
			'31241905-5426-4f88-a140-4416a2cab404' => array(
				'id'           => '31241905-5426-4f88-a140-4416a2cab404',
				'title'        => 'Recebimento em 14 dias',
				'app_name'     => 'WOOCOMMERCE–D14',
				'access_token' => 'b1GAHR6uf22RrC4KUNB+6eav/3YohEBofInyaIDw9cwVfMolF0HAfP3lS7MEbkcwlYywFbxGEDPTi/k6wjabXjYXaN4hyARRkDKE4hMR4K0BWq0ya96kAnw4tn0xybXt1Nnse5CHUNqOksBlSF+IeJVPrVMSCHzAStbT5VwgwXo2YlFLCfXFRCqca0uhHAUG3es29bPPjjdYY971NSYG+eCMDYCL356sQRbL3fxV0rjNax/A/l5qnTMBAB5Ki5wjB2D1YbIYfktzHEh7HM6OO4r0CG3bMd3/4AngGf3DNShpRogG9p1VZN5hkjxIVrMgUeHAYSvQJOnXFszAmgYjSQ==',
				'environment'  => 'production',
			),
			'c8672afd-abbb-4c47-a95d-7cf9cd4cee76' => array(
				'id'           => 'c8672afd-abbb-4c47-a95d-7cf9cd4cee76',
				'title'        => 'Recebimento em 30 dias',
				'app_name'     => 'WOOCOMMERCE–D30',
				'access_token' => 'g4MGqLlpVGGE+t4C0ReTZQf8URY5D3VWJQySNYYkLnhAw9emaUmxTdxAkIxesgnxrVsK2TfPnGJ0nB43lPEOPHLHPDDoX01117kKwYBDyj7sD3p2yXwRgTdonNcBGvpOlkk3K3mTj6oP6oRNu4IaXR+9X7xtPuQVgOoufZn3CSgHFpmJo9uivoFBG4ntu4pCiYLVG+3c0KhQJxTYVgTFTHeevtbddyALC2DK25Z4Pxz0aGB3PhtV6e7fLiu7uNkaMOEfZ8g3E8hVUmfMJABArXRhgYdjlUogzjh2RLxyJUuLn++ocmF76/hW4zygC2ZNqld10r5d3fb+VzMCeIbzRg==',
				'environment'  => 'production',
			),
			'f2ad0df4-4e52-4cef-97b2-4fcf1405ab9a' => array(
				'id'           => 'f2ad0df4-4e52-4cef-97b2-4fcf1405ab9a',
				'title'        => 'Personalizado',
				'app_name'     => 'WOOCOMMERCE–TAXA-NEGOCIADA',
				'access_token' => 'kVuwzAYnBsCLL2dMBO8gRIhm01rT0mVkznG4Vgw/jNTa2Pey+Ry/EdsionLcDDxmqvOompJp1RzTqq0ZPu2x+z4x5r0R1RjrS4WZp3dY5nNX1kkValMtgDbSyblF+b/LW7Npx7t60Y5AVOvwT8wnoetRxOwjVJp9rrTt9Mk29QSUnU0iKIGfZ+QFrUDmc9Y3y+wivGmzXCBoh1pFyiXb7WZfo7XSJM1CCFtfEJx3P4rheFWsjol1enVVfrMSfopa19z6EhJ0CEb4xKP9rkqyY0VurXgEFwAmBFQLJ3VHMlOSlUs0QCpP/0xe8a96qzlT/wV2Om2ONWwDFjqKRAdOqw==',
				'environment'  => 'production',
			),
		);

		if ( $environment ) {
			return array_filter(
				$applications,
				function ( $item ) use ( $environment ) {
					return $item['environment'] === $environment;
				}
			);
		}

		return $applications;
	}

	/**
	 * Get the connect application by id.
	 *
	 * @param string $id The application id.
	 *
	 * @return array|null
	 */
	public function get_connect_application( string $id ) {
		return isset( $this->get_connect_applications()[ $id ] ) ? $this->get_connect_applications()[ $id ] : null;
	}

	/**
	 * Get the connect data key.
	 */
	private function get_connect_data_key() {
		return 'woocommerce_pagbank_connect_data_' . ( $this->environment === 'production' ? 'production' : 'sandbox' );
	}

	/**
	 * Save the connection data to the database.
	 *
	 * @param array $data The data to be saved.
	 *
	 * @return bool
	 *
	 * @throws Exception If any of the required keys are empty.
	 */
	public function save( $data ): bool {
		$default = array(
			'application_id'  => '',
			'environment'     => '',
			'access_token'    => '',
			'expiration_date' => Carbon::now()->toISOString(),
			'refresh_token'   => '',
			'scope'           => '',
			'account_id'      => '',
			'public_key'      => '',
		);

		$new_data = wp_parse_args( $data, $default );

		$check_for_emptiness = array( 'application_id', 'environment', 'access_token', 'expiration_date', 'refresh_token', 'scope', 'account_id', 'public_key' );

		foreach ( $check_for_emptiness as $key ) {
			if ( empty( $new_data[ $key ] ) ) {
				throw new Exception( esc_html( 'The key ' . $key . ' is empty.' ) );
			}
		}

		return update_option( $this->get_connect_data_key(), $new_data );
	}

	/**
	 * Get the connection data from the database.
	 *
	 * @return array|null
	 */
	public function get_data(): ?array {
		$data = get_option( $this->get_connect_data_key(), null );

		if ( ! $data ) {
			return null;
		}

		$expiration_date = Carbon::parse( $data['expiration_date'] );

		if ( $expiration_date->isPast() ) {
			return null;
		}

		if ( $expiration_date->subDays( 30 )->isPast() ) {
			return $this->refresh_token( $data );
		}

		return $data;
	}

	/**
	 * Get the access token from the database.
	 *
	 * @return string|null
	 */
	public function get_access_token(): ?string {
		$data = $this->get_data();

		return $data['access_token'];
	}

	/**
	 * Refresh access token.
	 *
	 * @param array $data The old token data.
	 *
	 * @return array
	 * @throws Exception If the refresh token fails.
	 */
	private function refresh_token( $data ) {
		$api = new Api(
			$data['environment']
		);

		$refresh_token = $api->refresh_access_token(
			$data['refresh_token'],
			$data['environment'],
			$data['application_id']
		);

		if ( is_wp_error( $refresh_token ) ) {
			throw new Exception( esc_html( $refresh_token->get_error_message() ) );
		}

		$public_key = $api->get_public_key( $refresh_token['access_token'] );

		if ( is_wp_error( $public_key ) ) {
			throw new Exception( esc_html( $public_key->get_error_message() ) );
		}

		$refresh_token['public_key'] = $public_key['public_key'];

		$this->save( $refresh_token );

		return $refresh_token;
	}
}
