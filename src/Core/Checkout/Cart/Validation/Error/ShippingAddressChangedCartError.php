<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class ShippingAddressChangedCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-shipping-address-changed';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'unsicher gefunden' => 'unsafe',
        'unsicher, PLZ und Ort OK' => 'unsafe-check-street',
        'unsicher gefunden, Hausnr./ Postf. prüfen' => 'unsafe-check-hno',
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
    
    public function getLevel(): int
    {
        //        return self::LEVEL_NOTICE;
        return self::LEVEL_WARNING;
        //        return self::LEVEL_ERROR;
    }
    
    public function blockOrder(): bool
    {
        return false;
    }
}
