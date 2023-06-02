<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\FakeNameCartError;
use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;

trait FakeCheckTrait
{
    private const FAKE_NAME_HASH_KEY = 'al_fake_name_md5';
    
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
    private function checkFakeNameIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer,
    ): void {
        if (!$config->validateFakeName
            || $this->isFakeNameAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->fakeCheck(
            $customer->getFirstName(),
            $customer->getLastName()
        );
        
        if ($checkResult->trafficlight === 'gruen') {
            $this->setFakeNameHash($customer);
        } else {
            $errors->add(new FakeNameCartError($checkResult->resulttext));
        }
    }
    
    private function isFakeNameAlreadyChecked(CustomerEntity &$customer): bool
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!isset($customFields[self::FAKE_NAME_HASH_KEY])) {
            return false;
        }
        
        return $this->getFakeNameHash($customer) === $customFields[self::FAKE_NAME_HASH_KEY];
    }
    
    private function setFakeNameHash(CustomerEntity &$customer): void
    {
        $customFields = $customer->getCustomFields() ?? [];
        $customFields[self::FAKE_NAME_HASH_KEY] = md5($customer->getEmail());
        
        $customer->setCustomFields($customFields);
    }
    
    private function getFakeNameHash(CustomerEntity &$customer): string
    {
        $nameString = implode('-', [
            $customer->getFirstName(),
            $customer->getLastName(),
        ]);
        
        return md5($nameString);
    }
}
