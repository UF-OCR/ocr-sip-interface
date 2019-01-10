# OCR SIP Interface Plugin
- OCR-SIP plugin was developed to seamlessly add protocol listings and protocol summaries to your Institutional WordPress sites. 
- The plugin can be used to add your protocol listing/protocol summary to any post, page or text widget by using the `[protocolsList]` and `[protocolSummary]` shortcodes. OCR-SIP Plugin is maintained by the OCR OnCore Development Team.

## Prerequisites
- Wordpress Version 4.0 or higher
- OnCore SIP API activated

## Installation
- Clone this repo into your machine.
- Update your SIP URLS in ocr-sip-interface-config.php file.
- Upload the folder into wordpress site via plugin interface, and then enable it.


## Displaying a protocol list

- The ocr-sip-interface plugin uses `[protocolsList disease_site_desc='diseaseSiteDesc']` shortcode to insert the protocol list page feed. 
- You have to set the parameter for the disease site (diseaseSiteDesc) within the short code. 
- The disease site parameter comes from either the protocol disease site (Oncology protocols only) in PC Console or the Web Description assigned in the SIP Console (all protocols).
- Ex: `[protocolsList disease_site_desc='Bladder'], [protocolsList disease_site_desc='Liver']`, etc

## Displaying a protocol

- The ocr-sip-interface plugin uses `[protocol Summary]` shortcodes to insert the protocol summary page. 
- For Protocol Summary, no parameters are used within the shortcode because the plugin automatically sets the protocol parameter. 
- Ex: `[protocolSummary]`