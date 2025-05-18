<?php // src/Core/Database.php

namespace App\Core;

use MongoDB\Client;
use MongoDB\Database as MongoDbNativeDatabase;
// use Exception; // No longer throwing generic Exception for MongoDB errors from here
use MongoDB\Driver\Exception\Exception as MongoDriverException; // For catching MongoDB specific exceptions

class Database
{
    private static ?Client $client = null;
    private static ?MongoDbNativeDatabase $db = null;

    public static function connect(): MongoDbNativeDatabase
    {
        if (self::$db === null) {
            $config = require PROJECT_ROOT . '/config/database.php';
            $mongoConfig = $config['mongodb'];

            try {
                self::$client = new Client(
                    $mongoConfig['uri'],
                    $mongoConfig['options'] ?? [],
                    $mongoConfig['driver_options'] ?? []
                );
                // Test connection by listing databases (or ping)
                // This line can throw various MongoDB exceptions if connection fails
                self::$client->listDatabases(); 
                self::$db = self::$client->selectDatabase($mongoConfig['database_name']);
            } catch (MongoDriverException $e) { // Catch MongoDB driver specific exceptions
                error_log("MongoDB Connection Error: " . $e->getMessage() . " Type: " . get_class($e));
                // Re-throw the original MongoDB exception to be handled by higher-level handlers (e.g., in index.php)
                throw $e; 
            } catch (\Exception $e) { // Catch other generic exceptions that might occur during setup (e.g., config file not found)
                error_log("Generic Database Setup Error: " . $e->getMessage());
                $appConfig = require PROJECT_ROOT . '/config/app.php';
                // For non-MongoDB exceptions, we can throw a new generic one
                // or a more specific custom application exception if preferred.
                if ($appConfig['debug'] ?? false) {
                    throw new \Exception("Database setup failed: " . $e->getMessage() . (isset($mongoConfig['uri']) ? " (URI was configured)" : ""));
                }
                throw new \Exception("Could not configure the database connection due to a non-driver issue.");
            }
        }
        return self::$db;
    }

    public static function getClient(): ?Client
    {
        if (self::$client === null) {
            self::connect();
        }
        return self::$client;
    }
}
