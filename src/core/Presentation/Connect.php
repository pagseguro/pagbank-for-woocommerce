<?php
/**
 * The Connect class is responsible for saving and retrieving the Connect data from the database.
 * If the expiration date is past, the data is considered invalid.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

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
			'b728b941-dfdd-4b6c-a351-38ce4d3d0e4d'   => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-38ce4d3d0e4d',
				'title'        => 'Recebimento em 14 dias',
				'fee'          => 4.99,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'sandbox',
			),
			'b728b941-dfdd-4b6c-a351-invalid-1'      => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-invalid-1',
				'title'        => 'Recebimento em 30 dias',
				'fee'          => 3.99,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'sandbox',
			),
			'b728b941-dfdd-4b6c-a351-invalid-2'      => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-invalid-2',
				'title'        => 'Personalizado',
				'fee'          => null,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'sandbox',
			),
			'b728b941-dfdd-4b6c-a351-prod-invalid-1' => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-prod-invalid-1',
				'title'        => 'Recebimento em 14 dias',
				'fee'          => 4.99,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'production',
			),
			'b728b941-dfdd-4b6c-a351-prod-invalid-2' => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-prod-invalid-2',
				'title'        => 'Recebimento em 30 dias',
				'fee'          => 3.99,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'production',
			),
			'b728b941-dfdd-4b6c-a351-prod-invalid-3' => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-prod-invalid-3',
				'title'        => 'Personalizado',
				'fee'          => null,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'production',
			),
		);

		if ( $environment ) {
			return array_filter(
				$applications,
				function( $item ) use ( $environment ) {
					return $item['environment'] === $environment;
				}
			);
		}

		return $applications;
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
		);

		$new_data = wp_parse_args( $data, $default );

		$check_for_emptiness = array( 'application_id', 'environment', 'access_token', 'expiration_date', 'refresh_token', 'scope', 'account_id' );

		foreach ( $check_for_emptiness as $key ) {
			if ( empty( $new_data[ $key ] ) ) {
				throw new Exception( 'The key ' . $key . ' is empty.' );
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

}
