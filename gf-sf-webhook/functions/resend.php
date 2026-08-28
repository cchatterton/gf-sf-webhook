<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gform_entry_detail_meta_boxes', 'gfsf_add_entry_meta_box', 10, 3 );

function gfsf_add_entry_meta_box( $meta_boxes, $entry, $form ) {
	if ( ! current_user_can( 'gravityforms_edit_entries' ) ) {
		return $meta_boxes;
	}

	$meta_boxes['gfsf_resend_payload'] = array(
		'title'    => 'Salesforce Webhook',
		'callback' => 'gfsf_render_entry_meta_box',
		'context'  => 'side',
	);

	return $meta_boxes;
}

function gfsf_render_entry_meta_box( $args ) {
	if ( ! current_user_can( 'gravityforms_edit_entries' ) ) {
		return;
	}

	$entry = $args['entry'];
	$form  = $args['form'];

	$webhook_url = trim( (string) rgar( $form, 'salesforce_webhook_url' ) );

	if ( $webhook_url === '' ) {
		echo '<p>No Salesforce Webhook URL is configured for this form.</p>';
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=gfsf_resend_payload&entry_id=' . absint( $entry['id'] ) ),
		'gfsf_resend_payload_' . absint( $entry['id'] )
	);

	echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary">Resend Payload</a></p>';
}

add_action( 'admin_post_gfsf_resend_payload', 'gfsf_handle_resend_payload' );

function gfsf_handle_resend_payload() {
	if ( ! current_user_can( 'gravityforms_edit_entries' ) ) {
		wp_die(
			esc_html__( 'You are not allowed to resend this payload.', 'gf-sf-webhook' ),
			esc_html__( 'Permission denied', 'gf-sf-webhook' ),
			array( 'response' => 403 )
		);
	}

	$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;

	if ( ! $entry_id ) {
		wp_die(
			esc_html__( 'Invalid entry ID.', 'gf-sf-webhook' ),
			esc_html__( 'Invalid request', 'gf-sf-webhook' ),
			array( 'response' => 400 )
		);
	}

	check_admin_referer( 'gfsf_resend_payload_' . $entry_id );

	$entry = GFAPI::get_entry( $entry_id );

	if ( is_wp_error( $entry ) ) {
		wp_die(
			esc_html__( 'Entry not found.', 'gf-sf-webhook' ),
			esc_html__( 'Not found', 'gf-sf-webhook' ),
			array( 'response' => 404 )
		);
	}

	$form = GFAPI::get_form( $entry['form_id'] );

	if ( empty( $form ) ) {
		wp_die(
			esc_html__( 'Form not found.', 'gf-sf-webhook' ),
			esc_html__( 'Not found', 'gf-sf-webhook' ),
			array( 'response' => 404 )
		);
	}

	GFAPI::add_note(
		$entry_id,
		get_current_user_id(),
		'GF SF Webhook',
		'🔁 Payload manually resent from entry screen.'
	);

	gfsf_process_entry( $entry, $form );

	wp_safe_redirect(
		admin_url( 'admin.php?page=gf_entries&view=entry&id=' . absint( $form['id'] ) . '&lid=' . absint( $entry_id ) )
	);
	exit;
}
