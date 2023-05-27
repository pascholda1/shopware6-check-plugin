<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class InvalidBillingAddressCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-billing-address-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'ungültig, PLZ und Ort OK' => 'invalid-street',
        'Ungültig, mehrdeutig' => 'ambiguous',
        'Ungültig' => 'invalid',
        'Bitte Eingabe prüfen' => 'dubious',
    ];
    
}
