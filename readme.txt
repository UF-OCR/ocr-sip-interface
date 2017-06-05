=== Plugin Name ===
Contributors: Harshita koranne
Donate link: http://oncore.cancer.ufl.edu/sip/SIPControlServlet/
Short codes: [protocolSummary],[protocolsList disease_site_desc='disease_site_desc']

ocr-sip-plugin communicates with the OnCore SIP API, and creates a dynamic link(protocol number specific).

== Installation ==

1. Upload `ocr-sip-interface` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Place [protocolsList disease_site_desc='diseaseSiteDesc'] in your templates to get the protocol Lists
4. [protocolSummary] to get the protocol summary


== Example ==

To list the protocols for a cancer type:
Ex: Bladder Cancer
[protocolsList disease_site_desc='Bladder']

For Protocol Details
[protocolSummary]

