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
    /**
     * Creates a new user.
     *
     * @param string $username
     * @param string $password Hashed password
     * @param string $role
     * @param string|null $email
     * @return string|null The inserted ID or null on failure
     */
    public function createUser(string $username, string $password, string $role, ?string $email = null): ?string // Modified: $email is now ?string
    {
        // Check if username already exists
        if ($this->findByUsername($username)) {
            return null; // User already exists
        }

        $result = $this->collection->insertOne([
            'username' => $username,
            'password' => $password, 
            'role' => $role,
            'email' => $email,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
        ]);
        return (string) $result->getInsertedId();
    }
}
