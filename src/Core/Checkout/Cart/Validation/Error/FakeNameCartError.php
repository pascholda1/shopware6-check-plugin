<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class FakeNameCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-fake-name-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'Fake-Warnung (Vorname)!' => 'invalid-firstname',
        'Fake-Warnung (Nachname)!' => 'invalid-lastname',
        'Fake-Warnung (Namenszeile)!' => 'invalid',
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
