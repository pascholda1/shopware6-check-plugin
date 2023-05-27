<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class InvalidNameCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-invalid-name-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'ungültig, PLZ und Ort OK' => 'invalid-street',
        'Ungültig, mehrdeutig' => 'ambiguous',
        'Ungültig' => 'invalid',
        'Bitte Eingabe prüfen' => 'dubious',
    ];
    
}
