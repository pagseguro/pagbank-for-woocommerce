<?php
/**
 * Tests for the admin order fields helpers.
 *
 * WooCommerce splices its additional-field rows into the meta box arrays,
 * which renumbers their keys — so the helpers must identify rows by their
 * `id` entry (e.g. `_wc_billing/pagbank/cpf`), not by array key.
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\AdminOrderFields;
use PHPUnit\Framework\TestCase;

/**
 * Class AdminOrderFieldsTest.
 */
class AdminOrderFieldsTest extends TestCase {

	/**
	 * Meta box rows the way WooCommerce delivers them: core rows keyed by
	 * name, additional-field rows renumbered with the identity in `id`.
	 */
	private function meta_box_fields(): array {
		return array(
			'first_name' => array( 'label' => 'First name' ),
			'state'      => array( 'label' => 'State', 'show' => false ),
			0            => array(
				'id'    => '_wc_billing/pagbank/persontype',
				'label' => 'Tipo de pessoa',
				'value' => '1',
				'show'  => true,
			),
			1            => array(
				'id'    => '_wc_billing/pagbank/cpf',
				'label' => 'CPF',
				'value' => '062.556.385-96',
				'show'  => true,
			),
			2            => array(
				'id'    => '_wc_billing/pagbank/address-number',
				'label' => 'Número',
				'value' => '127',
				'show'  => true,
			),
		);
	}

	/**
	 * Rows spliced in with renumbered keys are still found by field id.
	 */
	public function test_find_row_key_matches_by_id(): void {
		$fields = $this->meta_box_fields();

		$this->assertSame( 1, AdminOrderFields::find_row_key( $fields, 'pagbank/cpf' ) );
		$this->assertSame( 'state', AdminOrderFields::find_row_key( $fields, 'state' ) );
		$this->assertNull( AdminOrderFields::find_row_key( $fields, 'pagbank/cnpj' ) );
	}

	/**
	 * Fields are inserted right after the anchor, preserving the order of
	 * the surrounding keys.
	 */
	public function test_insert_field_after_anchor(): void {
		$fields = $this->meta_box_fields();

		$result = AdminOrderFields::insert_field_after( $fields, 1, 'pagbank/cnpj', array( 'id' => '_wc_billing/pagbank/cnpj', 'label' => 'CNPJ' ) );

		$this->assertSame(
			array( 'first_name', 'state', 0, 1, 'pagbank/cnpj', 2 ),
			array_keys( $result )
		);
		$this->assertSame( 'CNPJ', $result['pagbank/cnpj']['label'] );
	}

	/**
	 * A missing anchor appends the field at the end instead of dropping it.
	 */
	public function test_insert_field_after_missing_anchor_appends(): void {
		$fields = array(
			'first_name' => array( 'label' => 'First name' ),
		);

		$result = AdminOrderFields::insert_field_after( $fields, 'state', 'pagbank/cnpj', array( 'label' => 'CNPJ' ) );

		$this->assertSame( array( 'first_name', 'pagbank/cnpj' ), array_keys( $result ) );
	}

	/**
	 * Moving a renumbered row re-anchors it after the target field and keeps
	 * its definition, using the row id as the new key.
	 */
	public function test_move_row_after(): void {
		$fields = array_merge(
			array( 'address_1' => array( 'label' => 'Address line 1' ) ),
			$this->meta_box_fields()
		);

		$result = AdminOrderFields::move_row_after( $fields, 'pagbank/address-number', 'address_1' );

		$this->assertSame(
			array( 'address_1', '_wc_billing/pagbank/address-number', 'first_name', 'state', 0, 1 ),
			array_keys( $result )
		);
		$this->assertSame( '127', $result['_wc_billing/pagbank/address-number']['value'] );
	}

	/**
	 * Moving a row without a present anchor keeps the fields untouched.
	 */
	public function test_move_row_after_missing_anchor_keeps_row(): void {
		$fields = $this->meta_box_fields();

		$result = AdminOrderFields::move_row_after( $fields, 'pagbank/address-number', 'address_1' );

		$this->assertSame( '127', $result[2]['value'] );
	}

	/**
	 * Interop-keyed rows (classic checkout) are recognized by the pagbank
	 * field id aliases.
	 */
	public function test_find_row_key_matches_interop_alias(): void {
		$fields = array(
			'state'  => array( 'label' => 'State' ),
			'cpf'    => array( 'label' => 'CPF' ),
			'number' => array( 'label' => 'Número' ),
		);

		$this->assertSame( 'cpf', AdminOrderFields::find_row_key( $fields, 'pagbank/cpf' ) );
		$this->assertSame( 'number', AdminOrderFields::find_row_key( $fields, 'pagbank/address-number' ) );
		$this->assertNull( AdminOrderFields::find_row_key( $fields, 'pagbank/cnpj' ) );
	}

	/**
	 * Interop rows are chained after the state anchor, skipping rows another
	 * plugin already added, with documents on full-width rows.
	 */
	public function test_insert_interop_rows(): void {
		$fields = array(
			'state' => array( 'label' => 'State' ),
			'email' => array( 'label' => 'Email' ),
			'cpf'   => array( 'label' => 'CPF (third party)' ),
		);

		$result = AdminOrderFields::insert_interop_rows(
			$fields,
			array(
				'persontype' => array( 'label' => 'Tipo de pessoa' ),
				'cpf'        => array( 'label' => 'CPF' ),
				'number'     => array( 'label' => 'Número' ),
			)
		);

		$this->assertSame( array( 'state', 'persontype', 'email', 'cpf', 'number' ), array_keys( $result ) );
		$this->assertSame( 'CPF (third party)', $result['cpf']['label'] );
		$this->assertSame( 'form-field-wide', $result['persontype']['wrapper_class'] );
		$this->assertArrayNotHasKey( 'wrapper_class', $result['number'] );
	}

	/**
	 * Hiding rows resolves the renumbered keys by field id and keeps the
	 * other rows untouched.
	 */
	public function test_mark_rows_hidden(): void {
		$result = AdminOrderFields::mark_rows_hidden(
			$this->meta_box_fields(),
			array( 'pagbank/persontype', 'pagbank/address-number', 'pagbank/missing' )
		);

		$this->assertFalse( $result[0]['show'] );
		$this->assertFalse( $result[2]['show'] );
		$this->assertTrue( $result[1]['show'] );
	}
}
