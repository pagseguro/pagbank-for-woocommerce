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
			'b728b941-dfdd-4b6c-a351-38ce4d3d0e4d' => array(
				'id'           => 'b728b941-dfdd-4b6c-a351-38ce4d3d0e4d',
				'title'        => 'Recebimento em 14 dias',
				'fee'          => 4.99,
				'access_token' => 'd16+IOeXfMUsOs++Yd6Ivacs3B3ixf0d9SsWSZUBk3UEB9r0TkiQkKR5qJjreBaZYXSYwXCoZuoT0eBIqr3VPFQqYGJI6ZGe+f4cqTWTlNlcauvqGtrNxY6pqp0lgVZIEwwCVD7jqy2qUpc/02VQTuxcs2AqgljJvhTJ1SCAFF3BN/jN+2cjhyIeNP+T7kYwyMamCn0tubHyMG75QmPiLrZtGSCsK633wdcD2lYLX9wxhpRoOpbU+CwtRPBvMJIGYGGyi3hULfmQ97UrOCDAKK3BYjt14fa1/9bphoh8TrsUmxGUHz6GdeF9IbnIDNmvF8ixnFFAqv1tlweJDIHnaQ==',
				'environment'  => 'sandbox',
			),
			'803000c4-a8f4-4588-91e0-a30e70856f2e' => array(
				'id'           => '803000c4-a8f4-4588-91e0-a30e70856f2e',
				'title'        => 'Recebimento em 30 dias',
				'fee'          => 3.99,
				'access_token' => 'dnlDomZhDTE2/l3QT1sKVRtVyZoP++HmjBpxoDrytCfjTTS5f1t8C8eeXUUPJJMUB3NTEO0VXWFpU4PGs5QJX8A23B1+rRCqZdTqY3fsfqA9qwkOhDH7dsoWBW5XI+niYaC/yo/nVX51d4AgyA5YGyioL6KDIsPaV0O1xayF6TlxcRtGMbP09Ii9NFvgHJTbVTVebCj9HrpZCyKSfxa/gDiTpzqUbzs6JW3X8sD6dpm7nx6rFTxjJXFK3MmBf8lhaVPEVa0wJ7XJM2RsVufAYiDZApch8ioSH4YMvs1ZWSafmilb/oAoYZFZ3UYx/m8UJmB58aIU2HTmihLLkFhenw==',
				'environment'  => 'sandbox',
			),
			'48adca1d-8ed0-4318-b4ac-a512e3c7a120' => array(
				'id'           => '48adca1d-8ed0-4318-b4ac-a512e3c7a120',
				'title'        => 'Personalizado',
				'fee'          => null,
				'access_token' => 'e1e6vPulLp0XllX/iHAoIoFhZC9VydhyqkEgskibVrb6l7yBo7laYKufOFAx9ndMTkwZF7fDEuO7+3CUykc2FcXhGnMFf1MVXCjSXAFAnlUMWt4kvRhldLB2mRCJjEa6N3dQJDfMw4mV9iyucyRTnDO738AKtcCJLU+PJb0XnPF5m3K0TmAZIOA6jhk5P7PhujE7vW8MfiFXPwsUAwsYhavV3Q1eCGTp/oOSJdvjO67u2l4QNoe6cXJ4TqQSPURUVmcESzFt55s2ubXM/qABOJY+ro8M4EIahsIUj4/rOtykoMQJ2POCmBWkOrxwb0fCrc/mOIP0XiSu1xD45UplGg==',
				'environment'  => 'sandbox',
			),
			'05c24ebd-1782-4e33-b6ea-acad83757b61' => array(
				'id'           => '05c24ebd-1782-4e33-b6ea-acad83757b61',
				'title'        => 'Recebimento em 14 dias',
				'fee'          => 4.99,
				'access_token' => 'CUVxlfafee1sZHmnFAGZR3O8xq9/IDvYEozwkE/MMdb0vY9YimoXwguBJmxNcgSvZWiH9jIXTFZZJuue1BK0VRNaiA0rcUGuba3jS/IU18YRJTx8ku6nqsO0PyHOjg0lVpiZuWTfPqLndQhgroDJ9Siza6Pt94cHH2P0r81igb3c2FMpMqb7nfo+FJ/5Mj/wo4bKB2NDHFDTrpDMVvI49qJIJ3l884sS/SmMnEEaPVd7RDmw5kH013JzUf/+erqCb/q2Vg6ucZNarZsW1CkCREheSXmdm97E7G9xPgLXBXaZE0+4TIilhuikZxkTDkuZn2qlweNxzqv3DjOD6sCNOg==',
				'environment'  => 'production',
			),
			'f8fabd0d-f5b1-45af-a87f-f7908b64de5b' => array(
				'id'           => 'f8fabd0d-f5b1-45af-a87f-f7908b64de5b',
				'title'        => 'Recebimento em 30 dias',
				'fee'          => 3.99,
				'access_token' => 'Y5WQZRFoS2hQNGC4AWnAv6DmsbyE5Qo4W+urTwhbAQU8Y0I5f9G38lqBd05aZlwH4v7O8gxcZ1WzuH3eakLrLnwSz8un+0PHQ0qz7fs4q+Q+IOo4RgizjADhbkbH/qQC5xzv+h3x6eFuX7zOeiVNZAct6Rf94r+B8yT4Rc8neggXHy4K+9of7QMOdKLBYOZZuJyxKK+Wp/HQWyTsKjs0rHThitZnv4Wiy1W8Znwiu+Ey2Ua7JQmLwZ10QtLJs9mr7NUUNcKPmdg//Sch40F1SGKAaFWx00+LBcProHJRmBCWMgXuYXJm1+aFndLb1yyZaABwWbAVGxnnDJKTm9cYwA==',
				'environment'  => 'production',
			),
			'b8691fc6-83c5-4af8-81f5-91ca99ea018b' => array(
				'id'           => 'b8691fc6-83c5-4af8-81f5-91ca99ea018b',
				'title'        => 'Personalizado',
				'fee'          => null,
				'access_token' => 'LTsrhgS3joAbiK2Xu8m4UnSHAGL+mgWSMp8WY/sU6MgBHnM6dvvuA6egFyqMmiKXMjWQJ3eGVQfiu5XFRm+uGiywKZ/RVam2gT+CcvMMx8un71TM4UcyCQaTWBAO12tMmm+aWPb9iGDAjOrN1MXu9GopZGjywQiPa4U5CNpPFoMibT2roMJIQy7zrl5lFbfaKF78mG6jVMsOHntaxSF3Wv4cv4x3/KZXfSoC3cCO7ii43ZbGZCcHbYCNUuZm6wLP229XqyE1SfnXV7U+GUTUUfdlJ8A1tjUKgpTAGYYbXXE2fXAWl36yVA+Je3gduyv/qxvLnN2FTljJU6+o4f+4Eg==',
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
			throw new Exception( $refresh_token->get_error_message() );
		}

		$this->save( $refresh_token );

		return $refresh_token;
	}

}
