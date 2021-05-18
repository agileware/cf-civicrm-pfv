<?php
/* Copyright (C) Agileware
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Plugin Name: Caldera Forms CiviCRM Price Field Value
 * Description: A Caldera Forms Processor for integration with CiviCRM which converts a Price Field value into Magic Tags, including: amount, amount_no_tax, tax, label, financial_type.
 * Version: 1.1.0
 * Author: Agileware
 * Author URI: https://agileware.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cf-civicrm-pfv
 */

namespace CF_CiviCRM_PFV;

/**
 * Calback to register the processors with Caldera Forms.
 */
function register ( $processors ) {
	/* Price field Value processor */
	$processors['price_field_value'] = [
		'name' => __( 'CiviCRM Price Field Value', 'cf-civicrm-pfv' ),
		'description' => __( 'Lookup the monetary amount of a price field value', 'cf-civicrm-pfv' ),
		'author' => 'Agileware',
		'template' => \plugin_dir_path( __FILE__ ) . '/config.php',
		'pre_processor' => 'CF_CiviCRM_PFV\pre_processor',
		'magic_tags' => tag_list(),
	];

	return $processors;
}

function tag_list ( ) {
	$tags = array_flip([ 'amount', 'amount_no_tax', 'tax', 'label', 'financial_type' ]);

	if(\civicrm_initialize()) {
		$tags += array_flip(array_keys(civicrm_api3('PriceFieldValue', 'getfields', [ 'action' => 'get' ])['values']));
	}

	$tags = array_keys($tags);

	return $tags;
}

/**
 * Price Field Value preprocessor.
 */
function pre_processor ( $config, $form ) {
	$data = new \Caldera_Forms_Processor_Get_Data( $config, $form, fields() );

	try {
		$tax_rate = \CRM_Core_PseudoConstant::getTaxRates();

		$pfv = \civicrm_api3('PriceFieldValue', 'getsingle', ['id' => $data->get_value('pfv_id')]);

		$amount = $pfv['amount'];

		if (isset($tax_rate[$pfv['financial_type_id']])) {
			$tax_amount = $amount * $tax_rate[$pfv['financial_type_id']] / 100;
		}

		// Loop through the stored results and set the magic tags from the output.
		\Caldera_Forms::set_submission_meta('amount', \sprintf('%.2f', $amount + $tax_amount), $form, $config['processor_id']);
		\Caldera_Forms::set_submission_meta('amount_no_tax', \sprintf('%.2f', $amount), $form, $config['processor_id']);
		\Caldera_Forms::set_submission_meta('tax', \sprintf('%.2f', $tax_amount), $form, $config['processor_id']);
		\Caldera_Forms::set_submission_meta('label', $pfv['label'], $form, $config['processor_id']);
		\Caldera_Forms::set_submission_meta('financial_type', \CRM_Contribute_PseudoConstant::financialType($pfv['financial_type_id']), $form, $config['processor_id']);

		foreach($pfv as $key => $value) {
			if (!in_array($key, [ 'amount', 'amount_no_tax', 'tax', 'label', 'financial_type' ])) {
				\Caldera_Forms::set_submission_meta($key, $value, $form, $config['processor_id']);
			}
		}
	}
	catch(CiviCRM_API3_Exception $e) {
		return [
			'note' => $e->getMessage(),
			'type' => 'error'
		];
	};
}

/**
 * Fields used for configuring the CiviCRM Price Field Value processor
 */
function fields () {
	return [
		[
			'id'       => 'pfv_id',
			'type'     => 'text',
			'required' => true,
			'magic'    => true,
			'label'    => __( 'Price Field Value ID', 'cf-civicrm-pfv' ),
			'desc'     => __( 'Use a Magic Tag or number to refer to the CiviCRM Price Field Option. This must return a valid ID for a single Option from a CiviCRM Price Field.', 'cf-civicrm-pfv' ),
		],
	];
}

// Add our register callback to Caldera Forms.
\add_filter( 'caldera_forms_get_form_processors', 'CF_CiviCRM_PFV\register' );
