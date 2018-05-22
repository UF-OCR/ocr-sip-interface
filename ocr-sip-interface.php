<?php
/**
 * Plugin Name: OCR SIP Interface
 * Plugin URI: http://oncore.cancer.ufl.edu/sip/SIPControlServlet
 * Description: Dynamic URL for protocol summary.
 * Version: 1.0
 * Text Domain: ocr-sip-interface
 * Contact: oncore-support@ahc.ufl.edu
 */


define('OCR_PLUGIN_DIR', __DIR__);

require_once OCR_PLUGIN_DIR . '/models/protocol.php';

require_once OCR_PLUGIN_DIR . '/models/clinicalTrial.php';


/**
 * Listing the protocols for the selected disease site.
 *
 * @param string $disease_site_desc The Disease Site selected.
 *
 * @return mixed|null|string
 */
function protocol_list( $disease_site_desc )
{
    $transient = get_transient('protocol_list_' . $disease_site_desc);
    if (false !== $transient) {
        return $transient;
    } else {
        $protocolListStr = build_protocol_list($disease_site_desc);
        if (strpos($protocolListStr, 'scheduled maintenance') !== false) {
            return $protocolListStr;
        }
        if (!empty($protocolListStr)) {
            $result = wp_kses_post($protocolListStr);
            if (!empty($result)) {
                set_transient('protocol_list_' . $disease_site_desc, $result, DAY_IN_SECONDS);
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
function build_protocol_list($disease_site_desc)
{
    $url = esc_url_raw('http://oncore-staging.cancer.ufl.edu/sip/SIPControlServlet?hdn_function=SIP_PROTOCOL_LISTINGS&disease_site=' . $disease_site_desc);
    $out = wp_remote_get($url);
    $replace_arr = array('javascript:displayProtocolSummary', '(', ')', '\'');
    $protocolListStr = '';
    if (!is_wp_error($out)) {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($out['body']);
        $infoText = $doc->getElementById('infoText')->textContent;
        //        $searchText = $doc->getElementById('searchText')->parentNode->textContent;
        //        $protocolListStr .= '<h3>'.$searchText.'</h3>';
        if (!empty($infoText)) {
            $protocolListStr .= '<h4>'. $infoText .'</h4>';
        }
        if (!is_null($doc->getElementById('listInfo'))) {
            foreach ($doc->getElementById('listInfo')->childNodes as $row) {
                $rowChilds = $row->childNodes;
                if (is_object($rowChilds)) {
                    foreach ($rowChilds as $rowChild) {
                        $protocolListStr .= '<dl>';
                        if ($rowChild->getAttribute('id') === 'protocolList') {
                            $replaced_string = str_replace($replace_arr, '', $rowChild->childNodes->item(0)->childNodes->item(0)->getAttribute('href'));
                            $protocol_id = explode(',', $replaced_string);
                            $protocol_id = $protocol_id[0];
                            $protocol_no = $rowChild->childNodes->item(0)->textContent;
                            $protocolTitle = $rowChild->childNodes->item(1)->textContent;
                            $arr_params = array(
                                'protocol_id' => $protocol_id,
                                'protocol_no' => $protocol_no,
                            );
                            $url = add_query_arg($arr_params, wp_get_canonical_url() . 'protocol-summary');
                            $anchor = '<a href=\''.$url.'\'>'. $protocol_no .'</a>';
                            $protocolListStr .= '<dt>'.$anchor.'</dt>';
                            $protocolListStr .= '<dd>'.$protocolTitle.'</dd>';
                        }
                        $protocolListStr .= '</dl>';
                    }
                }
            }
        }
        return $protocolListStr;
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
function protocol_list_short_code( $args )
{
    if (isset($args['disease_site_desc']) ) {
        $disease_site_desc = $args['disease_site_desc'];
        $pl_request        = protocol_list($disease_site_desc);
        if (is_null($pl_request) ) {
            return '<h4>Whoops, something went wrong. Please try again!</h4>';
        }
        return $pl_request;
    } else {
        return '<h4>Choose a cancer type to find the protocol listing.</h4>';
    }
}

add_shortcode('protocolsList', 'protocol_list_short_code');

/**
 * Functionality to display protocol summary.
 *
 * @param string $protocol_id internal id of the protocol selected
 * @param string $protocol_no OCR# of the protocol selected
 *
 * @return mixed|null|string
 */
function protocol_summary( $protocol_id, $protocol_no )
{
    $transient = get_transient('protocol_detail_' . $protocol_id);
    //    if (false !== $transient) {
    //        return $transient;
    //    } else {
        $clinicalTrial = new ClinicalTrail();
        $protocol = build_protocol_obj($protocol_id, $protocol_no);
    if (!is_null($protocol)) {
        if ($protocol->isActive) {
            if (!is_null($protocol->nctId) && $protocol->nctId != '') {
                $clinicalTrial = build_clinical_trial_obj($protocol->nctId);
            }
            $protocolSummaryStr = build_protocol_summary($protocol, $clinicalTrial);
            $result = wp_kses_post($protocolSummaryStr);
            //oncore sip api null pointers
            if (strpos($result, 'java.lang.NullPointerException') !== false) {
                return null;
            }
            if (!empty($result)) {
                set_transient('protocol_detail_' . $protocol_id, $result, DAY_IN_SECONDS);
                return $result;
            }
            return null;
        }
        return '<h3>OnCore is currently undergoing scheduled maintenance.</h3><h4>We\'ll be back soon.</br></br> Sorry for the inconvenience.</h4>';
    }
    //    }
}


/**
 * Short code for protocol details.
 *
 * @return mixed|string
 */
function protocol_summary_short_code()
{
    if (isset($_GET['protocol_id']) && isset($_GET['protocol_no'])) {
        $protocol_id = sanitize_text_field(wp_unslash($_GET['protocol_id']));
        $protocol_no = sanitize_text_field(wp_unslash($_GET['protocol_no']));
        $pd_request = protocol_summary($protocol_id, $protocol_no);
        if (is_null($pd_request)) {
            return '<h4>Whoops, something went wrong. Please try again!</h4>';
        }
        return $pd_request;
    } else {
        return '<h4>Choose a protocol to find detailed information.</h4>';
    }
}

add_shortcode('protocolSummary', 'protocol_summary_short_code');

/**
 * Build the protocol summary text content
 *
 * @param Protocol      $protocol      is a protocol objects
 * @param ClinicalTrail $clinicalTrial is a clinicalTrial object
 *
 * @return mixed|null|string
 */
function build_protocol_summary($protocol, $clinicalTrial)
{
    if (!is_null($protocol->protocolNo)) {
        $protocolSummary ='';
        if (!is_null($protocol->protocolNo)) {
            $protocolSummary .= '<p><strong>Protocol No.: </strong>'.$protocol->protocolNo.'</p>';
        }
        if (!is_null($protocol->secondaryProtocolNo)) {
            $protocolSummary .= '<p><strong>Sponsor Protocol No.: </strong>'.$protocol->secondaryProtocolNo.'</p>';
        }
        $title = is_null($protocol->protocolTitle) ? $protocol->protocolTitle : $clinicalTrial->title;
        if (!empty($title)) {
            $protocolSummary .= '<p><strong>Protocol Title.: </strong>'.$title.'</p>';
        }
        if (!is_null($protocol->protocolPi)) {
            $protocolSummary .= '<p><strong>Principal Investigator: </strong>'.$protocol->protocolPi.'</p>';
        }
        $objective = !empty($protocol->protocolObjective) ? $protocol->protocolObjective : $clinicalTrial->objective;
        if (!empty($objective)) {
            $protocolSummary .= '<p><strong>Objective: </strong>'.$objective.'</p>';
        }
        $description = !empty($protocol->layDescription) ? $protocol->layDescription : $clinicalTrial->detailedDescription;
        if (!empty($description)) {
            $protocolSummary .= '<p><strong>Description: </strong>'.$description.'</p>';
        }
        $phase = !empty($protocol->protocolPhase) ? $protocol->protocolPhase : $clinicalTrial->phase;
        if (!empty($phase)) {
            $protocolSummary .= '<p><strong>Phase: </strong>'.$phase.'</p>';
        }
        if (!is_null($protocol->protocolAge)) {
            $protocolSummary .= '<p><strong>Age Group: </strong>'.$protocol->protocolAge.'</p>';
        }
        if (!is_null($clinicalTrial->maximum_age) && !is_null($clinicalTrial->minimum_age)) {
            $protocolSummary .= '<p><strong>Age: </strong>'.$clinicalTrial->minimum_age .' - '. $clinicalTrial->maximum_age.'</p>';
        }
        if (!is_null($clinicalTrial->gender)) {
            $protocolSummary .= '<p><strong>Gender: </strong>'.$clinicalTrial->gender.'</p>';
        }
        if (!is_null($protocol->protocolScope)) {
            $protocolSummary .= '<p><strong>Scope: </strong>'.$protocol->protocolScope.'</p>';
        }
        $treatment = !empty($protocol->treatment) ? $protocol->treatment : $clinicalTrial->treatment;
        if (!empty($treatment)) {
            $protocolSummary .= '<p><strong>Treatment: </strong>'.$treatment.'</p>';
        }
        $detailedElig = !empty($protocol->detailElig) ? $protocol->detailElig : $clinicalTrial->detailedEligibility;
        if (!empty($detailedElig)) {
            $detailedElig = html_entity_decode($detailedElig);
        }
        $protocolSummary .= '<p><strong>Detailed Eligibility: </strong>'.$detailedElig.'</p>';
        if (!is_null($protocol->diseaseSite)) {
            $protocolSummary .= '<p><strong>Applicable Conditions: </strong>'.$protocol->diseaseSite .'</p>';
        }
        if (!is_null($protocol->protocolInstitution)) {
            $protocolSummary .= '<p><strong>Pariticipation Institution: </strong>'.$protocol->protocolInstitution.'</p>';
        }
        if (!is_null($protocol->contact)) {
            $protocolSummary .= '<p>'.$protocol->contact.'</p>';
        }
        if (!is_null($protocol->nctUrl)) {
            $protocolSummary .= '<p><strong>More Information:</strong>View study listing on ClinicialTrials.gov 
                                    <a target=\'_blank\' href=\''.$protocol->nctUrl.'\'/>'.$protocol->nctUrl.'</p>';
        }
        return $protocolSummary;
    }
    return null;
}

/**
 * Build the protocol object
 *
 * @param string $protocol_id The id to the protocol
 * @param string $protocol_no The protocol number to the the protocol.
 *
 * @return Protocol
 */
function build_protocol_obj($protocol_id, $protocol_no)
{
    $protocolObj = new Protocol();
    $url = esc_url_raw('http://oncore.cancer.ufl.edu/sip/SIPMain?hdn_function=SIP_PROTOCOL_SUMMARY&protocol_id'. $protocol_id . '&protocol_no=' . $protocol_no);
    $out = wp_remote_get($url);
    if (!is_wp_error($out)) {
        $protocolObj->isActive = true;
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($out['body']);
        if (!is_null($doc->getElementById('protocolNo'))) {
            $protocolObj->protocolNo = $doc->getElementById('protocolNo')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('secondaryProtocolNo'))) {
            $protocolObj->secondaryProtocolNo = $doc->getElementById('secondaryProtocolNo')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('protocolTitle'))) {
            $protocolObj->protocolTitle = $doc->getElementById('protocolTitle')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('protocolPi'))) {
            $protocolObj->protocolPi = $doc->getElementById('protocolPi')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('protocolObjective'))) {
            $protocolObj->protocolObjective = $doc->getElementById('protocolObjective')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('treatment'))) {
            foreach ($doc->getElementById('treatment')->childNodes as $treamentStr) {
                if (!is_null($treamentStr->textContent) && !empty($treamentStr->textContent)) {
                    $protocolObj->treatment .= '</br>' . $treamentStr->textContent;
                }
            }
        }

        if (!is_null($doc->getElementById('layDescription'))) {
            $protocolObj->layDescription = $doc->getElementById('layDescription')->textContent;
        }

        if (!is_null($doc->getElementById('protocolPhase'))) {
            $protocolObj->protocolPhase = $doc->getElementById('protocolPhase')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('protocolAge'))) {
            $protocolObj->protocolAge = $doc->getElementById('protocolAge')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('protocolScope'))) {
            $protocolObj->protocolScope = $doc->getElementById('protocolScope')->childNodes->item(0)->textContent;
        }

        if (!is_null($doc->getElementById('detailElig'))) {
            foreach ($doc->getElementById('detailElig')->childNodes as $detailElig) {
                if (!is_null($detailElig->textContent) && !empty($detailElig->textContent)) {
                    $protocolObj->detailElig .= '</br>' . $detailElig->textContent;
                }
            }
        }

        if (!is_null($doc->getElementById('diseaseSite'))) {
            $protocolObj->diseaseSite .= '<p>';
            foreach ($doc->getElementById('diseaseSite')->childNodes as $diseaseSite) {
                if (!is_null($diseaseSite->textContent) && !empty($diseaseSite->textContent)) {
                    $protocolObj->diseaseSite .= '<li>' . $diseaseSite->textContent . '</li>';
                }
            }
            $protocolObj->diseaseSite .= '</p>';
        }

        if (!is_null($doc->getElementById('protocolInstitution'))) {
            $protocolObj->protocolInstitution .= '<p>';
            foreach ($doc->getElementById('protocolInstitution')->childNodes as $protocolInstitution) {
                if (!is_null($protocolInstitution->textContent) && !empty($protocolInstitution->textContent)) {
                    $protocolObj->protocolInstitution .= '<li>' . $protocolInstitution->textContent . '</li>';
                }
            }
            $protocolObj->protocolInstitution .= '</p>';
        }

        if (!is_null($doc->getElementById('contactinfo'))) {
            $contactName = $doc->getElementById('contactinfo')->childNodes->item(2)->textContent;
            if (!empty($contactName)) {
                $contactStr = '<Strong>Contact:</Strong>';
                $contactStr .= '</br>' . $contactName . '';
                $contactPhone = $doc->getElementById('contactinfo')->childNodes->item(5)->textContent;
                if (!empty($contactPhone)) {
                    $contactStr .= '</br>Phone: ' . $contactPhone . '';
                }
                $contactEmail = $doc->getElementById('contactinfo')->childNodes->item(7)->textContent;
                if (!empty($contactEmail)) {
                    $contactStr .= '<br/> Email: <a href=\'mailto:'.$contactEmail.'\'>'.$contactEmail.'</a>';
                }
                $protocolObj->contact = $contactStr;
            }
        }

        if (!is_null($doc->getElementById('moreInformation'))) {
            $protocolObj->nctUrl = $doc->getElementById('moreInformation')->childNodes->item(6)->textContent;
        }

        if (!is_null($protocolObj->nctUrl)) {
            $protocolObj->nctId = str_replace('http://www.clinicaltrials.gov/ct2/show/', '', $protocolObj->nctUrl);
        }
    } else {
        $protocolObj->isActive = false;
    }
    return $protocolObj;
}

/**
 * Build the clinicalTrial object
 *
 * @param string $nct_id The id to the sponsor.
 *
 * @return ClinicalTrail
 */
function build_clinical_trial_obj($nct_id)
{
    $clinicalTrialObj = new ClinicalTrail();
    $url = esc_url_raw('https://clinicaltrials.gov/ct2/show/' . $nct_id . '?displayxml=true');
    $out = wp_remote_get($url);
    if (!is_wp_error($out)) {
        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($out['body']);

        //title
        if (!is_null($xmlDoc->getElementsByTagName('brief_title'))) {
            $clinicalTrialObj->title = $xmlDoc->getElementsByTagName('brief_title')->item(0)->textContent;
        }

        if (!is_null($xmlDoc->getElementsByTagName('brief_summary'))) {
            $clinicalTrialObj->objective = $xmlDoc->getElementsByTagName('brief_summary')->item(0)->textContent;
        }

        if (!is_null($xmlDoc->getElementsByTagName('phase'))) {
            $clinicalTrialObj->phase = $xmlDoc->getElementsByTagName('phase')->item(0)->textContent;
        }

        if (!is_null($xmlDoc->getElementsByTagName('detailed_description'))) {
            if (!is_null($xmlDoc->getElementsByTagName('detailed_description')->item(0))) {
                $clinicalTrialObj->detailedDescription = $xmlDoc->getElementsByTagName('detailed_description')
                    ->item(0)->getElementsByTagName('textblock')->item(0)->textContent;
            }
        }

        if (!is_null($xmlDoc->getElementsByTagName('arm_group'))) {
            $studyArmStr = '<p>';
            foreach ($xmlDoc->getElementsByTagName('arm_group') as $armGroup) {
                $studyArmStr .= '<p>';
                $studyArmStr .= $armGroup->getElementsByTagName('arm_group_type')->item(0)->textContent.': '.$armGroup->getElementsByTagName('arm_group_label')->item(0)->textContent;
                $studyArmStr .= '</br>' . $armGroup->getElementsByTagName('description')->item(0)->textContent;
                $studyArmStr .= '</p>';
            }
            $studyArmStr .= '</p>';
            $clinicalTrialObj->treatment = $studyArmStr;
        }

        if (!is_null($xmlDoc->getElementsByTagName('eligibility')->item(0))) {
            $clinicalTrialObj->detailedEligibility = preg_replace(
                '/[\r\n\r\n]/', '</br>',
                $xmlDoc->getElementsByTagName('eligibility')->item(0)->getElementsByTagName('textblock')->item(0)->textContent
            );
        }

        if (!is_null($xmlDoc->getElementsByTagName('gender'))) {
            $clinicalTrialObj->gender = $xmlDoc->getElementsByTagName('gender')->item(0)->textContent;
        }

        if (!is_null($xmlDoc->getElementsByTagName('minimum_age'))) {
            $clinicalTrialObj->minimum_age = $xmlDoc->getElementsByTagName('minimum_age')->item(0)->textContent;
        }

        if (!is_null($xmlDoc->getElementsByTagName('maximum_age'))) {
            $clinicalTrialObj->maximum_age = $xmlDoc->getElementsByTagName('maximum_age')->item(0)->textContent;
        }

        return $clinicalTrialObj;
    }
}

