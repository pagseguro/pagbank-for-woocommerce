<?php
/**
 * Trait for rendering React-based gateway settings.
 *
 * @package PagBank_WooCommerce\Gateways\Traits
 */

namespace PagBank_WooCommerce\Gateways\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait ReactSettingsTrait.
 */
trait ReactSettingsTrait {

	/**
	 * Output the admin settings page with React root.
	 */
	public function admin_options(): void {
		echo '<div id="pagbank-gateway-settings-root" data-gateway-id="' . esc_attr( $this->id ) . '"></div>';
	}
}
