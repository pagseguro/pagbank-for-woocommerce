<?php
/**
 * Application hooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;
use WC_Payment_Token;

/**
 * Class Hooks.
 */
class Hooks {

	/**
	 * Instance.
	 */
	private static ?Hooks $instance = null;

	/**
	 * Temporary boleto files to cleanup.
	 *
	 * @var array<string>
	 */
	private array $temp_boleto_files = array();

	/**
	 * Get instance.
	 */
	public static function get_instance(): Hooks {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Log sources used by the plugin.
	 *
	 * @var array
	 */
	private const LOG_SOURCES = array(
		'pagbank_credit_card',
		'pagbank_pix',
		'pagbank_boleto',
		'pagbank_oauth',
	);

	/**
	 * Hooks constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_filter( 'woocommerce_payment_token_class', array( $this, 'filter_payment_token_class_name' ), 10, 2 );
		add_filter( 'woocommerce_payment_methods_types', array( $this, 'filter_payment_method_types' ), 10, 1 );
		add_filter( 'woocommerce_payment_methods_list_item', array( $this, 'filter_payment_methods_list_item' ), 10, 2 );
		add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 100, 3 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'filter_woocommerce_get_order_item_totals' ), 10, 2 );
		add_filter(
			'plugin_action_links_' . plugin_basename( PAGBANK_WOOCOMMERCE_FILE_PATH ),
			array(
				$this,
				'plugin_action_links',
			)
		);
		add_action( 'init', array( $this, 'filter_gateways_settings' ) );
		add_action( 'woocommerce_email_order_details', array( $this, 'add_pix_details_to_email' ), 10, 4 );
		add_action( 'woocommerce_email_order_details', array( $this, 'add_boleto_details_to_email' ), 10, 4 );
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_boleto_pdf_to_email' ), 10, 3 );
		add_action( 'woocommerce_email_sent', array( $this, 'cleanup_boleto_pdfs_after_email' ), 10, 3 );
		add_filter( 'woocommerce_format_log_entry', array( $this, 'format_log_entry' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'check_for_plugin_dependencies' ) );
		}
	}

	/**
	 * Load text domain.
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain( 'pagbank-for-woocommerce', false, dirname( plugin_basename( PAGBANK_WOOCOMMERCE_FILE_PATH ) ) . '/languages' );
	}

	/**
	 * Filter payment token class name.
	 *
	 * @param  string $class_name Class name.
	 * @param  string $type       Type.
	 *
	 * @return string             Filtered class name.
	 */
	public function filter_payment_token_class_name( string $class_name, string $type ): string {
		if ( $type === 'PagBank_CC' ) {
			return 'PagBank_WooCommerce\Presentation\PaymentToken';
		}

		return $class_name;
	}

	/**
	 * Filter payment method types.
	 *
	 * @param  array $types Payment method types.
	 *
	 * @return array        Filtered payment method types.
	 */
	public function filter_payment_method_types( array $types ): array {
		$types['pagbank_cc'] = __( 'Cartão de crédito', 'pagbank-for-woocommerce' );

		return $types;
	}

	/**
	 * Controls the output for credit cards on the my account page.
	 *
	 * @param  array            $item         Individual list item from woocommerce_saved_payment_methods_list.
	 * @param  WC_Payment_Token $payment_token The payment token associated with this method entry.
	 * @return array                           Filtered item.
	 */
	public function filter_payment_methods_list_item( array $item, WC_Payment_Token $payment_token ): array {
		if ( ! $payment_token instanceof PaymentToken ) {
			return $item;
		}

		$card_type               = $payment_token->get_card_type();
		$item['method']['last4'] = $payment_token->get_last4();
		$item['method']['brand'] = ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Cartão de crédito', 'pagbank-for-woocommerce' ) );
		$item['expires']         = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 );

		return $item;
	}

	/**
	 * Add type attribute to script tag.
	 *
	 * @param  string $tag    Script tag.
	 * @param  string $handle Script handle.
	 * @param  string $src    Script source.
	 *
	 * @return string         Filtered script tag.
	 */
	public function add_type_attribute( string $tag, string $handle, string $src ): string {
		$type = wp_scripts()->get_data( $handle, 'pagbank_script' );

		if ( $type === true ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$tag = '<script src="' . esc_url( $src ) . '" type="module"></script>';
		}

		return $tag;
	}

	/**
	 * Filter woocommerce_get_order_item_totals.
	 *
	 * @param  array    $total_rows Total rows.
	 * @param  WC_Order $order      Order.
	 *
	 * @return array                 Filtered total rows.
	 */
	public function filter_woocommerce_get_order_item_totals( array $total_rows, WC_Order $order ): array {
		if ( $order->get_payment_method() === 'pagbank_credit_card' ) {
			$installments_meta = $order->get_meta( '_pagbank_credit_card_installments' );

			if ( empty( $installments_meta ) ) {
				return $total_rows;
			}

			$installments                = max( 1, (int) $installments_meta );
			$installment_value           = $order->get_total() / $installments;
			$installment_value_formatted = Helpers::format_money( $installment_value );

			// translators: %d is the number of installments.
			$total_rows['payment_method']['value'] = sprintf( __( 'Cartão de crédito (%1$dx de %2$s)', 'pagbank-for-woocommerce' ), $installments, $installment_value_formatted );
		}

		return $total_rows;
	}

	/**
	 * Action links.
	 *
	 * @param array $links Action links.
	 */
	public function plugin_action_links( array $links ): array {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&filter=pagbank' ) ) . '">' . __( 'Configurações', 'pagbank-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Filter allowed gateways.
	 */
	public function filter_gateways_settings(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && isset( $_GET['tab'] ) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'checkout' && isset( $_GET['filter'] ) && $_GET['filter'] === 'pagbank' ) {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'filter_allowed_gateways' ) );
		}
	}

	/**
	 * Filter gateways that will be displayed in a custom settings page.
	 *
	 * @param  array $load_gateways Gateways.
	 */
	public function filter_allowed_gateways( array $load_gateways ): array {
		$allowed_gateways = array(
			'PagBank_WooCommerce\Gateways\CreditCardPaymentGateway',
			'PagBank_WooCommerce\Gateways\BoletoPaymentGateway',
			'PagBank_WooCommerce\Gateways\PixPaymentGateway',
		);

		foreach ( $load_gateways as $key => $gateway ) {
			if ( ! in_array( $gateway, $allowed_gateways, true ) ) {
				unset( $load_gateways[ $key ] );
			}
		}

		return $load_gateways;
	}

	/**
	 * Check for plugin dependencies.
	 */
	public function check_for_plugin_dependencies(): void {
		if ( ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			include dirname( PAGBANK_WOOCOMMERCE_FILE_PATH ) . '/src/templates/admin/notices/html-notice-missing-brazilian-market-on-woocommerce.php';
		}
	}

	/**
	 * Add Pix details to email.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text    Plain text.
	 * @param object   $email         Email object.
	 */
	public function add_pix_details_to_email( WC_Order $order, bool $sent_to_admin, bool $plain_text, object $email ): void {
		// Only add Pix details to customer emails, not admin emails.
		if ( $sent_to_admin ) {
			return;
		}

		// Only add Pix details for customer on-hold or processing emails.
		if ( ! in_array( $email->id, array( 'customer_on_hold_order', 'customer_processing_order' ), true ) ) {
			return;
		}

		// Only add Pix details for Pix payment method.
		if ( $order->get_payment_method() !== 'pagbank_pix' ) {
			return;
		}

		// Don't add Pix details if order is already paid.
		if ( $order->is_paid() ) {
			return;
		}

		$pix_expiration_date = $order->get_meta( '_pagbank_pix_expiration_date' );
		$pix_text            = $order->get_meta( '_pagbank_pix_text' );
		$pix_qr_code         = $order->get_meta( '_pagbank_pix_qr_code' );

		// Check if we have Pix data.
		if ( empty( $pix_text ) || empty( $pix_qr_code ) ) {
			return;
		}

		if ( $plain_text ) {
			wc_get_template(
				'emails/plain/email-pix-instructions.php',
				array(
					'order'               => $order,
					'pix_expiration_date' => $pix_expiration_date,
					'pix_text'            => $pix_text,
					'pix_qr_code'         => $pix_qr_code,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		} else {
			wc_get_template(
				'emails/email-pix-instructions.php',
				array(
					'order'               => $order,
					'pix_expiration_date' => $pix_expiration_date,
					'pix_text'            => $pix_text,
					'pix_qr_code'         => $pix_qr_code,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		}
	}

	/**
	 * Add Boleto details to email.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text    Plain text.
	 * @param object   $email         Email object.
	 */
	public function add_boleto_details_to_email( WC_Order $order, bool $sent_to_admin, bool $plain_text, object $email ): void {
		// Only add Boleto details to customer emails, not admin emails.
		if ( $sent_to_admin ) {
			return;
		}

		// Only add Boleto details for customer on-hold or processing emails.
		if ( ! in_array( $email->id, array( 'customer_on_hold_order', 'customer_processing_order' ), true ) ) {
			return;
		}

		// Only add Boleto details for Boleto payment method.
		if ( $order->get_payment_method() !== 'pagbank_boleto' ) {
			return;
		}

		// Don't add Boleto details if order is already paid.
		if ( $order->is_paid() ) {
			return;
		}

		$boleto_expiration_date = $order->get_meta( '_pagbank_boleto_expiration_date' );
		$boleto_barcode         = $order->get_meta( '_pagbank_boleto_barcode' );
		$boleto_link_pdf        = $order->get_meta( '_pagbank_boleto_link_pdf' );
		$boleto_link_png        = $order->get_meta( '_pagbank_boleto_link_png' );

		// Check if we have Boleto data.
		if ( empty( $boleto_barcode ) || empty( $boleto_link_pdf ) ) {
			return;
		}

		if ( $plain_text ) {
			wc_get_template(
				'emails/plain/email-boleto-instructions.php',
				array(
					'order'                  => $order,
					'boleto_expiration_date' => $boleto_expiration_date,
					'boleto_barcode'         => $boleto_barcode,
					'boleto_link_pdf'        => $boleto_link_pdf,
					'boleto_link_png'        => $boleto_link_png,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		} else {
			wc_get_template(
				'emails/email-boleto-instructions.php',
				array(
					'order'                  => $order,
					'boleto_expiration_date' => $boleto_expiration_date,
					'boleto_barcode'         => $boleto_barcode,
					'boleto_link_pdf'        => $boleto_link_pdf,
					'boleto_link_png'        => $boleto_link_png,
				),
				'woocommerce/pagbank/',
				PAGBANK_WOOCOMMERCE_TEMPLATES_PATH
			);
		}
	}

	/**
	 * Attach Boleto PDF to email.
	 *
	 * @param array  $attachments Attachments array.
	 * @param string $email_id    Email ID.
	 * @param object $order       Order object.
	 */
	public function attach_boleto_pdf_to_email( array $attachments, string $email_id, object $order ): array {
		// Only attach to customer emails.
		if ( ! in_array( $email_id, array( 'customer_on_hold_order', 'customer_processing_order' ), true ) ) {
			return $attachments;
		}

		// Check if order exists and is a WC_Order.
		if ( ! $order instanceof WC_Order ) {
			return $attachments;
		}

		// Only attach for Boleto payment method.
		if ( $order->get_payment_method() !== 'pagbank_boleto' ) {
			return $attachments;
		}

		// Don't attach if order is already paid.
		if ( $order->is_paid() ) {
			return $attachments;
		}

		$boleto_link_pdf = $order->get_meta( '_pagbank_boleto_link_pdf' );

		// Check if we have the PDF link.
		if ( empty( $boleto_link_pdf ) ) {
			return $attachments;
		}

		// Download the PDF temporarily.
		$temp_file = $this->download_boleto_pdf( $boleto_link_pdf, $order->get_id() );

		if ( $temp_file && file_exists( $temp_file ) ) {
			$attachments[] = $temp_file;
			// Store the file path for cleanup after email is sent.
			$this->temp_boleto_files[ $order->get_id() ] = $temp_file;
		}

		return $attachments;
	}

	/**
	 * Download Boleto PDF to temporary file.
	 *
	 * @param string $pdf_url  PDF URL.
	 * @param int    $order_id Order ID.
	 *
	 * @return string|false Temporary file path or false on failure.
	 */
	private function download_boleto_pdf( string $pdf_url, int $order_id ) {
		// Create temp directory if it doesn't exist.
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/pagbank-boletos';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// Generate secure random filename to prevent enumeration attacks.
		$random_hash = wp_generate_password( 32, false, false );
		$temp_file   = $temp_dir . '/boleto-' . $order_id . '-' . $random_hash . '.pdf';

		// Download the PDF.
		$response = wp_remote_get(
			$pdf_url,
			array(
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$pdf_content = wp_remote_retrieve_body( $response );

		if ( empty( $pdf_content ) ) {
			return false;
		}

		// Save to temp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $temp_file, $pdf_content );

		if ( false === $result ) {
			return false;
		}

		return $temp_file;
	}

	/**
	 * Cleanup temporary boleto PDFs after email is sent.
	 *
	 * @param bool   $sent     Whether email was sent successfully.
	 * @param string $email_id Email ID.
	 * @param object $email    Email object.
	 */
	public function cleanup_boleto_pdfs_after_email( bool $sent, string $email_id, object $email ): void {
		// Only cleanup for customer emails.
		if ( ! in_array( $email_id, array( 'customer_on_hold_order', 'customer_processing_order' ), true ) ) {
			return;
		}

		// Get order from email object.
		if ( ! isset( $email->object ) || ! $email->object instanceof WC_Order ) {
			return;
		}

		$order = $email->object;

		// Check if we have a temp file for this order.
		$order_id = $order->get_id();
		if ( ! isset( $this->temp_boleto_files[ $order_id ] ) ) {
			return;
		}

		$temp_file = $this->temp_boleto_files[ $order_id ];

		// Delete the temporary file.
		if ( file_exists( $temp_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp_file );
		}

		// Remove from tracking array.
		unset( $this->temp_boleto_files[ $order_id ] );
	}

	/**
	 * Format log entry for PagBank sources.
	 *
	 * WooCommerce uses stripslashes() when formatting the context JSON, which breaks
	 * JSON strings that contain escaped quotes (like reference_id containing JSON).
	 * This filter reformats the log entry without stripslashes for PagBank log sources.
	 *
	 * @param string $entry   The formatted log entry.
	 * @param array  $details The log entry details containing timestamp, level, message, and context.
	 *
	 * @return string The formatted log entry.
	 */
	public function format_log_entry( string $entry, array $details ): string {
		$context = $details['context'] ?? array();
		$source  = $context['source'] ?? '';

		// Only process PagBank log sources.
		if ( ! in_array( $source, self::LOG_SOURCES, true ) ) {
			return $entry;
		}

		// Rebuild the entry without stripslashes.
		$timestamp    = $details['timestamp'] ?? time();
		$level        = $details['level'] ?? 'debug';
		$message      = $details['message'] ?? '';
		$time_string  = gmdate( 'c', $timestamp );
		$level_string = strtoupper( $level );

		// Remove the corrupted CONTEXT from the message (WooCommerce adds it with stripslashes).
		$context_pos = strpos( $message, ' CONTEXT: ' );
		if ( false !== $context_pos ) {
			$message = substr( $message, 0, $context_pos );
		}

		// Remove source from context for the entry.
		$context_for_entry = $context;
		unset( $context_for_entry['source'] );

		if ( ! empty( $context_for_entry ) ) {
			$formatted_context = wp_json_encode( $context_for_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$message          .= ' CONTEXT: ' . $formatted_context;
		}

		return $time_string . ' ' . $level_string . ' ' . $message;
	}
}
