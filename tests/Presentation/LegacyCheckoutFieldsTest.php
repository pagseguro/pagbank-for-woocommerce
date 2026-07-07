<?php
/**
 * Tests for the native classic-checkout fields (detection and field insertion).
 *
 * @package PagBank_WooCommerce\Tests\Presentation
 */

namespace PagBank_WooCommerce\Tests\Presentation;

use PagBank_WooCommerce\Presentation\LegacyCheckoutFields;
use PHPUnit\Framework\TestCase;

/**
 * Class LegacyCheckoutFieldsTest.
 */
class LegacyCheckoutFieldsTest extends TestCase {

	/**
	 * Brazilian Market `person_type` = 0 (or missing) means the CPF/CNPJ
	 * fields are disabled; any other value enables them.
	 */
	public function test_brazilian_market_document_fields_detection(): void {
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => 0 ) ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => '0' ) ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_document_fields( array() ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_document_fields( false ) );

		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => 1 ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => '1' ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => 2 ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_document_fields( array( 'person_type' => 3 ) ) );
	}

	/**
	 * Brazilian Market `cell_phone` semantics: '1' = separate optional field,
	 * '2' = separate required field, '-1' = phone relabeled as cellphone
	 * (role covered), '0'/unset/other = disabled.
	 */
	public function test_brazilian_market_cellphone_field_detection(): void {
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array() ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => '' ) ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => '0' ) ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => 'yes' ) ) );
		$this->assertFalse( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( false ) );

		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => 1 ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => '1' ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => '2' ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => '-1' ) ) );
		$this->assertTrue( LegacyCheckoutFields::brazilian_market_provides_cellphone_field( array( 'cell_phone' => -1 ) ) );
	}

	/**
	 * LinkNacional person type option: 'none' disables the fields; any other
	 * value (physical, legal, both) enables them.
	 */
	public function test_linknacional_document_fields_detection(): void {
		$this->assertFalse( LegacyCheckoutFields::linknacional_document_fields_enabled( 'none' ) );

		$this->assertTrue( LegacyCheckoutFields::linknacional_document_fields_enabled( 'physical' ) );
		$this->assertTrue( LegacyCheckoutFields::linknacional_document_fields_enabled( 'legal' ) );
		$this->assertTrue( LegacyCheckoutFields::linknacional_document_fields_enabled( 'both' ) );
	}

	/**
	 * When no external plugin provides any group, the person type selector,
	 * CPF, CNPJ, number, neighborhood and cellphone fields are inserted with
	 * the expected keys, priorities and classes.
	 */
	public function test_insert_billing_fields_adds_all_fields(): void {
		$provides = array(
			'document'  => false,
			'address'   => false,
			'cellphone' => false,
		);

		$fields = LegacyCheckoutFields::insert_billing_fields( array(), $provides, true );

		$this->assertArrayHasKey( 'billing_persontype', $fields );
		$this->assertArrayHasKey( 'billing_cpf', $fields );
		$this->assertArrayHasKey( 'billing_cnpj', $fields );
		$this->assertArrayHasKey( 'billing_number', $fields );
		$this->assertArrayHasKey( 'billing_neighborhood', $fields );
		$this->assertArrayHasKey( 'billing_cellphone', $fields );

		$this->assertSame( 41, $fields['billing_persontype']['priority'] );
		$this->assertSame( 42, $fields['billing_cpf']['priority'] );
		$this->assertSame( 43, $fields['billing_cnpj']['priority'] );
		$this->assertSame( 55, $fields['billing_number']['priority'] );
		$this->assertSame( 56, $fields['billing_neighborhood']['priority'] );
		$this->assertSame( 99, $fields['billing_cellphone']['priority'] );

		// The selector follows the interop convention: '1' = CPF, '2' = CNPJ.
		// (PHP casts numeric string array keys to int.)
		$this->assertSame( 'select', $fields['billing_persontype']['type'] );
		$this->assertSame( '1', $fields['billing_persontype']['default'] );
		$this->assertSame( array( 1, 2 ), array_keys( $fields['billing_persontype']['options'] ) );
		$this->assertTrue( $fields['billing_persontype']['required'] );

		// CPF/CNPJ requiredness depends on the selected person type, so the
		// core required flag stays off (enforced in validation instead).
		$this->assertFalse( $fields['billing_cpf']['required'] );
		$this->assertFalse( $fields['billing_cnpj']['required'] );

		$this->assertContains( 'person-type-field', $fields['billing_cpf']['class'] );
		$this->assertContains( 'person-type-field', $fields['billing_cnpj']['class'] );
		$this->assertContains( 'form-row-first', $fields['billing_number']['class'] );
		$this->assertContains( 'form-row-last', $fields['billing_neighborhood']['class'] );
	}

	/**
	 * When the person type selector is inserted and the core company field
	 * exists, it is relabeled as Razão Social next to the CNPJ field.
	 */
	public function test_insert_billing_fields_adjusts_company_field(): void {
		$provides = array(
			'document'  => false,
			'address'   => true,
			'cellphone' => true,
		);

		$existing = array(
			'billing_company' => array(
				'label'    => 'Company name',
				'class'    => array( 'form-row-wide' ),
				'required' => false,
				'priority' => 30,
			),
		);

		$fields = LegacyCheckoutFields::insert_billing_fields( $existing, $provides, true );

		$this->assertSame( 'Razão Social', $fields['billing_company']['label'] );
		$this->assertSame( 44, $fields['billing_company']['priority'] );
		$this->assertContains( 'person-type-field', $fields['billing_company']['class'] );
		$this->assertFalse( $fields['billing_company']['required'] );
	}

	/**
	 * The company field is created when missing (store hides the company
	 * field in the WooCommerce settings), since legal persons must provide
	 * the Razão Social.
	 */
	public function test_insert_billing_fields_creates_company_field_when_missing(): void {
		$provides = array(
			'document'  => false,
			'address'   => true,
			'cellphone' => true,
		);

		$fields = LegacyCheckoutFields::insert_billing_fields( array(), $provides, true );

		$this->assertArrayHasKey( 'billing_company', $fields );
		$this->assertSame( 'Razão Social', $fields['billing_company']['label'] );
		$this->assertSame( 44, $fields['billing_company']['priority'] );
		$this->assertContains( 'person-type-field', $fields['billing_company']['class'] );
		$this->assertFalse( $fields['billing_company']['required'] );
	}

	/**
	 * The company field is NOT touched when the document group is provided
	 * externally or when another plugin already added the person type field.
	 */
	public function test_insert_billing_fields_leaves_company_field_alone(): void {
		$company = array(
			'label'    => 'Company name',
			'priority' => 30,
		);

		// Document group provided externally.
		$fields = LegacyCheckoutFields::insert_billing_fields(
			array( 'billing_company' => $company ),
			array(
				'document'  => true,
				'address'   => true,
				'cellphone' => true,
			),
			true
		);
		$this->assertSame( $company, $fields['billing_company'] );

		// Person type field already present (unknown third-party plugin).
		$fields = LegacyCheckoutFields::insert_billing_fields(
			array(
				'billing_company'    => $company,
				'billing_persontype' => array( 'label' => 'Custom' ),
			),
			array(
				'document'  => false,
				'address'   => true,
				'cellphone' => true,
			),
			true
		);
		$this->assertSame( $company, $fields['billing_company'] );
	}

	/**
	 * The `required` flag follows the country detection.
	 */
	public function test_insert_billing_fields_respects_required_flag(): void {
		$provides = array(
			'document'  => false,
			'address'   => false,
			'cellphone' => false,
		);

		$fields = LegacyCheckoutFields::insert_billing_fields( array(), $provides, false );

		$this->assertFalse( $fields['billing_persontype']['required'] );
		$this->assertFalse( $fields['billing_number']['required'] );
	}

	/**
	 * Field groups provided by an external plugin are skipped, while the
	 * remaining groups are still inserted.
	 */
	public function test_insert_billing_fields_skips_provided_groups(): void {
		$provides = array(
			'document'  => true,
			'address'   => true,
			'cellphone' => false,
		);

		$fields = LegacyCheckoutFields::insert_billing_fields( array(), $provides, true );

		$this->assertArrayNotHasKey( 'billing_persontype', $fields );
		$this->assertArrayNotHasKey( 'billing_cpf', $fields );
		$this->assertArrayNotHasKey( 'billing_cnpj', $fields );
		$this->assertArrayNotHasKey( 'billing_number', $fields );
		$this->assertArrayNotHasKey( 'billing_neighborhood', $fields );
		$this->assertArrayHasKey( 'billing_cellphone', $fields );
	}

	/**
	 * Nothing is inserted when every group is provided externally.
	 */
	public function test_insert_billing_fields_adds_nothing_when_all_provided(): void {
		$provides = array(
			'document'  => true,
			'address'   => true,
			'cellphone' => true,
		);

		$existing = array(
			'billing_first_name' => array( 'label' => 'First name' ),
		);

		$this->assertSame( $existing, LegacyCheckoutFields::insert_billing_fields( $existing, $provides, true ) );
	}

	/**
	 * Fields already present in the array are never overwritten (duplicate
	 * hardening against unknown third-party plugins).
	 */
	public function test_insert_billing_fields_never_overwrites_existing_fields(): void {
		$provides = array(
			'document'  => false,
			'address'   => false,
			'cellphone' => false,
		);

		$existing_number = array(
			'label'    => 'Custom number',
			'priority' => 99,
		);

		$fields = LegacyCheckoutFields::insert_billing_fields(
			array( 'billing_number' => $existing_number ),
			$provides,
			true
		);

		$this->assertSame( $existing_number, $fields['billing_number'] );
		$this->assertArrayHasKey( 'billing_persontype', $fields );
	}

	/**
	 * Shipping fields are inserted only when no external plugin provides the
	 * address fields, and never overwrite existing entries.
	 */
	public function test_insert_shipping_fields(): void {
		$fields = LegacyCheckoutFields::insert_shipping_fields( array(), false, true );

		$this->assertArrayHasKey( 'shipping_number', $fields );
		$this->assertArrayHasKey( 'shipping_neighborhood', $fields );
		$this->assertSame( 55, $fields['shipping_number']['priority'] );
		$this->assertSame( 56, $fields['shipping_neighborhood']['priority'] );

		$this->assertSame( array(), LegacyCheckoutFields::insert_shipping_fields( array(), true, true ) );

		$existing = array( 'shipping_number' => array( 'label' => 'Custom' ) );
		$result   = LegacyCheckoutFields::insert_shipping_fields( $existing, false, true );
		$this->assertSame( $existing['shipping_number'], $result['shipping_number'] );
	}

	/**
	 * Individuals keep only the CPF: a partially typed CNPJ (and the Razão
	 * Social) left behind by the person type toggle is dropped.
	 */
	public function test_clear_document_posted_data_individual(): void {
		$data = LegacyCheckoutFields::clear_document_posted_data(
			array(
				'billing_persontype' => '1',
				'billing_cpf'        => '062.556.385-96',
				'billing_cnpj'       => '06.2',
				'billing_company'    => 'Cycle Labs',
			)
		);

		$this->assertSame( '062.556.385-96', $data['billing_cpf'] );
		$this->assertSame( '', $data['billing_cnpj'] );
		$this->assertSame( '', $data['billing_company'] );
	}

	/**
	 * Legal persons keep the CNPJ and Razão Social, never the CPF.
	 */
	public function test_clear_document_posted_data_legal_person(): void {
		$data = LegacyCheckoutFields::clear_document_posted_data(
			array(
				'billing_persontype' => '2',
				'billing_cpf'        => '062.5',
				'billing_cnpj'       => '12.345.678/0001-95',
				'billing_company'    => 'Cycle Labs',
			)
		);

		$this->assertSame( '', $data['billing_cpf'] );
		$this->assertSame( '12.345.678/0001-95', $data['billing_cnpj'] );
		$this->assertSame( 'Cycle Labs', $data['billing_company'] );
	}

	/**
	 * A missing person type defaults to individual, like the validation.
	 */
	public function test_clear_document_posted_data_defaults_to_individual(): void {
		$data = LegacyCheckoutFields::clear_document_posted_data(
			array(
				'billing_cpf'  => '062.556.385-96',
				'billing_cnpj' => '06.2',
			)
		);

		$this->assertSame( '062.556.385-96', $data['billing_cpf'] );
		$this->assertSame( '', $data['billing_cnpj'] );
	}
}
