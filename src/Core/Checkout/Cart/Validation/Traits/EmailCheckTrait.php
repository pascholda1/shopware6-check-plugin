<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidEmailAddressCartError;
use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;

trait EmailCheckTrait
{
    private const EMAIL_HASH_KEY = 'al_email_md5';
    
    /**
     * @param \stdClass       $config
     * @param ErrorCollection $errors
     * @param CheckClient     $checkClient
     * @param CustomerEntity  $customer
     *
     * @return void
     * @throws GuzzleException
     * @throws InvalidProductException
     */
    private function checkEmailIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer,
    ): void {
        if (!$config->validateEmailAddress
            || !$customer->getEmail()
            || $this->isEmailAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->emailCheck($customer->getEmail());
        
        switch ($checkResult->trafficlight) {
            case 'gelb':
                if ($config->blockDisposableEmails
                    && $checkResult->resulttext === 'Domain weist auf Wegwerf-Email-Adresse hin') {
                    $errors->add(new InvalidEmailAddressCartError($checkResult->resulttext));
                } else {
                    $this->setEmailHash($customer);
                }
                break;
            case 'gruen':
                $this->setEmailHash($customer);
                break;
            default:
                $errors->add(new InvalidEmailAddressCartError($checkResult->resulttext));
        }
    }
    
    private function isEmailAlreadyChecked(CustomerEntity &$customer): bool
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!isset($customFields[self::EMAIL_HASH_KEY])) {
            return false;
        }
        
        return $this->getEmailHash($customer) === $customFields[self::EMAIL_HASH_KEY];
    }
    
    private function setEmailHash(CustomerEntity &$customer): void
    {
        $customFields = $customer->getCustomFields() ?? [];
        $customFields[self::EMAIL_HASH_KEY] = $this->getEmailHash($customer);
        
        $customer->setCustomFields($customFields);
    }
    
    private function getEmailHash(CustomerEntity &$customer): string
    {
        return md5($customer->getEmail());
    }
}
