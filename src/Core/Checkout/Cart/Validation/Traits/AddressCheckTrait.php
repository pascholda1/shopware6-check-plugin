<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\BillingAddressChangedCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidBillingAddressCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidShippingAddressCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\ShippingAddressChangedCartError;
use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;

trait AddressCheckTrait
{
    private const ADDRESS_HASH_KEY = 'al_address_md5';
    
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
    private function checkShippingAddressIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer,
    ): void {
        $shippingAddress = $customer->getActiveShippingAddress();
        if (!$config->validateShippingAddress
            || !$shippingAddress
            || $this->isAddressAlreadyChecked($shippingAddress)
        ) {
            return;
        }
        
        $checkResult = $this->validateAddress($checkClient, $shippingAddress);
        
        switch ($checkResult[0]->trafficlight) {
            case 'gelb':
                $errors->add(new ShippingAddressChangedCartError($checkResult[0]->resulttext));
                $this->setAddressFromCheckResult($shippingAddress, $checkResult[0]);
                if ($checkResult[0]->hno === '') {
                    $errors->add(new InvalidShippingAddressCartError('missing-hno'));
                } else {
                    $this->setAddressHash($shippingAddress);
                }
                break;
            case 'gruen':
                $this->setAddressFromCheckResult($shippingAddress, $checkResult[0]);
                $this->setAddressHash($shippingAddress);
                break;
            default:
                $errors->add(new InvalidShippingAddressCartError($checkResult[0]->resulttext));
        }
    }
    
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
    private function checkBillingAddressIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer
    ): void {
        $billingAddress = $customer->getActiveBillingAddress();
        if (!$config->validateBillingAddress
            || !$billingAddress
            || $this->isAddressAlreadyChecked($billingAddress)
        ) {
            return;
        }
        
        $checkResult = $this->validateAddress($checkClient, $billingAddress);
        
        switch ($checkResult[0]->trafficlight) {
            case 'gelb':
                $errors->add(new BillingAddressChangedCartError($checkResult[0]->resulttext));
                $this->setAddressFromCheckResult($billingAddress, $checkResult[0]);
                $this->setAddressHash($billingAddress);
                break;
            case 'gruen':
                $this->setAddressFromCheckResult($billingAddress, $checkResult[0]);
                if ($checkResult[0]->hno === '') {
                    $errors->add(new InvalidBillingAddressCartError('missing-hno'));
                } else {
                    $this->setAddressHash($billingAddress);
                }
                break;
            default:
                $errors->add(new InvalidBillingAddressCartError($checkResult[0]->resulttext));
        }
    }
    
    private function isAddressAlreadyChecked(?CustomerAddressEntity &$address): bool
    {
        $customFields = $address->getCustomFields() ?? [];
        if (!isset($customFields[self::ADDRESS_HASH_KEY])) {
            return false;
        }
        
        return $this->getAddressHash($address) === $customFields[self::ADDRESS_HASH_KEY];
    }
    
    /**
     * @param CheckClient           $checkClient
     * @param CustomerAddressEntity $address
     *
     * @return \stdClass
     * @throws GuzzleException
     * @throws InvalidProductException
     */
    private function validateAddress(CheckClient &$checkClient, CustomerAddressEntity &$address): \stdClass|array
    {
        $countriesDACH = ['DE', 'AT', 'CH'];
        $countryIso = $address->getCountry()->getIso();
        if (in_array($countryIso, $countriesDACH)) {
            return $checkClient->addressCheckDACH(
                $address->getStreet() ?? '',
                '',
                $address->getZipcode() ?? '',
                $address->getCity() ?? '',
                $countryIso
            );
        } else {
            return $checkClient->addressCheckWorld(
                $address->getStreet(),
                $address->getZipcode(),
                $address->getCity(),
                $countryIso,
                $address->getCountryState(),
                "",
                "",
                "",
                "",
                $address->getCompany()
            );
        }
    }
    
    private function setAddressFromCheckResult(CustomerAddressEntity &$address, \stdClass &$checkResult): void
    {
        $address->setCity($checkResult->city);
        $address->setZipcode($checkResult->zip);
        $address->setStreet($checkResult->street . ' ' . $checkResult->hno);
    }
    
    private function setAddressHash(CustomerAddressEntity &$address): void
    {
        $customFields = $address->getCustomFields() ?? [];
        $customFields[self::ADDRESS_HASH_KEY] = $this->getAddressHash($address);
        
        $address->setCustomFields($customFields);
    }
    
    private function getAddressHash(CustomerAddressEntity &$address): string
    {
        $addressString = implode('-', [
            $address->getStreet(),
            $address->getZipcode() ?? '',
            $address->getCity(),
            $address->getCountry()?->getIso() ?? '',
        ]);
        
        return md5($addressString);
    }
}
