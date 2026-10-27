<?php

namespace App\Support;

/**
 * Starter terms & conditions text. The administrator edits the live version
 * under Admin → Terms & Conditions; this is only the initial default.
 */
class DefaultTerms
{
    public static function text(): string
    {
        return <<<'TXT'
FARMMANTRA AGRO CHEMICALS LIMITED — FRANCHISE TERMS AND CONDITIONS

1. Pricing. Franchise partners buy at the franchise price set by Farmmantra and sell only at the published retail or farmer prices. Prices are set by Farmmantra administration and may not be altered by the franchise.
2. Orders. Orders are subject to acceptance by the Farmmantra warehouse. Quantities may be adjusted to available stock. Farmmantra targets dispatch within the published dispatch period.
3. Delivery and receipt. On delivery the franchise partner must physically count the goods, record any shortage or damage in the application, and accept the delivery. Accepting a delivery confirms the quantities received and creates a purchase entry on the franchise account.
4. Payment. Purchases on credit must be settled within the agreed credit limit. Payments are made by mobile money or bank transfer and are credited to the account only after verification and acceptance by Farmmantra Finance.
5. Records. Every confirmation in the application is recorded with a digital signature number identifying the user who confirmed it. Users must keep their login credentials confidential.
6. Returns. Goods may be returned only with Farmmantra approval, recorded as a sales return or credit note on the franchise account.
TXT;
    }
}
