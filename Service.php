<?php
class Box_Mod_NameSilo_Service {
    private $api;
    
    public function __construct() {
        $config = $this->getConfig();
        $this->api = new NameSiloAPI($config['api_key']);
    }
    
    public function checkAvailability($domain) {
        try {
            return $this->api->checkDomainAvailability($domain);
        } catch (Exception $e) {
            error_log("NameSilo API Error: " . $e->getMessage());
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function registerDomain($domain, $years = 1, $client_id = null) {
        try {
            // Get client contact information from FOSSbilling
            $contact_info = $this->getClientContactInfo($client_id);
            
            $result = $this->api->registerDomain($domain, $years, $contact_info);
            
            // Log the registration
            $this->logDomainRegistration($domain, $client_id, $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Domain registration failed: " . $e->getMessage());
            throw new Exception("Domain registration failed: " . $e->getMessage());
        }
    }
    
    public function renewDomain($domain, $years = 1) {
        try {
            return $this->api->renewDomain($domain, $years);
        } catch (Exception $e) {
            error_log("Domain renewal failed: " . $e->getMessage());
            throw new Exception("Domain renewal failed: " . $e->getMessage());
        }
    }
    
    private function getConfig() {
        return include dirname(__FILE__) . '/config.php';
    }
    
    private function getClientContactInfo($client_id) {
        // Get client details from FOSSbilling database
        $client = $this->getClientById($client_id);
        
        if (!$client) {
            // Use default contact info from config
            $config = $this->getConfig();
            return $config['default_contact'];
        }
        
        // Format contact info for NameSilo API
        return [
            'fn' => $client->first_name,
            'ln' => $client->last_name,
            'ad' => $client->address_1 ?? 'Not Provided',
            'cy' => $client->city ?? 'Not Provided',
            'st' => $client->state ?? 'Not Provided',
            'zp' => $client->postcode ?? '12345',
            'ct' => $client->country ?? 'BD',
            'em' => $client->email,
            'ph' => $this->formatPhone($client->phone ?? '123.123.1234')
        ];
    }
    
    private function getClientById($client_id) {
        // FOSSbilling database query to get client details
        $db = $this->getDatabase();
        $stmt = $db->prepare("SELECT * FROM client WHERE id = :id");
        $stmt->execute([':id' => $client_id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }
    
    private function formatPhone($phone) {
        // Format phone number for NameSilo (format: 123.123.1234)
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) == 10) {
            return substr($cleaned, 0, 3) . '.' . substr($cleaned, 3, 3) . '.' . substr($cleaned, 6);
        }
        return '123.123.1234';
    }
    
    private function logDomainRegistration($domain, $client_id, $result) {
        $db = $this->getDatabase();
        $stmt = $db->prepare("
            INSERT INTO domain_registrations 
            (domain, client_id, order_id, amount, registration_date) 
            VALUES (:domain, :client_id, :order_id, :amount, NOW())
        ");
        $stmt->execute([
            ':domain' => $domain,
            ':client_id' => $client_id,
            ':order_id' => $result['order_id'] ?? '',
            ':amount' => $result['amount'] ?? 0
        ]);
    }
    
    private function getDatabase() {
        // Get FOSSbilling database connection
        return Box_Db::getPdo();
    }
}
?>