<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class InvalidBillingAddressCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-billing-address-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'ungültig, PLZ und Ort OK' => 'invalid-street',
        'ungültig, mehrdeutig' => 'ambiguous',
        'ungültig' => 'invalid',
        'Bitte Eingabe prüfen' => 'dubious',
    ];
    
    public function __construct(string $resultText)
    {
        $this->resultTextPartialKey = self::CHECK_RESULT_PARTIAL_KEY_MAP[trim($resultText)] ?? $resultText;
        
        parent::__construct();
    }
    
    public function getMessageKey(): string
    {
        return implode('-', [self::KEY, $this->resultTextPartialKey]);
    }
    
}
