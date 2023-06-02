<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\InvalidNameCartError;
use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;

trait NameCheckTrait
{
    
    private const NAME_HASH_KEY = 'al_name_md5';
    
    private EntityCollection $salutations;
    
    private array $salutationKeyMap;
    
    private function setSalutations(
        EntityRepository $salutationRepository,
        SalesChannelContext $context,
        \stdClass $config
    ): void {
        $this->salutations = $salutationRepository->search(new Criteria(), $context->getContext())->getEntities();
        
        $this->salutationKeyMap = [
            "Herr" => $config->technicalNameMr,
            "Frau" => $config->technicalNameMrs,
        ];
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
    private function checkNameIfNeeded(
        \stdClass $config,
        ErrorCollection $errors,
        CheckClient $checkClient,
        CustomerEntity $customer,
    ): void {
        if (!$config->validateName
            || (!$customer->getFirstName() && !$customer->getLastName())
            || $this->isNameAlreadyChecked($customer)
        ) {
            return;
        }
        
        $checkResult = $checkClient->nameCheckB2C(
            $customer->getFirstName(),
            $customer->getLastName(),
            $customer->getSalutation()?->getDisplayName() ?? '',
            $customer->getTitle() ?? ''
        );
        
        switch ($checkResult->trafficlight) {
            case 'gelb':
            case 'gruen':
                $this->setNameFromCheckResult($customer, $checkResult);
                $this->setNameHash($customer);
                break;
            default:
                $errors->add(new InvalidNameCartError($checkResult->resulttext));
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
        $customer->setTitle($checkResult->title);
        
        $salutation = $this->getSalutationFromCheckResult($checkResult);
        if ($salutation) {
            $customer->setSalutation($salutation);
        }
    }
    
    private function getSalutationFromCheckResult(\stdClass &$checkResult): ?SalutationEntity
    {
        if (!isset($this->salutationKeyMap[$checkResult->salutation])
            || !$this->salutations
        ) {
            return null;
        }
        
        $salutationKey = $this->salutationKeyMap[$checkResult->salutation];
        
        foreach ($this->salutations as $salutation) {
            if ($salutation->getSalutationKey() === $salutationKey) {
                return $salutation;
            }
        }
        
        return null;
    }
    
    private function setNameHash(CustomerEntity &$customer): void
    {
        $customFields = $customer->getCustomFields() ?? [];
        $customFields[self::NAME_HASH_KEY] = $this->getNameHash($customer);
        
        $customer->setCustomFields($customFields);
    }
    
    private function getNameHash(CustomerEntity &$customer): string
    {
        $nameString = implode('-', [
            $customer->getFirstName(),
            $customer->getLastName(),
            $customer->getSalutation()?->getSalutationKey() ?? '',
            $customer->getTitle(),
        ]);
        
        return md5($nameString);
    }
}
