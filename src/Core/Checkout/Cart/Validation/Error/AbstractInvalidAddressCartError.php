<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

abstract class AbstractInvalidAddressCartError extends Error
{
    protected const KEY = 'adresslabor-address-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [];
    
    private string $resultTextPartialKey;
    
    public function __construct(string $resultText)
    {
        $this->resultTextPartialKey = self::CHECK_RESULT_PARTIAL_KEY_MAP[$resultText] ?? $resultText;
        
        parent::__construct();
    }
    
    public function getId(): string
    {
        return $this->resultTextPartialKey;
    }
    
    public function getMessageKey(): string
    {
        return implode('-', [self::KEY, $this->resultTextPartialKey]);
    }
    
    public function getLevel(): int
    {
        //        return self::LEVEL_NOTICE;
        //        return self::LEVEL_WARNING;
        return self::LEVEL_ERROR;
    }
    
    public function blockOrder(): bool
    {
        return true;
    }
    
    public function getParameters(): array
    {
        return [];
    }
}
