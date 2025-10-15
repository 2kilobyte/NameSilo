<?php

class Mod_NameSilo extends Box_Mod
{
    public function register()
    {
        // Extension hooks can be added here if needed
    }

    public function install()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `mod_namesilo_domain` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `client_id` bigint(20) DEFAULT NULL,
            `product_id` bigint(20) DEFAULT NULL,
            `order_id` bigint(20) DEFAULT NULL,
            `sld` varchar(100) DEFAULT NULL,
            `tld` varchar(10) DEFAULT NULL,
            `domain` varchar(255) DEFAULT NULL,
            `namesilo_order_id` varchar(100) DEFAULT NULL,
            `expires_at` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`),
            KEY `domain_idx` (`domain`),
            KEY `order_id_idx` (`order_id`),
            KEY `product_id_idx` (`product_id`),
            KEY `expires_at_idx` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        $this->di['db']->exec($sql);
        return true;
    }

    public function uninstall()
    {
        $this->di['db']->exec("DROP TABLE IF EXISTS `mod_namesilo_domain`");
        return true;
    }

    public function getConfig()
    {
        return $this->getModuleConfig();
    }
}