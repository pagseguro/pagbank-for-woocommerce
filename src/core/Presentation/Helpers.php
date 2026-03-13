<?php
/**
 * Application helper public static functions.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PagBank_WooCommerce\Validators\CPF;
use PagBank_WooCommerce\Validators\CNPJ;
use PagBank_WooCommerce\Validators\AlphanumericCNPJ;

/**
 * Class Helpers.
 */
class Helpers {

	/**
	 * Format money to cents.
	 *
	 * @param float $value Value.
	 */
	public static function format_money_cents( float $value ): int {
		return (int) ( $value * 100 );
	}

	/**
	 * Format money from cents.
	 *
	 * @param int $value Value.
	 */
	public static function format_money_from_cents( int $value ): float {
		return (float) ( $value / 100 );
	}

	/**
	 * Format money to string.
	 *
	 * @param float $value Value.
	 */
	public static function format_money( float $value ): string {
		$currency_symbol    = get_woocommerce_currency_symbol();
		$decimal_separator  = wc_get_price_decimal_separator();
		$thousand_separator = wc_get_price_thousand_separator();
		$decimals           = wc_get_price_decimals();
		$price_format       = get_woocommerce_price_format();

		$price = sprintf( $price_format, $currency_symbol, number_format( $value, $decimals, $decimal_separator, $thousand_separator ) );

		return html_entity_decode( $price );
	}

	/**
	 * Base64 encode text.
	 *
	 * @param string $text Text.
	 */
	public static function encode_text( string $text ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $text );
	}

	/**
	 * Base64 decode text.
	 *
	 * @param string $text Text.
	 */
	public static function decode_text( string $text ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $text );
	}

	/**
	 * Sanitize checkout field.
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string Sanitized HTML content.
	 */
	public static function sanitize_checkout_field( string $html ): string {
		return wp_kses(
			html_entity_decode( $html ?? '' ),
			array(
				'fieldset' => array(
					'id'    => array(),
					'class' => array(),
				),
				'span'     => array(
					'id' => array(),
				),
				'p'        => array(
					'class' => array(),
				),
				'input'    => array(
					'id'             => array(),
					'name'           => array(),
					'class'          => array(),
					'autocomplete'   => array(),
					'autocorrect'    => array(),
					'autocapitalize' => array(),
					'spellcheck'     => array(),
					'type'           => array(),
					'style'          => array(),
				),
				'label'    => array(
					'for' => array(),
				),
			)
		);
	}

	/**
	 * Check if WooCommerce is activated.
	 *
	 * @return bool If WooCommerce is activated.
	 */
	public static function is_woocommerce_activated(): bool {
		return class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check if WCFM is activated.
	 *
	 * @return bool If WCFM is activated.
	 */
	public static function is_wcfm_activated(): bool {
		return class_exists( 'WCFM' ) || in_array( 'wc-frontend-manager/wc_frontend_manager.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check if the store is localhost.
	 *
	 * @return bool If is localhost.
	 */
	public static function is_localhost(): bool {
		return wp_parse_url( get_site_url() )['host'] === 'localhost';
	}

	/**
	 * Validate CPF number.
	 *
	 * @param string $cpf CPF number (with or without formatting).
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_cpf( string $cpf ): bool {
		$validator = new CPF( $cpf );

		return $validator->is_valid();
	}

	/**
	 * Format CPF number.
	 *
	 * @param string $cpf CPF number (with or without formatting).
	 *
	 * @return string Formatted CPF (000.000.000-00).
	 */
	public static function format_cpf( string $cpf ): string {
		$validator = new CPF( $cpf );

		return $validator->format();
	}

	/**
	 * Validate CNPJ number.
	 *
	 * When PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED is true, validates both traditional
	 * numeric CNPJ and the new alphanumeric format (starting July 2026).
	 *
	 * @param string $cnpj CNPJ number (with or without formatting).
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_cnpj( string $cnpj ): bool {
		if ( self::get_constant_value( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', false ) ) {
			$validator = new AlphanumericCNPJ( $cnpj );

			return $validator->is_valid();
		}

		$validator = new CNPJ( $cnpj );

		return $validator->is_valid();
	}

	/**
	 * Format CNPJ number.
	 *
	 * When PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED is true, formats both traditional
	 * numeric CNPJ and the new alphanumeric format (starting July 2026).
	 *
	 * @param string $cnpj CNPJ number (with or without formatting).
	 *
	 * @return string Formatted CNPJ (00.000.000/0000-00 or XX.XXX.XXX/XXXX-XX).
	 */
	public static function format_cnpj( string $cnpj ): string {
		if ( self::get_constant_value( 'PAGBANK_FEATURE_FLAG_ALPHANUMERIC_CNPJ_ENABLED', false ) ) {
			$validator = new AlphanumericCNPJ( $cnpj );

			return $validator->format();
		}

		$validator = new CNPJ( $cnpj );

		return $validator->format();
	}

	/**
	 * Format CPF or CNPJ number based on its length/validity.
	 *
	 * @param string $value CPF or CNPJ number (with or without formatting).
	 *
	 * @return string|null Formatted CPF/CNPJ or null if invalid.
	 */
	public static function format_cpf_or_cnpj( string $value ): ?string {
		$is_valid_cpf  = self::is_valid_cpf( $value );
		$is_valid_cnpj = self::is_valid_cnpj( $value );

		return $is_valid_cpf ? self::format_cpf( $value ) : ( $is_valid_cnpj ? self::format_cnpj( $value ) : null );
	}

	/**
	 * Get the value of a PagBank constant if defined.
	 *
	 * Use this method to retrieve string/value constants like PAGBANK_WEBHOOK_SITE_URL.
	 *
	 * @param string $constant_name The full constant name.
	 * @param mixed  $fallback      Fallback value if constant is not defined.
	 *
	 * @return mixed The constant value or fallback.
	 */
	public static function get_constant_value( string $constant_name, $fallback = null ) {
		return \defined( $constant_name ) ? \constant( $constant_name ) : $fallback;
	}

	/**
	 * Filter a string to keep only numeric characters.
	 *
	 * @param string $value Input string.
	 *
	 * @return string String containing only numbers.
	 */
	public static function filter_only_numbers( string $value ): string {
		return preg_replace( '/[^0-9]/', '', $value );
	}

	/**
	 * Validate alphanumeric CNPJ (new format starting July 2026).
	 *
	 * The alphanumeric CNPJ has 14 positions:
	 * - First 12 positions: alphanumeric (0-9, A-Z)
	 * - Last 2 positions: numeric digits (verification digits)
	 *
	 * The verification digits are calculated using module 11, where each character
	 * is converted to its ASCII code minus 48 (so '0' = 0, 'A' = 17, 'Z' = 42).
	 *
	 * @see https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/cnpj-alfanumerico
	 * @see https://www.serpro.gov.br/menu/noticias/noticias-2024/cnpj-alfanumerico
	 *
	 * @param string $cnpj CNPJ alfanumérico (with or without formatting).
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_alphanumeric_cnpj( string $cnpj ): bool {
		$validator = new AlphanumericCNPJ( $cnpj );

		return $validator->is_valid();
	}

	/**
	 * Format alphanumeric CNPJ.
	 *
	 * @param string $cnpj CNPJ alfanumérico (without formatting).
	 *
	 * @return string Formatted CNPJ (XX.XXX.XXX/XXXX-XX).
	 */
	public static function format_alphanumeric_cnpj( string $cnpj ): string {
		$validator = new AlphanumericCNPJ( $cnpj );

		return $validator->format();
	}

	/**
	 * Check if the checkout page uses WooCommerce Blocks.
	 *
	 * @return bool True if using Blocks checkout, false for legacy checkout.
	 */
	public static function is_checkout_block(): bool {
		$checkout_page_id = wc_get_page_id( 'checkout' );

		if ( $checkout_page_id <= 0 ) {
			return false;
		}

		$checkout_page = get_post( $checkout_page_id );

		if ( ! $checkout_page ) {
			return false;
		}

		return has_block( 'woocommerce/checkout', $checkout_page );
	}

	/**
	 * Parse CPF or CNPJ value and return structured data.
	 *
	 * @param string $value CPF or CNPJ number (with or without formatting).
	 *
	 * @return array{type: string, value: string|null, is_valid: bool, error_message: string|null} Parsed data.
	 */
	public static function parse_cpf_or_cnpj( string $value ): array {
		$digits        = self::filter_only_numbers( $value );
		$is_valid_cpf  = self::is_valid_cpf( $value );
		$is_valid_cnpj = self::is_valid_cnpj( $value );

		$is_valid = $is_valid_cpf || $is_valid_cnpj;

		// Determine error message based on validation result.
		$error_message = null;
		if ( ! $is_valid ) {
			if ( strlen( $digits ) === 11 ) {
				$error_message = __( 'CPF inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' );
			} elseif ( strlen( $digits ) === 14 ) {
				$error_message = __( 'CNPJ inválido. Verifique os dígitos informados.', 'pagbank-for-woocommerce' );
			} else {
				$error_message = __( 'CPF/CNPJ inválido. Informe 11 dígitos para CPF ou 14 para CNPJ.', 'pagbank-for-woocommerce' );
			}
		}

		return array(
			'type'          => $is_valid_cpf ? 'cpf' : ( $is_valid_cnpj ? 'cnpj' : 'unknown' ),
			'value'         => $is_valid ? $digits : null,
			'is_valid'      => $is_valid,
			'error_message' => $error_message,
		);
	}
}
