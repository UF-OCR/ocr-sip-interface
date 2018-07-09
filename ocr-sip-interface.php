<?php
/**
 * Plugin Name: OCR SIP Interface
 * Plugin URI: http://oncore.cancer.ufl.edu/sip/SIPControlServlet
 * Description: Dynamic URL for protocol summary.
 * Version: 1.3
 * Text Domain: ocr-sip-interface
 * Contact: oncore-support@ahc.ufl.edu
 * Author: OCR
 */


define( 'OCR_PLUGIN_DIR', __DIR__ );

require_once OCR_PLUGIN_DIR . '/models/protocol.php';

require_once OCR_PLUGIN_DIR . '/models/clinical-trial.php';


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
	$url               = esc_url_raw( 'http://oncore.cancer.ufl.edu/sip/SIPControlServlet?hdn_function=SIP_PROTOCOL_LISTINGS&disease_site=' . $disease_site_desc );
	$out               = wp_remote_get( $url );
	$replace_arr       = array( 'javascript:displayProtocolSummary', '(', ')', '\'' );
	$protocol_list_str = '';
	if ( ! is_wp_error( $out ) ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $out['body'] );
		$info_text = $doc->getElementById( 'infoText' )->textContent;
		if ( ! empty( $info_text ) ) {
			$protocol_list_str .= '<h4>' . $info_text . '</h4>';
		}
		if ( ! is_null( $doc->getElementById( 'listInfo' ) ) ) {
			foreach ( $doc->getElementById( 'listInfo' )->childNodes as $row ) {
				$row_childs = $row->childNodes;
				if ( is_object( $row_childs ) ) {
					foreach ( $row_childs as $row_child ) {
						$protocol_list_str .= '<dl>';
						if ( $row_child->getAttribute( 'id' ) === 'protocolList' ) {
							$replaced_string    = str_replace( $replace_arr, '', $row_child->childNodes->item( 0 )->childNodes->item( 0 )->getAttribute( 'href' ) );
							$protocol_id        = explode( ',', $replaced_string );
							$protocol_id        = $protocol_id[0];
							$protocol_no        = $row_child->childNodes->item( 0 )->textContent;
							$protocol_title     = $row_child->childNodes->item( 1 )->textContent;
							$arr_params         = array(
								'protocol_id' => $protocol_id,
								'protocol_no' => $protocol_no,
							);
							$url                = add_query_arg( $arr_params, wp_get_canonical_url() . 'protocol-summary' );
							$anchor             = '<a href=\'' . $url . '\'>' . $protocol_no . '</a>';
							$protocol_list_str .= '<dt>' . $anchor . '</dt>';
							$protocol_list_str .= '<p>' . $protocol_title . '</p>';
						}
						$protocol_list_str .= '</dl>';
					}
				}
			}
		}
		return $protocol_list_str;
	}
	return '<h3>OnCore is currently undergoing scheduled maintenance.</h3><h4>We\'ll be back soon.</br></br> Sorry for the inconvenience.</h4>';
}

/**
 * Short code for protocol list.
 *
 * @param array|mixed $args Receives the input from the short code.
 *
 * @return mixed|string
 */
function protocol_list_short_code( $args ) {
	if ( isset( $args['disease_site_desc'] ) ) {
		$disease_site_desc = $args['disease_site_desc'];
		$pl_request        = protocol_list( $disease_site_desc );
		if ( is_null( $pl_request ) ) {
			return '<h4>Whoops, something went wrong. Please try again!</h4>';
		}
		return $pl_request;
	} else {
		return '<h4>Choose a cancer type to find the protocol listing.</h4>';
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
	$transient = get_transient( 'protocol_detail_' . $protocol_id );
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
			return '<h3>OnCore is currently undergoing scheduled maintenance.</h3><h4>We\'ll be back soon.</br></br> Sorry for the inconvenience.</h4>';
		}
	}
}


/**
 * Short code for protocol details.
 *
 * @return mixed|string
 */
function protocol_summary_short_code() {
	if ( isset( $_GET['protocol_id'] ) && isset( $_GET['protocol_no'] ) ) {
		$protocol_id = sanitize_text_field( wp_unslash( $_GET['protocol_id'] ) );
		$protocol_no = sanitize_text_field( wp_unslash( $_GET['protocol_no'] ) );
		$pd_request  = protocol_summary( $protocol_id, $protocol_no );
		if ( is_null( $pd_request ) ) {
			return '<h4>Whoops, something went wrong. Please try again!</h4>';
		}
		return $pd_request;
	} else {
		return '<h4>Choose a protocol to find detailed information.</h4>';
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
	if ( ! is_null( $protocol->protocol_no ) ) {
		$protocol_summary = '';
		if ( ! is_null( $protocol->protocol_no ) ) {
			$protocol_summary .= '<p><strong>Protocol No.: </strong>' . $protocol->protocol_no . '</p>';
		}
		if ( ! is_null( $protocol->secondary_protocol_no ) ) {
			$protocol_summary .= '<p><strong>Sponsor Protocol No.: </strong>' . $protocol->secondary_protocol_no . '</p>';
		}
		$title = is_null( $protocol->protocol_title ) ? $protocol->protocol_title : $clinical_trial->title;
		if ( ! empty( $title ) ) {
			$protocol_summary .= '<p><strong>Protocol Title.: </strong>' . $title . '</p>';
		}
		if ( ! is_null( $protocol->protocol_pi ) ) {
			$protocol_summary .= '<p><strong>Principal Investigator: </strong>' . $protocol->protocol_pi . '</p>';
		}
		$objective = ! empty( $protocol->protocol_objective ) ? $protocol->protocol_objective : $clinical_trial->objective;
		if ( ! empty( $objective ) ) {
			$protocol_summary .= '<p><strong>Objective: </strong>' . $objective . '</p>';
		}
		$description = ! empty( $protocol->lay_description ) ? $protocol->lay_description : $clinical_trial->detailed_description;
		if ( ! empty( $description ) ) {
			$protocol_summary .= '<p><strong>Description: </strong>' . $description . '</p>';
		}
		$phase = ! empty( $protocol->protocol_phase ) ? $protocol->protocol_phase : $clinical_trial->phase;
		if ( ! empty( $phase ) ) {
			$protocol_summary .= '<p><strong>Phase: </strong>' . $phase . '</p>';
		}
		if ( ! is_null( $protocol->protocol_age ) ) {
			$protocol_summary .= '<p><strong>Age Group: </strong>' . $protocol->protocol_age . '</p>';
		}
		if ( ! is_null( $clinical_trial->maximum_age ) && ! is_null( $clinical_trial->minimum_age ) ) {
			$protocol_summary .= '<p><strong>Age: </strong>' . $clinical_trial->minimum_age . ' - ' . $clinical_trial->maximum_age . '</p>';
		}
		if ( ! is_null( $clinical_trial->gender ) ) {
			$protocol_summary .= '<p><strong>Gender: </strong>' . $clinical_trial->gender . '</p>';
		}
		if ( ! is_null( $protocol->protocol_scope ) ) {
			$protocol_summary .= '<p><strong>Scope: </strong>' . $protocol->protocol_scope . '</p>';
		}
		$treatment = ! empty( $protocol->treatment ) ? $protocol->treatment : $clinical_trial->treatment;
		if ( ! empty( $treatment ) ) {
			$protocol_summary .= '<p><strong>Treatment: </strong>' . $treatment . '</p>';
		}
		$detailed_elig = ! empty( $protocol->detail_elig ) ? $protocol->detail_elig : $clinical_trial->detailed_eligibility;
		if ( ! empty( $detailed_elig ) ) {
			$detailed_elig     = html_entity_decode( $detailed_elig );
			$protocol_summary .= '<p><strong>Detailed Eligibility: </strong>' . $detailed_elig . '</p>';
		}
		if ( ! is_null( $protocol->disease_site ) ) {
			$protocol_summary .= '<p><strong>Applicable Conditions: </strong>' . $protocol->disease_site . '</p>';
		}
		if ( ! is_null( $protocol->protocol_institution ) ) {
			$protocol_summary .= '<p><strong>Pariticipation Institution: </strong>' . $protocol->protocol_institution . '</p>';
		}
		if ( ! is_null( $protocol->contact ) ) {
			$protocol_summary .= '<p>' . $protocol->contact . '</p>';
		}
		if ( ! is_null( $protocol->nct_url ) ) {
			$protocol_summary .= '<p><strong>More Information: </strong>View study listing on ClinicialTrials.gov 
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
	$protocol_obj = new Protocol();
	$url          = esc_url_raw( 'http://oncore.cancer.ufl.edu/sip/SIPMain?hdn_function=SIP_PROTOCOL_SUMMARY&protocol_id' . $protocol_id . '&protocol_no=' . $protocol_no );
	$out          = wp_remote_get( $url );
	if ( ! is_wp_error( $out ) ) {
		$protocol_obj->is_active = true;
		$doc                     = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $out['body'] );
		if ( ! is_null( $doc->getElementById( 'protocolNo' ) ) ) {
			$protocol_obj->protocol_no = $doc->getElementById( 'protocolNo' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'secondaryProtocolNo' ) ) ) {
			$protocol_obj->secondary_protocol_no = $doc->getElementById( 'secondaryProtocolNo' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolTitle' ) ) ) {
			$protocol_obj->protocol_title = $doc->getElementById( 'protocolTitle' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolPi' ) ) ) {
			$protocol_obj->protocol_pi = $doc->getElementById( 'protocolPi' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolObjective' ) ) ) {
			$protocol_obj->protocol_objective = $doc->getElementById( 'protocolObjective' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'treatment' ) ) ) {
			foreach ( $doc->getElementById( 'treatment' )->childNodes as $treament_str ) {
				if ( ! is_null( $treament_str->textContent ) && ! empty( $treament_str->textContent ) ) {
					$protocol_obj->treatment .= '</br>' . $treament_str->textContent;
				}
			}
		}

		if ( ! is_null( $doc->getElementById( 'layDescription' ) ) ) {
			$protocol_obj->lay_description = $doc->getElementById( 'layDescription' )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolPhase' ) ) ) {
			$protocol_obj->protocol_phase = $doc->getElementById( 'protocolPhase' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolAge' ) ) ) {
			$protocol_obj->protocol_age = $doc->getElementById( 'protocolAge' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'protocolScope' ) ) ) {
			$protocol_obj->protocol_scope = $doc->getElementById( 'protocolScope' )->childNodes->item( 0 )->textContent;
		}

		if ( ! is_null( $doc->getElementById( 'detailElig' ) ) ) {
			foreach ( $doc->getElementById( 'detailElig' )->childNodes as $detail_elig ) {

				if ( ! is_null( $detail_elig->textContent ) && ! empty( $detail_elig->textContent ) ) {
					$protocol_obj->detail_elig .= '</br>' . $detail_elig->textContent;
				}
			}
		}

		if ( ! is_null( $doc->getElementById( 'diseaseSite' ) ) ) {
			$protocol_obj->disease_site .= '<p>';
			foreach ( $doc->getElementById( 'diseaseSite' )->childNodes as $disease_site ) {
				if ( ! is_null( $disease_site->textContent ) && ! empty( $disease_site->textContent ) ) {
					$protocol_obj->disease_site .= '<li>' . $disease_site->textContent . '</li>';
				}
			}
			$protocol_obj->disease_site .= '</p>';
		}

		if ( ! is_null( $doc->getElementById( 'protocolInstitution' ) ) ) {
			$protocol_obj->protocol_institution .= '<p>';
			foreach ( $doc->getElementById( 'protocolInstitution' )->childNodes as $protocol_institution ) {
				if ( ! is_null( $protocol_institution->textContent ) && ! empty( $protocol_institution->textContent ) ) {
					$protocol_obj->protocol_institution .= '<li>' . $protocol_institution->textContent . '</li>';
				}
			}
			$protocol_obj->protocol_institution .= '</p>';
		}

		if ( ! is_null( $doc->getElementById( 'contactinfo' ) ) ) {
			$contact_name = $doc->getElementById( 'contactinfo' )->childNodes->item( 2 )->textContent;
			if ( ! empty( $contact_name ) ) {
				$contact_str   = '<Strong>Contact:</Strong>';
				$contact_str  .= '</br>' . $contact_name . '';
				$contact_phone = $doc->getElementById( 'contactinfo' )->childNodes->item( 5 )->textContent;
				if ( ! empty( $contact_phone ) ) {
					$contact_str .= '</br>Phone: ' . $contact_phone . '';
				}
				$contact_email = $doc->getElementById( 'contactinfo' )->childNodes->item( 7 )->textContent;
				if ( ! empty( $contact_email ) ) {
					$contact_str .= '<br/> Email: <a href=\'mailto:' . $contact_email . '\'>' . $contact_email . '</a>';
				}
				$protocol_obj->contact = $contact_str;
			}
		}

		if ( ! is_null( $doc->getElementById( 'moreInformation' ) ) ) {
			$protocol_obj->nct_url = $doc->getElementById( 'moreInformation' )->childNodes->item( 6 )->textContent;
		}

		if ( ! is_null( $protocol_obj->nct_url ) ) {
			$protocol_obj->nct_id = str_replace( 'http://www.clinicaltrials.gov/ct2/show/', '', $protocol_obj->nct_url );
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
				$clinical_trial_obj->detailed_description = $xml_doc->getElementsByTagName( 'detailed_description' )
					->item( 0 )->getElementsByTagName( 'textblock' )->item( 0 )->textContent;
			}
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'arm_group' ) ) ) {
			$study_arm_str = '<p>';
			foreach ( $xml_doc->getElementsByTagName( 'arm_group' ) as $arm_group ) {
				$study_arm_str .= '<p>';
				$study_arm_str .= $arm_group->getElementsByTagName( 'arm_group_type' )->item( 0 )->textContent . ': ' . $arm_group->getElementsByTagName( 'arm_group_label' )->item( 0 )->textContent;
				$study_arm_str .= '</br>' . $arm_group->getElementsByTagName( 'description' )->item( 0 )->textContent;
				$study_arm_str .= '</p>';
			}
			$study_arm_str                .= '</p>';
			$clinical_trial_obj->treatment = $study_arm_str;
		}

		if ( ! is_null( $xml_doc->getElementsByTagName( 'eligibility' )->item( 0 ) ) ) {
            $clinical_detailed_eligibility = esc_html($xml_doc->getElementsByTagName( 'eligibility' )->item( 0 )->getElementsByTagName( 'textblock' )->item( 0 )->textContent);
            $arrs = explode(PHP_EOL,$clinical_detailed_eligibility);
            $next_line = true;
            foreach($arrs as $arr){
                if($next_line){
                    $clinical_trial_obj->detailed_eligibility .= '</br>';
                }
                $clinical_trial_obj->detailed_eligibility .= $arr;
                if(empty($arr)){
                    $next_line = true;
                }else{
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
}

