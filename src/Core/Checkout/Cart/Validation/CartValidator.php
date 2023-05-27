<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation;

use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\ValidationFailedCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\FakeNameCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidNameCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidEmailAddressCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidBillingAddressCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidShippingAddressCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\BillingAddressChangedCartError;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\ShippingAddressChangedCartError;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CartValidator implements CartValidatorInterface
{
    
    private const ADDRESS_HASH_KEY = 'al_address_md5';
    private const EMAIL_HASH_KEY = 'al_email_md5';
    private const NAME_HASH_KEY = 'al_name_md5';
    private const FAKE_NAME_HASH_KEY = 'al_fake_name_md5';
    
    private SystemConfigService $systemConfigService;
    private array $checkClients = [];
    private array $configs = [];
    
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }
    
    /**
     * @param string $channelId
     *
     * @return \stdClass
     */
    private function getConfig(string $channelId): \stdClass
    {
        if (isset($this->configs[$channelId])) {
            return $this->configs[$channelId];
        }
        
        $config = (object)$this->systemConfigService->get('AdresslaborCheckPlugin.config', $channelId);
        $this->configs[$channelId] = $config;
        
        return $config;
    }
    
    /**
     * @param string $channelId
     *
     * @return CheckClient
     * @throws GuzzleException
     * @throws InvalidProductException
     */
    private function getCheckClient(string $channelId): CheckClient
    {
        if (isset($this->checkClients[$channelId])) {
            return $this->checkClients[$channelId];
        }
        
        $config = $this->getConfig($channelId);
        
        $this->checkClients[$channelId] = new CheckClient($config->apicid, $config->apikey, false);
        
        return $this->checkClients[$channelId];
    }
    
    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        $customer = $context->getCustomer();
        if (!$customer) {
            return;
        }
        
        $channelId = $context->getSalesChannelId();
        
        $config = $this->getConfig($channelId);
        
        try {
            $checkClient = $this->getCheckClient($channelId);
            
            $this->checkNameIfNeeded($config, $errors, $checkClient, $customer);
            
            $this->checkFakeNameIfNeeded($config, $errors, $checkClient, $customer);
            
            $this->checkEmailIfNeeded($config, $errors, $checkClient, $customer);
            
            $this->checkShippingAddressIfNeeded($config, $errors, $checkClient, $customer);
            
            $this->checkBillingAddressIfNeeded($config, $errors, $checkClient, $customer);
        } catch (InvalidProductException|GuzzleException $exception) {
            
            error_log($exception->getMessage());
            
            if ($config->forceValidation) {
                $errors->add(new ValidationFailedCartError());
            }
        }
    }
    
    // Name Check
    
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
    private function checkNameIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer,
    ): void {
        if (!$config->validateName
            || (!$customer->getFirstName() && !$customer->getLastName())
            || !$this->isNameAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->nameCheckB2C(
            $customer->getFirstName(),
            $customer->getLastName(),
            $customer->getSalutation(),
            $customer->getTitle()
        );
        
        switch ($checkResult->trafficlight) {
            case 'rot':
                $errors->add(new InvalidNameCartError($checkResult->resulttext));
                break;
            case 'gelb':
            case 'gruen':
                $this->setNameFromCheckResult($customer, $checkResult);
                $this->setNameHash($customer);
                break;
        }
    }
    
    private function isNameAlreadyChecked(CustomerEntity &$customer): bool
    {
        $customFields = $customer->getCustomFields() ?? [];
        if (!isset($customFields[self::NAME_HASH_KEY])) {
            return false;
        }
        
        return $this->getNameHash($customer) === $customFields[self::NAME_HASH_KEY];
    }
    
    
    private function setNameFromCheckResult(CustomerEntity &$customer, \stdClass &$checkResult): void
    {
        $customer->setFirstName($checkResult->firstname);
        $customer->setLastName($checkResult->lastname);
        $customer->setSalutation($checkResult->salutation);
        $customer->setTitle($checkResult->title);
    }
    
    private function setNameHash(CustomerEntity &$customer): void
    {
        $customFields = $customer->getCustomFields() ?? [];
        $customFields[self::NAME_HASH_KEY] = md5($customer->getEmail());
        
        $customer->setCustomFields($customFields);
    }
    
    private function getNameHash(CustomerEntity &$customer): string
    {
        $nameString = implode('-', [
            $customer->getFirstName(),
            $customer->getLastName(),
            $customer->getSalutation(),
            $customer->getTitle(),
        ]);
        
        return md5($nameString);
    }
    
    // Fake Check
    
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
            || !$this->isFakeNameAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->fakeCheck(
            $customer->getFirstName(),
            $customer->getLastName()
        );
        
        switch ($checkResult->trafficlight) {
            case 'rot':
            case 'gelb':
                $errors->add(new FakeNameCartError($checkResult->resulttext));
                break;
            case 'gruen':
                $this->setFakeNameHash($customer);
                break;
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
            $customer->getSalutation(),
            $customer->getTitle(),
        ]);
        
        return md5($nameString);
    }
    
    
    // Email Check
    
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
            || !$this->isEmailAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->emailCheck($customer->getEmail());
        
        switch ($checkResult->trafficlight) {
            case 'rot':
                $errors->add(new InvalidEmailAddressCartError($checkResult->resulttext));
                break;
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
    
    // Address Check
    
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
        if ($config->validateShippingAddress
            && $shippingAddress
            && !$this->isAddressAlreadyChecked($shippingAddress)
        ) {
            
            $checkResult = $this->validateAddress($checkClient, $shippingAddress);
            
            switch ($checkResult[0]->trafficlight) {
                case 'rot':
                    $errors->add(new InvalidShippingAddressCartError($checkResult[0]->resulttext));
                    break;
                case 'gelb':
                    $errors->add(new ShippingAddressChangedCartError($checkResult[0]->resulttext));
                    $this->setAddressFromCheckResult($shippingAddress, $checkResult[0]);
                    $this->setAddressHash($shippingAddress);
                    break;
                case 'gruen':
                    $this->setAddressFromCheckResult($shippingAddress, $checkResult[0]);
                    $this->setAddressHash($shippingAddress);
                    break;
            }
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
        if ($config->validateBillingAddress
            && $billingAddress
            && $this->isAddressAlreadyChecked($billingAddress)
        ) {
            
            $checkResult = $this->validateAddress($checkClient, $billingAddress);
            
            switch ($checkResult[0]->trafficlight) {
                case 'rot':
                    $errors->add(new InvalidBillingAddressCartError($checkResult[0]->resulttext));
                    break;
                case 'gelb':
                    $errors->add(new BillingAddressChangedCartError($checkResult[0]->resulttext));
                    $this->setAddressFromCheckResult($billingAddress, $checkResult[0]);
                    $this->setAddressHash($billingAddress);
                    break;
                case 'gruen':
                    $this->setAddressFromCheckResult($billingAddress, $checkResult[0]);
                    $this->setAddressHash($billingAddress);
                    break;
            }
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
            $address->getZipcode(),
            $address->getCity(),
            $address->getCountry(),
        ]);
        
        return md5($addressString);
    }
}
