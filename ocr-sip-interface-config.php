<?php
/**
 * Class for config objects
 *
 * Since version 1.8
 *
 * @author  OCR
 */
class OcrSipInterfaceConfig {

	public $protocol_list_url    = 'http://oncore.cancer.ufl.edu/sip/SIPControlServlet?hdn_function=SIP_PROTOCOL_LISTINGS&disease_site=';
	public $protocol_summary_url = 'http://oncore.cancer.ufl.edu/sip/SIPMain?hdn_function=SIP_PROTOCOL_SUMMARY&protocol_id=';
	public $warning_message      = 'Whoops, something went wrong. Please try again!';
	public $protocol_listing_wm  = 'Choose a cancer type to find the protocol listing.';
	public $maintanance_msg      = "<h3>OnCore is currently undergoing scheduled maintenance.</h3>
    <h4>We'll be back soon.</br></br> Sorry for the inconvenience.</h4>";
	public $protocol_label       = 'Protocol No.: ';
	public $sponsor_label        = 'Sponsor Protocol No.: ';
	public $protocol_title_label = 'Protocol Title.: ';
	public $pi_label             = 'Principal Investigator: ';
	public $object_label         = 'Objective: ';
	public $description_label    = 'Description: ';
	public $phase_label          = 'Phase: ';
	public $age_label            = 'Age: ';
	public $age_group_label      = 'Age Group: ';
	public $gender_label         = 'Gender: ';
	public $scope_label          = 'Scope: ';
	public $treatment_label      = 'Treatment: ';
	public $elig_label           = 'Detailed Eligibility: ';
	public $conditions_label     = 'Applicable Conditions: ';
	public $inst_label           = 'Participation Institution: ';
	public $more_info_label      = 'More Information: ';
	public $ctgov_label          = 'View study listing on ClinicialTrials.gov ';
	public $contact_label        = 'Contact: ';
	public $phone_label          = 'Phone: ';
	public $email_label          = 'Email: ';
	public $count_msg            = 'protocols meet the specified criteria';
}
