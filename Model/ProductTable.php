<?php

class Box_Mod_Namesilo_Model_ProductTable implements Box_Mod_Products_Model_ProductTableInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * Get product configuration options
     */
    public function getConfig()
    {
        return [
            'tlds' => [
                'type' => 'text',
                'label' => 'TLDs',
                'required' => true,
                'description' => 'Comma separated list of TLDs (e.g., com,net,org,io)',
                'default' => 'com,net,org'
            ],
            'register_price' => [
                'type' => 'text',
                'label' => 'Default Registration Price',
                'required' => true,
                'description' => 'Default price for domain registration',
                'default' => '15.00'
            ],
            'transfer_price' => [
                'type' => 'text',
                'label' => 'Default Transfer Price',
                'required' => true,
                'description' => 'Default price for domain transfer',
                'default' => '15.00'
            ],
            'renew_price' => [
                'type' => 'text',
                'label' => 'Default Renewal Price',
                'required' => true,
                'description' => 'Default price for domain renewal',
                'default' => '15.00'
            ],
            'min_years' => [
                'type' => 'text',
                'label' => 'Minimum Registration Years',
                'required' => false,
                'description' => 'Minimum number of years for domain registration',
                'default' => '1'
            ],
            'max_years' => [
                'type' => 'text',
                'label' => 'Maximum Registration Years',
                'required' => false,
                'description' => 'Maximum number of years for domain registration',
                'default' => '10'
            ],
            'privacy_protection' => [
                'type' => 'checkbox',
                'label' => 'Enable Privacy Protection',
                'required' => false,
                'description' => 'Enable WHOIS privacy protection for domains',
                'default' => '1'
            ],
            'auto_renew' => [
                'type' => 'checkbox',
                'label' => 'Enable Auto Renewal',
                'required' => false,
                'description' => 'Automatically renew domains before expiration',
                'default' => '1'
            ],
            'epp_required' => [
                'type' => 'checkbox',
                'label' => 'EPP Code Required',
                'required' => false,
                'description' => 'Require EPP code for domain transfers',
                'default' => '1'
            ]
        ];
    }

    /**
     * Get product pricing configuration
     */
    public function getPricingConfig()
    {
        return [
            'setup' => [
                'type' => 'setup',
                'label' => 'Setup Fee',
                'description' => 'One-time setup fee for domain registration'
            ],
            'registration' => [
                'type' => 'domain',
                'label' => 'Registration Price',
                'description' => 'Price for domain registration'
            ],
            'transfer' => [
                'type' => 'domain',
                'label' => 'Transfer Price',
                'description' => 'Price for domain transfer'
            ],
            'renewal' => [
                'type' => 'domain',
                'label' => 'Renewal Price',
                'description' => 'Price for domain renewal'
            ]
        ];
    }

    /**
     * Get product type
     */
    public function getType()
    {
        return 'domain';
    }

    /**
     * Check if product is recurring
     */
    public function isRecurring()
    {
        return true;
    }

    /**
     * Create a new product
     */
    public function create($data)
    {
        // Validate required fields
        if (empty($data['config']['tlds'])) {
            throw new Box_Exception('At least one TLD is required');
        }

        // Validate pricing
        if (!is_numeric($data['config']['register_price']) || $data['config']['register_price'] < 0) {
            throw new Box_Exception('Registration price must be a valid number');
        }

        return true;
    }

    /**
     * Update product configuration
     */
    public function update($product, $data)
    {
        // Additional validation can be added here
        return true;
    }

    /**
     * Delete product
     */
    public function delete($product)
    {
        // Clean up any product-specific data
        return true;
    }

    /**
     * Get product details for order form
     */
    public function getProductDetails($product)
    {
        $config = $product->config;
        
        return [
            'type' => 'domain',
            'tlds' => $this->parseTlds($config['tlds'] ?? ''),
            'pricing' => [
                'register' => $config['register_price'] ?? '15.00',
                'transfer' => $config['transfer_price'] ?? '15.00',
                'renew' => $config['renew_price'] ?? '15.00'
            ],
            'features' => [
                'privacy_protection' => (bool)($config['privacy_protection'] ?? true),
                'auto_renew' => (bool)($config['auto_renew'] ?? true),
                'min_years' => (int)($config['min_years'] ?? 1),
                'max_years' => (int)($config['max_years'] ?? 10)
            ]
        ];
    }

    /**
     * Get renewal pricing
     */
    public function getRenewalPricing($product)
    {
        $config = $product->config;
        return [
            'price' => $config['renew_price'] ?? '15.00',
            'setup' => '0.00'
        ];
    }

    /**
     * Parse TLDs from string to array
     */
    private function parseTlds($tlds)
    {
        if (empty($tlds)) {
            return [];
        }

        $tldArray = explode(',', $tlds);
        $tldArray = array_map('trim', $tldArray);
        $tldArray = array_map(function($tld) {
            return ltrim($tld, '.');
        }, $tldArray);

        return array_filter($tldArray);
    }

    /**
     * Validate domain order data
     */
    public function validateOrderData($product, array $data)
    {
        $required = ['domain_sld', 'domain_tld'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Box_Exception("Field $field is required");
            }
        }

        $tlds = $this->parseTlds($product->config['tlds'] ?? '');
        if (!in_array($data['domain_tld'], $tlds)) {
            throw new Box_Exception('Selected TLD is not supported');
        }

        // Validate domain name format
        $domain = $data['domain_sld'] . '.' . $data['domain_tld'];
        if (!preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $domain)) {
            throw new Box_Exception('Invalid domain name format');
        }

        return true;
    }

    /**
     * Get TLD pricing for admin interface
     */
    public function getTldPricing($product)
    {
        $config = $product->config;
        $tlds = $this->parseTlds($config['tlds'] ?? '');
        
        $pricing = [];
        foreach ($tlds as $tld) {
            $pricing[$tld] = [
                'register' => $config['register_price'] ?? '15.00',
                'transfer' => $config['transfer_price'] ?? '15.00',
                'renew' => $config['renew_price'] ?? '15.00'
            ];
        }

        return $pricing;
    }
}