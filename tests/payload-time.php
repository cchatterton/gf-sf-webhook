<?php
/**
 * Regression check for Gravity Forms Time fields stored against the main field ID.
 */

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}

function rgar( $container, $key ) {
	if ( is_array( $container ) ) {
		return $container[ $key ] ?? null;
	}

	if ( is_object( $container ) ) {
		return $container->{$key} ?? null;
	}

	return null;
}

class RGFormsModel {
	public static function get_lead_field_value( $entry, $field ) {
		return $entry[ (string) $field->id ] ?? '';
	}
}

require dirname( __DIR__ ) . '/gf-sf-webhook/functions/submission.php';

$time_field = (object) array(
	'id'                     => 3,
	'type'                   => 'time',
	'salesforce_mapping_key' => 'appointment_time',
	'inputs'                 => array(
		array(
			'id'    => '3.1',
			'label' => 'Hour',
		),
		array(
			'id'    => '3.2',
			'label' => 'Minute',
		),
		array(
			'id'    => '3.3',
			'label' => 'AM/PM',
		),
	),
);
$name_field = (object) array(
	'id'                     => 4,
	'type'                   => 'name',
	'salesforce_mapping_key' => 'contact_name',
	'inputs'                 => array(
		array(
			'id'    => '4.3',
			'label' => 'First',
		),
		array(
			'id'    => '4.6',
			'label' => 'Last',
		),
	),
);
$form = array(
	'id'     => 30,
	'fields' => array( $time_field, $name_field ),
);
$entry = array(
	'id'  => 502,
	'3'   => '9:30 am',
	'4.3' => 'Danryl',
	'4.6' => 'Carpio',
);

$payload = gfsf_build_payload( $entry, $form );

if ( '9:30 am' !== ( $payload['appointment_time'] ?? null ) ) {
	fwrite( STDERR, "Time payload regression failed.\n" );
	exit( 1 );
}

$expected_name = array(
	'first' => 'Danryl',
	'last'  => 'Carpio',
);

if ( $expected_name !== ( $payload['contact_name'] ?? null ) ) {
	fwrite( STDERR, "Compound payload regression failed.\n" );
	exit( 1 );
}

echo "Time payload regression passed.\n";
