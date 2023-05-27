<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class ValidationFailedCartError extends Error
{
    
    private const KEY = 'adresslabor-validation-failed-cart-blocked';
    
    public function getId(): string
    {
        return $this->getMessageKey();
    }
    
    public function getMessageKey(): string
    {
        return self::KEY;
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
