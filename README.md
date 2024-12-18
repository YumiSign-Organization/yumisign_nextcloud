![](https://docs.rcdevs.com/pictures/logo/yumisign/nextcloud/yumisign_169x169.png)

# YumiSign for Nextcloud
------

This version is deprecated due to Nextcloud 28 EOL. For more information about it, please check this page
https://github.com/nextcloud/server/wiki/Maintenance-and-Release-Schedule/
					
| Version code | Version name | Release date | End of life | Current version | Next version |
| ------------ | ------------ | ------------ | ----------- | --------------- | ------------ |
|     28         |Hub 7        |2023-12-12 | 2024-12 | 28.0.14 (2024-12-12) | End of Life |

This YumiSign Plugin for Nextcloud allows users to digitally sign documents directly within the Nextcloud platform. With this plugin, you can securely sign PDFs, Word documents, and other supported file types with ease, ensuring the integrity and authenticity of your documents.

# Features

* Digital signatures for various document types.
* Support for multiple signature levels (eIDAS compliant).
* Easy integration with Nextcloud's user interface.
* Secure signing process with audit trail for document verification.

# Requirements
* Nextcloud instance (version X.X or higher).
* Access to Nextcloud's admin account for installation.

# Installation

1. **Download the Plugin**: First, download the latest version of the YumiSign Plugin from the Nextcloud app store or the official repository.
1. **Install the Plugin**: Log into your Nextcloud instance as an admin. Navigate to `Apps > App Store`, then upload the downloaded plugin package.
1. **Enable the Plugin**: Once uploaded, navigate to `Apps > Disabled Apps`. Find the Signature Plugin and click `Enable`.

# Configuration

After installation, you may need to configure the plugin to suit your needs:

1. **Access Plugin Settings**: Go to `Settings > Administration > Signature Plugin`.
1. **Configure Signature Settings**: Set up your signature preferences, including default signature format, security options, and any integration settings with external signature providers.
1. **Save Changes**: Ensure you save your settings before exiting.

# Usage
To sign a document:
1. **Open the Document**: Navigate to the file within Nextcloud and open it.
1. **Initiate Signing Process**: Click on the `Sign` button typically located in the document viewer's toolbar.
1. **Sign the Document**: Follow the on-screen instructions to sign the document. This may involve selecting a signature type, drawing a signature, or using a digital certificate.
1. **Verify & Save**: Once signed, the document will be automatically saved with a signature. You can also verify the signature through the plugin interface.

# Troubleshooting
* **Signature not Appearing**: Ensure the document type is supported and that you have completed all signing steps.
* **Plugin not Loading**: Verify that your Nextcloud and PHP versions meet the plugin’s requirements. Check the Nextcloud log for any error messages.
* **Issues with External Signatures**: If using external signature services, ensure your API keys and service settings are correctly configured.

# Support
For more support and information:
* Visit the [YumiSign Helpcenter](https://app.yumisign.com/help/).
* Report issues on the [YumiSign contact page](https://www.yumisign.com/contact-us/).

# Contact us
Please contact YumiSign’ sales team [info@yumisign.com](mailto:info@yumisign.com) to purchase e-signature credits.

**How to use it**

You can download the application from the Nextcloud market at https://apps.nextcloud.com/apps/yumisign_nextcloud

On this repository, please consult the different branches corresponding to the version of Nextcloud installed on your server.

Do not hesitate to contact us at https://www.yumisign.com/contact-us

![](https://docs.rcdevs.com/pictures/logo/rcdevs/nextcloud/rcdevs_115x54.png)
