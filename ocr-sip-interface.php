<?php
/**
 * Plugin Name: OCR SIP Interface
 * Plugin URI: http://oncore.cancer.ufl.edu/sip/SIPControlServlet
 * Description: Dynamic URL for protocol summary.
 * Version: 1.0
 * Text Domain: ocr-sip-interface
 * Author: OCR
 * Copyright 2017
 */

add_action( 'wp_enqueue_scripts', 'ocr_sip_styles' );

/**
 * Custom styles.
 */
function ocr_sip_styles() {
	wp_register_style( 'ocr-sip-plugin', plugins_url( 'ocr-sip-interface/css/ocr-sip-style.css' ) );
	wp_enqueue_style( 'ocr-sip-plugin' );
}

/**
 * Listing the protocols for the selected disease site.
 *
 * @param string $disease_site_desc     The Disease Site selected.
 *
 * @return mixed|null|string
 */
function protocol_list( $disease_site_desc ) {
	$transient = get_transient( 'protocol_list_' . $disease_site_desc );
	if ( ! empty( $transient ) ) {
		return $transient;
	} else {
		$url = 'http://oncore.cancer.ufl.edu/sip/SIPControlServlet?hdn_function=SIP_PROTOCOL_LISTINGS&disease_site=' . $disease_site_desc . '';
		$out = wp_remote_get( $url );
		if ( ! is_wp_error( $out ) ) {
			$doc = new DOMDocument();
			$doc->loadHTML( $out['body'] );
			/* loading only body element */
			$body    = $doc->getElementsByTagName( 'body' );
			if ( $body && 0 < $body->length ) {
				$body = $body->item( 0 );
			}
			/* replacing the links with dynamic links */
			$anchors    = $doc->getElementsByTagName( 'a' );
			$replace_arr = array( 'javascript:displayProtocolSummary', '(', ')', '\'' );
			foreach ( $anchors as $anchor ) {
				$replaced_string = str_replace( $replace_arr, '', $anchor->getAttribute( 'href' ) );
				$protocol_id     = explode( ',', $replaced_string );
				$protocol_id     = $protocol_id[0];
				$protocol_no     = $anchor->textContent;
				$arr_params     = array(
					'protocol_id' => $protocol_id,
					'protocol_no' => $protocol_no,
				);
				$url = add_query_arg( $arr_params, wp_get_canonical_url() . 'protocol-summary ' );
				$anchor->setAttribute( 'href', $url );
			}
			$result = $doc->saveHTML( $body );
			set_transient( 'protocol_list_' . $disease_site_desc, $result, DAY_IN_SECONDS );

			return $result;
		}
		return null;
	}
}

add_filter( 'protocol_list', 'protocol_list' );

/**
 * Short code for protocol list.
 *
 * @param array|mixed $args     Receives the input from the short code.
 *
 * @return mixed|string
 */
function protocol_list_short_code( $args ) {
	if ( isset( $args['disease_site_desc'] ) ) {
		$disease_site_desc = $args['disease_site_desc'];
		$pl_request        = apply_filters( 'protocol_list', $disease_site_desc );
		if ( is_null( $pl_request ) ) {
			return '<h4>Whoops, something went wrong. Please try again!</h4>';
		}
		return $pl_request;
	} else {
		return '<h4>Please select the cancer type.</h4>';
	}
}

add_shortcode( 'protocolsList', 'protocol_list_short_code' );

/**
 * Functionality to display protocol summary.
 *
 * @param String $protocol_id   The id to the protocol.
 *
 * @param String $protocol_no   The protocol number to the the protocol.
 *
 * @return mixed|null|string
 */
function protocol_summary( $protocol_id, $protocol_no ) {
	$transient = get_transient( 'protocol_detail_' . $protocol_id );
	if ( ! empty( $transient ) ) {
		return $transient;
	} else {
		$url = 'http://oncore.cancer.ufl.edu/sip/SIPMain?hdn_function=SIP_PROTOCOL_SUMMARY&protocol_id' . $protocol_id . '&protocol_no=' . $protocol_no;
		$out = wp_remote_get( $url );
		if ( ! is_wp_error( $out ) ) {
			$doc = new DOMDocument();
			libxml_use_internal_errors( true );
			$doc->loadHTML( $out['body'] );
			$body    = $doc->getElementsByTagName( 'body' );
			if ( $body && 0 < $body->length ) {
				$body = $body->item( 0 );
			}
			$result = $doc->saveHTML( $body );
			set_transient( 'protocol_detail_' . $protocol_id, $result, DAY_IN_SECONDS );
			return $result;
		}
		return null;
	}
}

add_filter( 'protocol_summary', 'protocol_summary', 10, 2 );

/**
 * Short code for protocol details.
 *
 * @return mixed|string
 */
function protocol_summary_short_code() {
	if ( isset( $_GET['protocol_id'] ) && isset( $_GET['protocol_no'] ) ) {
		$protocol_id = sanitize_text_field( wp_unslash( $_GET['protocol_id'] ) );
		$protocol_no = sanitize_text_field( wp_unslash( $_GET['protocol_no'] ) );
		$pd_request  = apply_filters( 'protocol_summary', $protocol_id, $protocol_no );
		if ( is_null( $pd_request ) ) {
			return '<h4>Whoops, something went wrong. Please try again!</h4>';
		}
		return $pd_request;
	} else {
		return '<h4>Please select a protocol</h4>';
	}
}

add_shortcode( 'protocolSummary', 'protocol_summary_short_code' );


