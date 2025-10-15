<?php

class Box_Mod_Namesilo_Service
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * Get module configuration
     */
    public function getModuleConfig()
    {
        return $this->di['mod_config']('namesilo');
    }

    /**
     * Get API client
     */
    public function getApiClient()
    {
        $config = $this->getModuleConfig();
        
        if (!isset($config['api_key']) || empty($config['api_key'])) {
            throw new Box_Exception('NameSilo API key is not configured');
        }

        $api = new Box_Mod_Namesilo_Api_NameSilo();
        $api->setApiKey($config['api_key']);
        $api->setSandbox(isset($config['sandbox']) ? (bool)$config['sandbox'] : false);
        
        return $api;
    }

    /**
     * Register a new domain
     */
    public function registerDomain($order, $product, array $data)
    {
        try {
            $api = $this->getApiClient();
            
            // Extract domain from data
            $sld = $data['domain_sld'] ?? '';
            $tld = $data['domain_tld'] ?? '';
            $domain = $sld . '.' . $tld;

            if (empty($sld) || empty($tld)) {
                throw new Box_Exception('Domain SLD and TLD are required');
            }

            // Get client details
            $client = $this->di['db']->getExistingModelById('Client', $order->client_id);
            
            // Prepare registration data
            $years = $data['domain_years'] ?? 1;
            $contact = array(
                'fn' => $data['first_name'] ?? $client->first_name,
                'ln' => $data['last_name'] ?? $client->last_name,
                'ad' => $data['address_1'] ?? $client->address_1,
                'cy' => $data['city'] ?? $client->city,
                'st' => $data['state'] ?? $client->state,
                'zp' => $data['postcode'] ?? $client->postcode,
                'ct' => $data['country'] ?? $client->country,
                'em' => $data['email'] ?? $client->email,
                'ph' => $this->formatPhone($data['phone'] ?? $client->phone),
            );

            // Register domain with NameSilo
            $result = $api->registerDomain($domain, $years, $contact);

            // Save domain record
            $domainRecord = $this->di['db']->dispense('mod_namesilo_domain');
            $domainRecord->client_id = $order->client_id;
            $domainRecord->product_id = $product->id;
            $domainRecord->order_id = $order->id;
            $domainRecord->sld = $sld;
            $domainRecord->tld = $tld;
            $domainRecord->domain = $domain;
            $domainRecord->namesilo_order_id = $result['order_id'] ?? '';
            $domainRecord->expires_at = date('Y-m-d H:i:s', strtotime("+{$years} years"));
            $domainRecord->created_at = date('Y-m-d H:i:s');
            $domainRecord->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($domainRecord);

            return $domainRecord;
        } catch (Exception $e) {
            throw new Box_Exception('Domain registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Transfer domain
     */
    public function transferDomain($order, $product, array $data)
    {
        try {
            $api = $this->getApiClient();
            
            $sld = $data['domain_sld'] ?? '';
            $tld = $data['domain_tld'] ?? '';
            $domain = $sld . '.' . $tld;
            $authCode = $data['domain_transfer_auth_code'] ?? '';

            if (empty($authCode)) {
                throw new Box_Exception('Authorization code is required for domain transfer');
            }

            $result = $api->transferDomain($domain, $authCode);

            // Save transfer record
            $domainRecord = $this->di['db']->dispense('mod_namesilo_domain');
            $domainRecord->client_id = $order->client_id;
            $domainRecord->product_id = $product->id;
            $domainRecord->order_id = $order->id;
            $domainRecord->sld = $sld;
            $domainRecord->tld = $tld;
            $domainRecord->domain = $domain;
            $domainRecord->namesilo_order_id = $result['order_id'] ?? '';
            $domainRecord->created_at = date('Y-m-d H:i:s');
            $domainRecord->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($domainRecord);

            return $domainRecord;
        } catch (Exception $e) {
            throw new Box_Exception('Domain transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Renew domain
     */
    public function renewDomain($order, $product, array $data)
    {
        try {
            $api = $this->getApiClient();
            
            $domainRecord = $this->di['db']->findOne('mod_namesilo_domain', 'order_id = ?', array($order->id));
            if (!$domainRecord) {
                throw new Box_Exception('Domain record not found');
            }

            $years = $data['domain_years'] ?? 1;
            $result = $api->renewDomain($domainRecord->domain, $years);

            // Update expiry date
            $domainRecord->expires_at = date('Y-m-d H:i:s', strtotime("+{$years} years"));
            $domainRecord->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($domainRecord);

            return $domainRecord;
        } catch (Exception $e) {
            throw new Box_Exception('Domain renewal failed: ' . $e->getMessage());
        }
    }

    /**
     * Get domain info
     */
    public function getDomainInfo($domainId)
    {
        try {
            $domainRecord = $this->di['db']->load('mod_namesilo_domain', $domainId);
            if (!$domainRecord) {
                throw new Box_Exception('Domain not found');
            }

            $api = $this->getApiClient();
            $info = $api->getDomainInfo($domainRecord->domain);

            return array_merge($domainRecord->export(), $info);
        } catch (Exception $e) {
            throw new Box_Exception('Failed to get domain info: ' . $e->getMessage());
        }
    }

    /**
     * Update nameservers
     */
    public function updateNameservers($domainId, array $nameservers)
    {
        try {
            $domainRecord = $this->di['db']->load('mod_namesilo_domain', $domainId);
            if (!$domainRecord) {
                throw new Box_Exception('Domain not found');
            }

            $api = $this->getApiClient();
            $result = $api->updateNameservers($domainRecord->domain, $nameservers);

            return $result;
        } catch (Exception $e) {
            throw new Box_Exception('Failed to update nameservers: ' . $e->getMessage());
        }
    }

    /**
     * Get domain pricing
     */
    public function getPricing($tld)
    {
        try {
            $api = $this->getApiClient();
            return $api->getDomainPricing($tld);
        } catch (Exception $e) {
            throw new Box_Exception('Failed to get pricing: ' . $e->getMessage());
        }
    }

    /**
     * Check domain availability
     */
    public function checkAvailability($domain)
    {
        try {
            $api = $this->getApiClient();
            return $api->checkAvailability($domain);
        } catch (Exception $e) {
            throw new Box_Exception('Domain availability check failed: ' . $e->getMessage());
        }
    }

    /**
     * Format phone number for NameSilo
     */
    private function formatPhone($phone)
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure proper format
        if (substr($phone, 0, 1) !== '+' && substr($phone, 0, 1) !== '1') {
            $phone = '1' . $phone; // Default to US/Canada format
        }
        
        return $phone;
    }

    /**
     * Get product configuration options
     */
    public function getProductConfig($product)
    {
        $productTable = new Box_Mod_Namesilo_Model_ProductTable();
        $productTable->setDi($this->di);
        return $productTable->getProductDetails($product);
    }

    /**
     * Validate domain order
     */
    public function validateDomainOrder($product, $data)
    {
        $productTable = new Box_Mod_Namesilo_Model_ProductTable();
        $productTable->setDi($this->di);
        return $productTable->validateOrderData($product, $data);
    }
}