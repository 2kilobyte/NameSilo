<?php

return array(
    'id'          => 'namesilo',
    'type'        => 'product',
    'module'      => 'namesilo',
    'title'       => 'NameSilo Domain Registrar',
    'version'     => '1.0.0',
    'author'      => 'FOSSBilling',
    'description' => 'NameSilo domain registration and management module for FOSSBilling',
    
    'product' => array(
        'type'          => 'domain',
        'auto_setup'    => 'on_order',
        'form'          => array(
            'id'        => 'domain',
            'renderer'  => 'domain',
        ),
    ),
    
    'routes' => array(
        '/namesilo' => array(
            'name' => 'namesilo-index',
            'controller' => 'mod_namesilo_index',
        ),
    ),
);