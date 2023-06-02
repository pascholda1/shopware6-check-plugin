<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits\AddressCheckTrait;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits\EmailCheckTrait;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits\FakeCheckTrait;
use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Traits\NameCheckTrait;
use Adresslabor\CheckService\CheckClient;
use Adresslabor\CheckService\Exception\InvalidProductException;
use GuzzleHttp\Exception\GuzzleException;

use Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error\ValidationFailedCartError;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CartValidator implements CartValidatorInterface
{
    use NameCheckTrait;
    use FakeCheckTrait;
    use EmailCheckTrait;
    use AddressCheckTrait;
    
    private SystemConfigService $systemConfigService;
    private EntityRepository $salutationRepository;
    private array $checkClients = [];
    private array $configs = [];
    
    
    public function __construct(SystemConfigService $systemConfigService, EntityRepository $salutationRepository)
    {
        $this->systemConfigService = $systemConfigService;
        
        $this->salutationRepository = $salutationRepository;
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
            
            $this->setSalutations($this->salutationRepository, $context,  $config);
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
    
    
}
