<?php
class NameSiloAPI {
    private $api_key;
    private $base_url = 'https://www.namesilo.com/api/';
    private $version = '1';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    public function checkDomainAvailability($domain) {
        $params = [
            'domain' => $domain,
            'key' => $this->api_key,
            'version' => $this->version
        ];
        
        $response = $this->callAPI('checkRegisterAvailability', $params);
        
        if (isset($response->reply->code) && $response->reply->code == 300) {
            if (isset($response->reply->available) && $response->reply->available == 'yes') {
                return ['available' => true, 'price' => $this->getDomainPrice($domain)];
            }
        }
        
        return ['available' => false, 'message' => $response->reply->detail ?? 'Domain not available'];
    }
    
    public function registerDomain($domain, $years = 1, $contact_info = []) {
        $params = [
            'domain' => $domain,
            'years' => $years,
            'private' => 1, // Enable free WHOIS privacy
            'auto_renew' => 0,
            'key' => $this->api_key,
            'version' => $this->version
        ];
        
        // Add contact information
        $params = array_merge($params, $contact_info);
        
        $response = $this->callAPI('registerDomain', $params);
        
        if (isset($response->reply->code) && $response->reply->code == 300) {
            return [
                'success' => true,
                'order_id' => $response->reply->order_id ?? '',
                'amount' => $response->reply->amount_charged ?? 0,
                'domain' => $domain
            ];
        } else {
            throw new Exception($response->reply->detail ?? 'Registration failed');
        }
    }
    
    public function getDomainPrice($domain) {
        $tld = strtolower(pathinfo($domain, PATHINFO_EXTENSION));
        $pricing = include dirname(__FILE__) . '/config.php';
        
        return $pricing['pricing'][$tld]['register'] ?? 14.99;
    }
    
    public function renewDomain($domain, $years = 1) {
        $params = [
            'domain' => $domain,
            'years' => $years,
            'key' => $this->api_key,
            'version' => $this->version
        ];
        
        $response = $this->callAPI('renewDomain', $params);
        
        if (isset($response->reply->code) && $response->reply->code == 300) {
            return ['success' => true, 'amount' => $response->reply->amount_charged ?? 0];
        } else {
            throw new Exception($response->reply->detail ?? 'Renewal failed');
        }
    }
    
    private function callAPI($operation, $params) {
        $url = $this->base_url . $operation . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception('API connection failed: HTTP ' . $http_code);
        }
        
        return json_decode($response);
    }
}
?>