<?php
/**
 * Application hooks.
 *
 * @package PagBank_WooCommerce\Presentation
 */

namespace PagBank_WooCommerce\Presentation;

/**
 * Class Hooks.
 */
class Hooks {

	/**
	 * Instance.
	 *
	 * @var Hooks
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
