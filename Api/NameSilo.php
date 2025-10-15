<?php

class Box_Mod_Namesilo_Api_NameSilo
{
    private $apiKey;
    private $sandbox = false;
    private $apiUrl = 'https://www.namesilo.com/api/';
    private $sandboxUrl = 'https://sandbox.namesilo.com/api/';

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function setSandbox($sandbox)
    {
        $this->sandbox = $sandbox;
    }

    private function getBaseUrl()
    {
        return $this->sandbox ? $this->sandboxUrl : $this->apiUrl;
    }

    private function makeRequest($operation, $params = array())
    {
        $url = $this->getBaseUrl() . $operation . '?' . http_build_query(array_merge(
            ['version' => 1, 'type' => 'xml', 'key' => $this->apiKey],
            $params
        ));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FOSSBilling NameSilo Module'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Box_Exception('NameSilo API request failed: ' . $error);
        }

        // Parse XML response
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new Box_Exception('Invalid XML response from NameSilo API');
        }

        // Check for API errors - NameSilo success codes: 300, 301, 302
        $replyCode = (string)$xml->reply->code;
        $successCodes = ['300', '301', '302'];
        
        if (!in_array($replyCode, $successCodes)) {
            $error = (string)$xml->reply->detail;
            throw new Box_Exception('NameSilo API Error (' . $replyCode . '): ' . $error);
        }

        return $xml;
    }

    public function checkAvailability($domain)
    {
        $result = $this->makeRequest('checkRegisterAvailability', ['domains' => $domain]);
        
        $domainNode = $result->reply->children();
        $available = false;
        $price = '0.00';
        
        foreach ($domainNode as $node) {
            if ($node->getName() == 'available' && (string)$node->attributes()->domain == $domain) {
                $available = (string)$node == 'yes';
                $price = (string)$node->attributes()->price ?? '0.00';
                break;
            }
        }

        return [
            'available' => $available,
            'domain' => $domain,
            'price' => $price
        ];
    }

    public function registerDomain($domain, $years, $contact)
    {
        $params = [
            'domain' => $domain,
            'years' => $years,
            'auto_renew' => 0, // Let FOSSBilling handle renewals
        ];

        // Add contact information
        $params = array_merge($params, $contact);

        $result = $this->makeRequest('registerDomain', $params);

        return [
            'order_id' => (string)$result->reply->order_id,
            'domain' => $domain,
            'total_amount' => (string)$result->reply->total_amount
        ];
    }

    public function transferDomain($domain, $authCode)
    {
        $params = [
            'domain' => $domain,
            'auth' => $authCode
        ];

        $result = $this->makeRequest('transferDomain', $params);

        return [
            'order_id' => (string)$result->reply->order_id,
            'domain' => $domain,
            'amount' => (string)$result->reply->amount
        ];
    }

    public function renewDomain($domain, $years)
    {
        $params = [
            'domain' => $domain,
            'years' => $years
        ];

        $result = $this->makeRequest('renewDomain', $params);

        return [
            'order_id' => (string)$result->reply->order_id,
            'domain' => $domain,
            'amount' => (string)$result->reply->amount
        ];
    }

    public function getDomainInfo($domain)
    {
        $result = $this->makeRequest('getDomainInfo', ['domain' => $domain]);

        $nameservers = [];
        if (isset($result->reply->nameservers)) {
            foreach ($result->reply->nameservers->children() as $ns) {
                $nameservers[] = (string)$ns;
            }
        }

        return [
            'status' => (string)$result->reply->status,
            'created' => (string)$result->reply->created,
            'expires' => (string)$result->reply->expires,
            'nameservers' => $nameservers,
            'locked' => (string)$result->reply->locked === 'Yes',
            'auto_renew' => (string)$result->reply->auto_renew === 'Yes'
        ];
    }

    public function updateNameservers($domain, $nameservers)
    {
        $params = ['domain' => $domain];
        $i = 1;
        foreach ($nameservers as $ns) {
            if (!empty($ns)) {
                $params['ns' . $i] = $ns;
                $i++;
            }
            if ($i > 5) break; // NameSilo supports max 5 nameservers
        }

        // Ensure at least 2 nameservers
        while ($i <= 2) {
            $params['ns' . $i] = 'ns' . $i . '.namesilo.com';
            $i++;
        }

        $result = $this->makeRequest('changeNameServers', $params);

        return ['success' => true];
    }

    public function getDomainPricing($tld = null)
    {
        $result = $this->makeRequest('getPrices');

        $pricing = [];
        if (isset($result->reply->price)) {
            foreach ($result->reply->price as $price) {
                $tldName = (string)$price->attributes()->tld;
                $pricing[$tldName] = [
                    'registration' => (string)$price->registration,
                    'renew' => (string)$price->renew,
                    'transfer' => (string)$price->transfer
                ];
            }
        }

        if ($tld) {
            return $pricing[$tld] ?? null;
        }

        return $pricing;
    }
}