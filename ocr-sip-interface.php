<?php
/**
 * Plugin Name: OCR SIP Interface
 * Plugin URI: http://oncore.cancer.ufl.edu/sip/SIPControlServlet
 * Description: Dynamic URL for protocol summary.
 * Version: 1.7
 * Text Domain: ocr-sip-interface
 * Contact: oncore-support@ahc.ufl.edu
 * Author: OCR
 */

define( 'OCR_PLUGIN_DIR', __DIR__ );

require_once OCR_PLUGIN_DIR . '/models/protocol.php';

require_once OCR_PLUGIN_DIR . '/models/clinical-trial.php';

require_once OCR_PLUGIN_DIR . '/ocr-sip-interface-config.php';

/**
 * Listing the protocols for the selected disease site.
 *
 * @param string $disease_site_desc The Disease Site selected.
 *
 * @return mixed|null|string
 */
function protocol_list( $disease_site_desc ) {
	$transient = get_transient( 'protocol_list_' . $disease_site_desc );
	if ( false !== $transient ) {
		return $transient;
	} else {
		$protocol_list_str = build_protocol_list( $disease_site_desc );
		if ( strpos( $protocol_list_str, 'scheduled maintenance' ) ) {
			return $protocol_list_str;
		}
		if ( ! empty( $protocol_list_str ) ) {
			$result = wp_kses_post( $protocol_list_str );
			if ( ! empty( $result ) ) {
				set_transient( 'protocol_list_' . $disease_site_desc, $result, DAY_IN_SECONDS );
				return $result;
			}
		}
	}
}

/**
 * Builds the protocol list content
 *
 * @param string $disease_site_desc The Disease Site selected.
 *
 * @return mixed|string
 */
function build_protocol_list( $disease_site_desc ) {
	$config_inst       = new OcrSipInterfaceConfig();
	$url               = esc_url_raw( $config_inst->protocol_list_url . $disease_site_desc . '&format=xml' );
	$out               = wp_remote_get( $url );
	$protocol_list_str = '';
	if ( ! is_wp_error( $out ) ) {
		$xml_doc = new DOMDocument();
		$xml_doc->loadXML( $out['body'] );
		if ( ! is_null( $xml_doc->getElementsByTagName( 'count' ) ) ) {
			$info_text = $xml_doc->getElementsByTagName( 'count' )->item( 0 )->textContent . ' ' . $config_inst->count_msg;
			if ( ! empty( $info_text ) ) {
				$protocol_list_str .= '<h4>' . $info_text . '</h4>';
			}
		}
		if ( ! is_null( $xml_doc->getElementsByTagName( 'protocol' ) ) ) {
			foreach ( $xml_doc->getElementsByTagName( 'protocol' ) as $row ) {
				$protocol_list_str .= '<dl>';
				$protocol_id        = $row->getElementsByTagName( 'id' )->item( 0 )->textContent;
				$protocol_no        = $row->getElementsByTagName( 'no' )->item( 0 )->textContent;
				$protocol_title     = $row->getElementsByTagName( 'title' )->item( 0 )->textContent;
				$arr_params         = array(
					'protocol_id' => $protocol_id,
					'protocol_no' => $protocol_no,
				);
				$url                = add_query_arg( $arr_params, wp_get_canonical_url() . 'protocol-summary' );
				$anchor             = '<a href=\'' . $url . '\'>' . $protocol_no . '</a>';
				$protocol_list_str .= '<dt>' . $anchor . '</dt>';
				$protocol_list_str .= '<p>' . $protocol_title . '</p>';
				$protocol_list_str .= '</dl>';
			}
		}
		return $protocol_list_str;
	}
	return $config_inst->maintanance_msg;
}

/**
 * Short code for protocol list.
 *
 * @param array|mixed $args Receives the input from the short code.
 *
 * @return mixed|string
 */
function protocol_list_short_code( $args ) {
	$config_inst = new OcrSipInterfaceConfig();
	if ( isset( $args['disease_site_desc'] ) ) {
		$disease_site_desc = $args['disease_site_desc'];
		$pl_request        = protocol_list( $disease_site_desc );
		if ( is_null( $pl_request ) ) {
			return '<h4>' . $config_inst->warning_message . '</h4>';
		}
		return $pl_request;
	} else {
		return '<h4>' . $config_inst->protocol_listing_wm . '</h4>';
	}
}

add_shortcode( 'protocolsList', 'protocol_list_short_code' );

/**
 * Functionality to display protocol summary.
 *
 * @param string $protocol_id internal id of the protocol selected.
 * @param string $protocol_no OCR# of the protocol selected.
 *
 * @return mixed|null|string
 */
function protocol_summary( $protocol_id, $protocol_no ) {
	$config_inst = new OcrSipInterfaceConfig();
	$transient   = get_transient( 'protocol_detail_' . $protocol_id );
	if ( false !== $transient ) {
		return $transient;
	} else {
		$clinical_trial = new Clinical_Trail();
		$protocol       = build_protocol_obj( $protocol_id, $protocol_no );
		if ( ! is_null( $protocol ) ) {
			if ( $protocol->is_active ) {
				if ( ! is_null( $protocol->nct_id ) && ! empty( $protocol->nct_id ) ) {
					$clinical_trial = build_clinical_trial_obj( $protocol->nct_id );
				}
				$protocol_summary_str = build_protocol_summary( $protocol, $clinical_trial );
				$result               = wp_kses_post( $protocol_summary_str );
				// oncore sip api null pointers.
				if ( strpos( $result, 'java.lang.NullPointerException' ) ) {
					return null;
				}
				if ( ! empty( $result ) ) {
					set_transient( 'protocol_detail_' . $protocol_id, $result, DAY_IN_SECONDS );
					return $result;
				}
				return null;
			}
			return $config_inst->maintanance_msg;
		}
	}
}


/**
 * Short code for protocol details.
 *
 * @return mixed|string
 */
function protocol_summary_short_code() {
	$config_inst = new OcrSipInterfaceConfig();
	if ( isset( $_GET['protocol_id'] ) && isset( $_GET['protocol_no'] ) ) {
		$protocol_id = sanitize_text_field( wp_unslash( $_GET['protocol_id'] ) );
		$protocol_no = sanitize_text_field( wp_unslash( $_GET['protocol_no'] ) );
		$pd_request  = protocol_summary( $protocol_id, $protocol_no );
		if ( is_null( $pd_request ) ) {
			return '<h4>' . $config_inst->warning_message . '</h4>';
		}
		return $pd_request;
	} else {
		return '<h4>' . $config_inst->protocol_listing_wm . '</h4>';
	}
}

add_shortcode( 'protocolSummary', 'protocol_summary_short_code' );

/**
 * Build the protocol summary text content
 *
 * @param Protocol      $protocol       is a protocol objects.
 * @param ClinicalTrail $clinical_trial is a clinicalTrial object.
 *
 * @return mixed|null|string
 */
function build_protocol_summary( $protocol, $clinical_trial ) {
	$config_inst = new OcrSipInterfaceConfig();
	if ( ! is_null( $protocol->protocol_no ) ) {
		$protocol_summary = '';
		if ( ! is_null( $protocol->protocol_no ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->protocol_label . '</strong>' . $protocol->protocol_no . '</p>';
		}
		if ( ! is_null( $protocol->secondary_protocol_no ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->sponsor_label . '</strong>' . $protocol->secondary_protocol_no . '</p>';
		}
		$title = ! empty( $protocol->protocol_title ) ? $protocol->protocol_title : $clinical_trial->title;
		if ( ! empty( $title ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->protocol_title_label . '</strong>' . $title . '</p>';
		}
		if ( ! is_null( $protocol->protocol_pi ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->pi_label . '</strong>' . $protocol->protocol_pi . '</p>';
		}
		$objective = ! empty( $protocol->protocol_objective ) ? $protocol->protocol_objective : $clinical_trial->objective;
		if ( ! empty( $objective ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->object_label . '</strong>' . $objective . '</p>';
		}
		$description = ! empty( $protocol->lay_description ) ? $protocol->lay_description : $clinical_trial->detailed_description;
		if ( ! empty( $description ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->description_label . '</strong>' . $description . '</p>';
		}
		$phase = ! empty( $protocol->protocol_phase ) ? $protocol->protocol_phase : $clinical_trial->phase;
		if ( ! empty( $phase ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->phase_label . '</strong>' . $phase . '</p>';
		}
		if ( ! is_null( $protocol->protocol_age ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->age_group_label . '</strong>' . $protocol->protocol_age . '</p>';
		}
		if ( ! is_null( $clinical_trial->maximum_age ) && ! is_null( $clinical_trial->minimum_age ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->age_label . '</strong>' . $clinical_trial->minimum_age . ' - ' . $clinical_trial->maximum_age . '</p>';
		}
		if ( ! is_null( $clinical_trial->gender ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->gender_label . '</strong>' . $clinical_trial->gender . '</p>';
		}
		if ( ! is_null( $protocol->protocol_scope ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->scope_label . '</strong>' . $protocol->protocol_scope . '</p>';
		}
		$treatment = ! empty( $protocol->treatment ) ? $protocol->treatment : $clinical_trial->treatment;
		if ( ! empty( $treatment ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->treatment_label . '</strong>' . $treatment . '</p>';
		}

		$detailed_elig = ! empty( $protocol->detail_elig ) ? $protocol->detail_elig : $clinical_trial->detailed_eligibility;
		if ( ! empty( $detailed_elig ) ) {
			$detailed_elig     = html_entity_decode( $detailed_elig );
			$protocol_summary .= '<p><strong>' . $config_inst->elig_label . '</strong>' . $detailed_elig . '</p>';
		}
		if ( ! is_null( $protocol->disease_site ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->conditions_label . '</strong>' . $protocol->disease_site . '</p>';
		}
		if ( ! is_null( $protocol->protocol_institution ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->inst_label . '</strong>' . $protocol->protocol_institution . '</p>';
		}
		if ( ! is_null( $protocol->contact ) ) {
			$protocol_summary .= '<p>' . $protocol->contact . '</p>';
		}
		if ( ! is_null( $protocol->nct_url ) ) {
			$protocol_summary .= '<p><strong>' . $config_inst->more_info_label . '</strong>' . $config_inst->ctgov_label . ' 
                                    <a target=\'_blank\' href=\'' . $protocol->nct_url . '\'/>' . $protocol->nct_url . '</p>';
		}
		return $protocol_summary;
	}
	return null;
}

/**
 * Build the protocol object
 *
 * @param string $protocol_id The id to the protocol.
 * @param string $protocol_no The protocol number to the the protocol.
 *
 * @return Protocol
 */
function build_protocol_obj( $protocol_id, $protocol_no ) {
	$config_inst  = new OcrSipInterfaceConfig();
	$protocol_obj = new Protocol();
	$url          = esc_url_raw( $config_inst->protocol_summary_url . $protocol_id . '&protocol_no=' . $protocol_no . '&format=xml' );
	$out          = wp_remote_get( $url );
	if ( ! is_wp_error( $out ) ) {
		$protocol_obj->is_active = true;
		$xml_doc                 = new DOMDocument();
		$xml_doc->loadXML( $out['body'] );
		if ( ! is_null( $xml_doc->getElementsByTagName( 'protocol' ) ) ) {
			$protocol_temp = $xml_doc->getElementsByTagName( 'protocol' )->item( 0 );
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'no' ) ) ) {
				$protocol_obj->protocol_no = $protocol_temp->getElementsByTagName( 'no' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'title' ) ) ) {
				$protocol_obj->protocol_title = $protocol_temp->getElementsByTagName( 'title' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'PI' ) ) ) {
				$protocol_obj->protocol_pi = $protocol_temp->getElementsByTagName( 'PI' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'objective' ) ) ) {
				$protocol_obj->protocol_objective = $protocol_temp->getElementsByTagName( 'objective' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'treatment' ) ) ) {
				$protocol_obj_treatment_tmp = $protocol_temp->getElementsByTagName( 'treatment' )->item( 0 )->textContent;
				if ( ! empty( $protocol_obj_treatment_tmp ) ) {
					$arrs = explode( PHP_EOL, $protocol_obj_treatment_tmp );
					foreach ( $arrs as $arr ) {
						$protocol_obj->treatment .= '</br>' . $arr;
					}
				}
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'lay_description' ) ) ) {
				$protocol_obj->lay_description = $protocol_temp->getElementsByTagName( 'lay_description' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'phase' ) ) ) {
				$protocol_obj->protocol_phase = $protocol_temp->getElementsByTagName( 'phase' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'age_group' ) ) ) {
				$protocol_obj->protocol_age = $protocol_temp->getElementsByTagName( 'age_group' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'scope' ) ) ) {
				$protocol_obj->protocol_scope = $protocol_temp->getElementsByTagName( 'scope' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'secondary_protocol_no' ) ) ) {
				$protocol_obj->secondary_protocol_no = $protocol_temp->getElementsByTagName( 'secondary_protocol_no' )->item( 0 )->textContent;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'detailed_eligibility' ) ) ) {
				$detail_elig_temp = $protocol_temp->getElementsByTagName( 'detailed_eligibility' )->item( 0 )->textContent;
				if ( ! empty( $detail_elig_temp ) ) {
					$arrs = explode( PHP_EOL, $detail_elig_temp );
					foreach ( $arrs as $arr ) {
						$protocol_obj->detail_elig .= '</br>';
						$protocol_obj->detail_elig .= $arr;
					}
				}
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'disease_site' ) ) ) {
				$disease_site_temp = $protocol_temp->getElementsByTagName( 'disease_site' )->item( 0 )->textContent;
				$arrs              = explode( ';', $disease_site_temp );
				foreach ( $arrs as $arr ) {
					$protocol_obj->disease_site .= '<li>' . $arr . '</li>';
				}
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'institution' ) ) ) {
				$inst_temp = $protocol_temp->getElementsByTagName( 'institution' )->item( 0 )->textContent;
				$arrs      = explode( ';', $inst_temp );
				foreach ( $arrs as $arr ) {
					$protocol_obj->protocol_institution .= '<li>' . $arr . '</li>';
				}
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'item' ) ) && ! is_null( $protocol_temp->getElementsByTagName( 'item' )->item( 0 ) ) ) {
				$contact_str  = '<Strong>Contact:</Strong>';
				$contact_temp = $protocol_temp->getElementsByTagName( 'item' )->item( 0 );
				if ( ! is_null( $contact_temp->getElementsByTagName( 'item_description' ) ) ) {
					$contact_name = $contact_temp->getElementsByTagName( 'item_description' )->item( 0 )->textContent;
				}
				if ( ! is_null( $contact_temp->getElementsByTagName( 'phone_no' ) ) ) {
					$contact_phone = $contact_temp->getElementsByTagName( 'phone_no' )->item( 0 )->textContent;
				}
				if ( ! is_null( $contact_temp->getElementsByTagName( 'email' ) ) ) {
					$contact_email = $contact_temp->getElementsByTagName( 'email' )->item( 0 )->textContent;
				}
				$contact_str .= '</br>' . $contact_name . '';
				if ( ! empty( $contact_phone ) ) {
					$contact_str .= '</br>Phone: <a href=\'tel:' . $contact_phone . '\'> ' . $contact_phone . '</a>';
				}
				if ( ! empty( $contact_email ) ) {
					$contact_str .= '<br/> Email: <a href=\'mailto:' . $contact_email . '\'>' . $contact_email . '</a>';
				}
				$protocol_obj->contact = $contact_str;
			}
			if ( ! is_null( $protocol_temp->getElementsByTagName( 'nct_id' ) ) ) {
				$protocol_obj->nct_id  = $protocol_temp->getElementsByTagName( 'nct_id' )->item( 0 )->textContent;
				$protocol_obj->nct_url = 'http://www.clinicaltrials.gov/ct2/show/' . $protocol_obj->nct_id;
			}
		}
	} else {
		$protocol_obj->is_active = false;
	}
	return $protocol_obj;
}

/**
 * Build the clinicalTrial object
 *
 * @param string $nct_id The id to the sponsor.
 *
 * @return ClinicalTrail
 */
function build_clinical_trial_obj( $nct_id ) {
	$clinical_trial_obj = new Clinical_Trail();
	$url                = esc_url_raw( 'https://clinicaltrials.gov/ct2/show/' . $nct_id . '?displayxml=true' );
	$out                = wp_remote_get( $url );
	if ( ! is_wp_error( $out ) ) {
		$xml_doc = new DOMDocument();
		$xml_doc->loadXML( $out['body'] );

		if ( ! is_null( $xml_doc->getElementsByTagName( 'brief_title' ) ) ) {
			$clinical_trial_obj->title = $xml_doc->getElementsByTagName( 'brief_title' )->item( 0 )->textContent;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'brief_summary' ) ) ) {
			$clinical_trial_obj->objective = $xml_doc->getElementsByTagName( 'brief_summary' )->item( 0 )->textContent;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'phase' ) ) ) {
			$clinical_trial_obj->phase = $xml_doc->getElementsByTagName( 'phase' )->item( 0 )->textContent;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'detailed_description' ) ) ) {
			if ( ! is_null( $xml_doc->getElementsByTagName( 'detailed_description' )->item( 0 ) ) ) {
				$clinical_trial_obj->detailed_description = esc_html(
					$xml_doc->getElementsByTagName( 'detailed_description' )
						->item( 0 )->getElementsByTagName( 'textblock' )->item( 0 )->textContent
				);
			}
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'arm_group' ) ) ) {
			$study_arm_str = '';
			foreach ( $xml_doc->getElementsByTagName( 'arm_group' ) as $arm_group ) {
				$study_arm_str .= '<p>';
				$study_arm_str .= $arm_group->getElementsByTagName( 'arm_group_type' )->item( 0 )->textContent . ': ' . $arm_group->getElementsByTagName( 'arm_group_label' )->item( 0 )->textContent;
				$study_arm_str .= '</br>' . $arm_group->getElementsByTagName( 'description' )->item( 0 )->textContent;
				$study_arm_str .= '</p>';
			}
			$clinical_trial_obj->treatment = $study_arm_str;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'eligibility' )->item( 0 ) ) ) {
			$clinical_detailed_eligibility =
				esc_html( $xml_doc->getElementsByTagName( 'eligibility' )->item( 0 )->getElementsByTagName( 'textblock' )->item( 0 )->textContent );
			$arrs                          = explode( PHP_EOL, $clinical_detailed_eligibility );
			$next_line                     = true;
			foreach ( $arrs as $arr ) {
				if ( $next_line ) {
					$clinical_trial_obj->detailed_eligibility .= '</br>';
				}
				$clinical_trial_obj->detailed_eligibility .= $arr;
				if ( empty( $arr ) ) {
					$next_line = true;
				} else {
					$next_line = false;
				}
			}
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'gender' ) ) ) {
			$clinical_trial_obj->gender = $xml_doc->getElementsByTagName( 'gender' )->item( 0 )->textContent;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'minimum_age' ) ) ) {
			$clinical_trial_obj->minimum_age = $xml_doc->getElementsByTagName( 'minimum_age' )->item( 0 )->textContent;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'maximum_age' ) ) ) {
			$clinical_trial_obj->maximum_age = $xml_doc->getElementsByTagName( 'maximum_age' )->item( 0 )->textContent;
		}
		return $clinical_trial_obj;
	}
	return null;
}

