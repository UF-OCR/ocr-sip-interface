<?php
/**
 * Plugin Name: ocr-sip-interface
 * Description: Plugin to  cache and display a JSON-Feed.
 * Version: 1.0
 * Author: hkoranne
 */

// Register style sheet.
add_action( 'wp_enqueue_scripts', 'ocr_sip_styles' );

/**
 * Register style sheet. this stylesheet is the custom style sheet from onCore SIP.
 */
function ocr_sip_styles(){
    wp_register_style( 'ocr-sip-plugin', plugins_url( 'ocr-sip-interface/css/ocr-sip-style.css' ) );
    wp_enqueue_style( 'ocr-sip-plugin' );
}

/**
 * Get a list of protocols for diseaseSites
 * @param $diseaseSiteDescription
 * @return object The HTTP response that comes as a result of a wp_remote_get().
 */
function protocolList($diseaseSiteDescription)
{
    //delete_transient( 'protocol_list_'.$diseaseSiteDescription );
    $transient = get_transient('protocol_list_' . $diseaseSiteDescription);
    if (!empty($transient)) {
        return $transient;
    } else {
        $url = 'http://oncore.cancer.ufl.edu/sip/SIPControlServlet?hdn_function=SIP_PROTOCOL_LISTINGS&disease_site=' . $diseaseSiteDescription . '';
        $args = array(
            'headers' => array(//'token' => ''
            ),
        );
        $out = wp_remote_get($url, $args);
        if(!is_wp_error($out)){
            $doc = new DOMDocument();
            $doc->loadHTML($out['body']);
            // remove the scripts and styles received from OnCoreSip
            removeElementsByTagName('script', $doc);
            removeElementsByTagName('style', $doc);
            removeElementsByTagName('link', $doc);
            //replacing the links with dynamic links
            $anchors = $doc->getElementsByTagName("a");
            $replaceArr = array('javascript:displayProtocolSummary','(',')','\'');
            foreach ($anchors as $anchor){
                $replacedString = str_replace($replaceArr,'',$anchor->getAttribute("href"));
                $protocolId = explode(",",$replacedString);
                $protocolId =$protocolId[0];
                $protocolNo = $anchor->textContent;
                $arr_params = array('protocolid' => $protocolId,'protocolno'=> $protocolNo);
                $url = add_query_arg($arr_params, wp_get_canonical_url().'protocol-details ');
                $anchor->setAttribute('href',$url);
            }
            $result= $doc->saveHTML();
            set_transient('protocol_list_' . $diseaseSiteDescription, $result, DAY_IN_SECONDS);
            return $result;
        }
        return null;
    }
}

/**
 * ShortCode for displaying the list of protocol for selected disease site.
 * @param $args
 * @return string
 */
function protocol_list_shortcode($args)
{
    if (isset($args['diseasesite'])) {
        $diseaseSiteDesc = $args['diseasesite'];
        $plRequest = protocolList($diseaseSiteDesc);
        if(is_null($plRequest)){
            return "<h4>Whoops, something went wrong. Please try again!</h4>";
        }
         return $plRequest;
    } else {
        return '<h4>Please select the cancer type.</h4>';
    }
}

add_shortcode('protocolsList', 'protocol_list_shortcode');

/**
 * Get protocol details
 * @param $protocol_id $protocol_no
 * @return object The HTTP response that comes as a result of a wp_remote_get().
 */
function protocolDetails($protocol_id,$protocol_no)
{
    //delete_transient( 'protocol_detail_'.$protocol_id );
    $transient = get_transient('protocol_detail_' . $protocol_id);
    if (!empty($transient)) {
        return $transient;
    } else {
        $url = 'http://oncore.cancer.ufl.edu/sip/SIPMain?hdn_function=SIP_PROTOCOL_SUMMARY&protocol_id'.$protocol_id.'&protocol_no='.$protocol_no;
        $args = array(
            'headers' => array(//'token' => ''
            ),
        );
        $out = wp_remote_get($url, $args);
        if(!is_wp_error($out)) {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($out['body']);
            $result= $doc->saveHTML();
            set_transient('protocol_detail_' . $protocol_id, $result, DAY_IN_SECONDS);
            return $result;
        }
        return null;
    }
}

/**
 * Shortcode for protocolDetails
 * @return string
 */
function protocol_details_shortcode()
{
    if (isset($_GET['protocolid']) && isset($_GET['protocolno'])) {
        $pdRequest = protocolDetails($_GET['protocolid'],$_GET['protocolno']);
        if(is_null($pdRequest)){
            return '<h4>Whoops, something went wrong. Please try again!</h4>';
        }
        return $pdRequest;
    } else {
        return '<h4>Please select a protocol</h4>';
    }
}

add_shortcode('protocolDetails', 'protocol_details_shortcode');

/**
 * Remove the nodes for a given tag name
 * @param $tagName
 * @param $document
 */
function removeElementsByTagName($tagName, $document) {
    $nodeList = $document->getElementsByTagName($tagName);
    for ($nodeIdx = $nodeList->length; --$nodeIdx >= 0; ) {
        $node = $nodeList->item($nodeIdx);
        $node->parentNode->removeChild($node);
    }
}

?>