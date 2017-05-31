=== Plugin Name ===
Contributors: Harshita koranne
Donate link: http://oncore.cancer.ufl.edu/sip/SIPControlServlet/
Shortcodes: [protocolDetails],[protocolsList diseasesite='disease_site_desc']

ocr-sip-plugin communicates with the OnCore SIP API to get the protocol list and protocol summary, and replaces the anchors in protocol list with a dynamic link for the protocols.

== Installation ==

1. Upload `ocr-sip-interface` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Place [protocolsList diseasesite='diseaseSiteDesc'] in your page to get the protocol Lists
4. [protocolDetails] to get the protocol details.


== Example ==

To list the protocols for a cancer type:
Ex: Bladder Cancer
[protocolsList diseasesite='Bladder']

For Protocol Details
[protocolDetails]

