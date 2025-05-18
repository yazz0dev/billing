<?php // src/Core/Database.php

namespace App\Core;

use MongoDB\Client;
use MongoDB\Database as MongoDbNativeDatabase;
use Exception;

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
                self::$client->listDatabases();
                self::$db = self::$client->selectDatabase($mongoConfig['database_name']);
            } catch (Exception $e) {
                error_log("MongoDB Connection Error: " . $e->getMessage());
                $appConfig = require PROJECT_ROOT . '/config/app.php';
                if ($appConfig['debug']) {
                    throw new Exception("Database connection failed: " . $e->getMessage() . " URI: " . $mongoConfig['uri']);
                }
                throw new Exception("Could not connect to the database.");
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
