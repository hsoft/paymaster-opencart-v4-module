# PayMaster for OpenCart 4.0.0.x - 4.0.2.x

## Creating the extension (module)

1. Open *install.json* and fill in the following fields:
    * link (PayMaster website URL);
    * base_service_url (PayMaster API base URL);
    * display_service_name (payment method name that will be shown to user).

    Filling example:

    > "link": "https://paymaster24.com",  
    > "base_service_url": "https://psp.paymaster24.com",  
    > "display_service_name": "PayMaster (bank cards, electronic money and more)",

2. Zip the resulting *install.json*, *admin* and *catalog* folders. Name the archive file **paymaster.ocmod.zip**.

## Installing the extension (module)

Please read the [user guide](user-guide.pdf).
