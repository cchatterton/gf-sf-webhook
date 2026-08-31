<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'gform_after_submission', 'gfsf_process_entry', 10, 2 );

function gfsf_process_entry( $entry, $form ) {

	$webhook_url = esc_url_raw( trim( (string) rgar( $form, 'salesforce_webhook_url' ) ) );

	if ( '' === $webhook_url || ! wp_http_validate_url( $webhook_url ) ) {
		return;
	}

	$payload = gfsf_build_payload( $entry, $form );

	GFAPI::add_note(
		rgar( $entry, 'id' ),
		0,
		'GF SF Webhook',
		"Payload Ready:\n\n" . wp_json_encode( $payload, JSON_PRETTY_PRINT )
	);

	gfsf_send_webhook( $webhook_url, $payload, $entry, $form );
}

function gfsf_build_payload( $entry, $form ) {

	$payload = array(
		'gf_entry_id' => rgar( $entry, 'id' ),
		'gf_form_id'  => rgar( $form, 'id' ),
	);

	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $payload;
	}

	foreach ( $form['fields'] as $field ) {

		$key = gfsf_get_payload_key( $field );

		if ( $key === '' ) {
			continue;
		}

		/*
		|--------------------------------------------------------------------------
		| Checkbox fields → array of selected values
		|--------------------------------------------------------------------------
		*/
		if ( isset( $field->type ) && 'checkbox' === $field->type && ! empty( $field->inputs ) && is_array( $field->inputs ) ) {

			$selected = array();

			foreach ( $field->inputs as $input ) {

				$input_id = isset( $input['id'] ) ? (string) $input['id'] : '';

				if ( '' === $input_id ) {
					continue;
				}

				$value = rgar( $entry, $input_id );

				if ( $value !== '' && $value !== null ) {
					$selected[] = $value;
				}
			}

			if ( ! empty( $selected ) ) {
				$payload[ $key ] = $selected;
			}

			continue;
		}

		/*
		|--------------------------------------------------------------------------
		| Compound fields (Name, Address, etc.)
		|--------------------------------------------------------------------------
		*/
		if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {

			$sub_payload = array();

			foreach ( $field->inputs as $input ) {

				$input_id = isset( $input['id'] ) ? (string) $input['id'] : '';

				if ( $input_id === '' ) {
					continue;
				}

				$value = rgar( $entry, $input_id );

				if ( $value === '' || $value === null ) {
					continue;
				}

				$sub_key = gfsf_get_input_key( $input );

				if ( $sub_key === '' ) {
					$sub_key = $input_id;
				}

				$sub_payload[ $sub_key ] = $value;
			}

			if ( ! empty( $sub_payload ) ) {
				$payload[ $key ] = $sub_payload;
				continue;
			}
		}

		/*
		|--------------------------------------------------------------------------
		| Standard fields
		|--------------------------------------------------------------------------
		*/
		$value = RGFormsModel::get_lead_field_value( $entry, $field );

		if ( is_array( $value ) ) {

			$value = array_filter(
				$value,
				function ( $item ) {
					return $item !== '' && $item !== null;
				}
			);

			if ( empty( $value ) ) {
				continue;
			}

			if ( count( $value ) === 1 ) {
				$value = reset( $value );
			}
		}

		if ( $value === '' || $value === null || $value === array() ) {
			continue;
		}

		$payload[ $key ] = $value;
	}

	return $payload;
}

function gfsf_get_payload_key( $field ) {

	// Salesforce mapping keys are preserved to match the receiving integration.
	$mapping_key = trim( (string) rgar( $field, 'salesforce_mapping_key' ) );
	if ( $mapping_key !== '' ) {
		return $mapping_key;
	}

	// Admin labels are preserved because they can also be integration keys.
	$admin_label = trim( (string) rgar( $field, 'adminLabel' ) );
	if ( $admin_label !== '' ) {
		return $admin_label;
	}

	// Human-facing field labels are normalised for a predictable fallback key.
	$label = trim( (string) rgar( $field, 'label' ) );
	if ( $label !== '' ) {
		return gfsf_normalize_key( $label );
	}

	// The field ID is the final stable fallback.
	return (string) $field->id;
}

function gfsf_get_input_key( $input ) {

	// Compound sub-fields use normalised labels as keys.
	if ( ! empty( $input['customLabel'] ) ) {
		return gfsf_normalize_key( $input['customLabel'] );
	}

	if ( ! empty( $input['label'] ) ) {
		return gfsf_normalize_key( $input['label'] );
	}

	if ( ! empty( $input['name'] ) ) {
		return gfsf_normalize_key( $input['name'] );
	}

	if ( ! empty( $input['id'] ) ) {
		return (string) $input['id'];
	}

	return '';
}

function gfsf_normalize_key( $string ) {

	$string = strtolower( (string) $string );
	$string = preg_replace( '/[^a-z0-9\s]/', '', $string );
	$string = preg_replace( '/\s+/', '_', $string );
	$string = preg_replace( '/_+/', '_', $string );
	$string = trim( $string, '_' );

	return $string;
}
