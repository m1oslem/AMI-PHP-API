<?php

namespace App;

use PAMI\Client\Impl\ClientImpl;
use PAMI\Client\Exception\PamiException;

class AmiClient
{
    private static $pamiClient = null;

    private function __construct()
    {
        // private constructor to prevent direct instantiation
    }

    private function __clone()
    {
        // private clone to prevent cloning
    }

    public static function getInstance()
    {
        if (self::$pamiClient === null) {
            $config = require __DIR__ . '/../config/ami.php';
            
            $pamiClientOptions = [
                'host' => $config['host'],
                'scheme' => 'tcp://',
                'port' => $config['port'],
                'username' => $config['username'],
                'secret' => $config['secret'],
                'connect_timeout' => $config['connect_timeout'],
                'read_timeout' => $config['read_timeout']
            ];

            self::$pamiClient = new ClientImpl($pamiClientOptions);
        }
        return self::$pamiClient;
    }

    public static function login()
    {
        try {
            $client = self::getInstance();
            $client->open();
            $_SESSION['ami_logged_in'] = true;
            return true;
        } catch (PamiException $e) {
            // Log the error message in a real app
            // error_log($e->getMessage());
            $_SESSION['ami_logged_in'] = false;
            return false;
        }
    }

    public static function logout()
    {
        if (self::$pamiClient !== null && isset($_SESSION['ami_logged_in']) && $_SESSION['ami_logged_in']) {
            try {
                self::$pamiClient->close();
            } catch (PamiException $e) {
                // Log error
            }
            self::$pamiClient = null;
            $_SESSION['ami_logged_in'] = false;
        }
    }
} 