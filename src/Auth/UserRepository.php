<?php // src/Auth/UserRepository.php

namespace App\Auth;

use App\Core\Database;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

class UserRepository
{
    private Collection $collection;

    public function __construct()
    {
        $this->collection = Database::connect()->selectCollection('users'); // Changed from 'user' to 'users' (convention)
    }

    public function findByUsername(string $username): ?BSONDocument
    {
        return $this->collection->findOne(['username' => $username]);
    }

    // You can add createUser, findById, etc. methods here
    public function createUser(string $username, string $password, string $role, string $email = null): ?string
    {
        // IMPORTANT: Hash passwords in a real application!
        // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // For this example, keeping plaintext for simplicity, but DO NOT DO THIS IN PRODUCTION.
        if ($this->findByUsername($username)) {
            return null; // User already exists
        }

        $result = $this->collection->insertOne([
            'username' => $username,
            'password' => $password, // HASH THIS: $hashedPassword
            'role' => $role,
            'email' => $email,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
        ]);
        return (string) $result->getInsertedId();
    }
}
