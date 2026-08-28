<?php
/**
 * Regression check for Gravity Forms checkbox input IDs.
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

$input_numbers = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14, 15, 16 );
$inputs        = array();
$choices       = array();

foreach ( $input_numbers as $position => $input_number ) {
	$label     = 14 === $position ? 'Low vision' : 'Choice ' . ( $position + 1 );
	$inputs[]  = array(
		'id'    => '12.' . $input_number,
		'label' => $label,
	);
	$choices[] = array(
		'text'  => $label,
		'value' => $label,
	);
}

$field = (object) array(
	'id'                     => 12,
	'type'                   => 'checkbox',
	'salesforce_mapping_key' => 'health_issues',
	'choices'                => $choices,
	'inputs'                 => $inputs,
);
$form  = array(
	'id'     => 30,
	'fields' => array( $field ),
);
$entry = array(
	'id'    => 501,
	'12.1'  => 'Hearing impairment',
	'12.16' => 'Low vision',
);

$payload = gfsf_build_payload( $entry, $form );
$expected = array( 'Hearing impairment', 'Low vision' );

if ( $expected !== ( $payload['health_issues'] ?? null ) ) {
	fwrite( STDERR, "Checkbox payload regression failed.\n" );
	exit( 1 );
}

echo "Checkbox payload regression passed.\n";
