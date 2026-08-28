<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Field setting: Salesforce Mapping Key
|--------------------------------------------------------------------------
*/
add_action( 'gform_field_advanced_settings', function( $position ) {
	if ( (int) $position !== 100 ) {
		return;
	}
	?>
	<li class="salesforce_mapping_key_setting field_setting">
		<label for="field_salesforce_mapping_key" class="section_label" style="margin-top:0.75rem; display:block;">
			Salesforce Mapping Key
		</label>
		<input
			type="text"
			id="field_salesforce_mapping_key"
			style="width:100%;"
			oninput="SetFieldProperty('salesforce_mapping_key', this.value);"
		/>
	</li>
	<?php
}, 10, 2 );

/*
|--------------------------------------------------------------------------
| Form setting: Salesforce Webhook URL
|--------------------------------------------------------------------------
*/
add_filter( 'gform_form_settings_fields', function( $settings, $form ) {

	$settings['salesforce'] = array(
		'title'  => 'Salesforce',
		'fields' => array(
			array(
				'name'  => 'salesforce_webhook_url',
				'label' => 'Salesforce Webhook URL',
				'type'  => 'text',
				'class' => 'large',
			),
		),
	);

	return $settings;

}, 10, 2 );

/*
|--------------------------------------------------------------------------
| Editor JS
|--------------------------------------------------------------------------
*/
add_action( 'gform_editor_js', function() {
?>
<script>

jQuery(function($){

	/*
	|--------------------------------------------------------------------------
	| Attach our setting to ALL field types
	|--------------------------------------------------------------------------
	*/

	if (typeof fieldSettings === 'object') {

		Object.keys(fieldSettings).forEach(function(type) {

			if (typeof fieldSettings[type] === 'string') {

				if (!fieldSettings[type].includes('.salesforce_mapping_key_setting')) {
					fieldSettings[type] += ', .salesforce_mapping_key_setting';
				}

			}

		});

	}

	/*
	|--------------------------------------------------------------------------
	| Load value when field settings open
	|--------------------------------------------------------------------------
	*/

	$(document).on('gform_load_field_settings', function(event, field) {

		$('#field_salesforce_mapping_key').val(
			field.salesforce_mapping_key || ''
		);

	});

});

</script>
<?php
});
