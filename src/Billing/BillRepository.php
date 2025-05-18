<?php // src/Billing/BillRepository.php

namespace App\Billing;

use App\Core\Database;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Session; // For transactions

class BillRepository
{
    private Collection $billCollection;

    public function __construct()
    {
        $this->billCollection = Database::connect()->selectCollection('bills_new');
    }

    public function createBill(array $items, float $totalAmount, string $userId, string $username, ?Session $dbSession = null): ?string
    {
        $billData = [
            'items' => $items, // Should be an array of item details
            'total_amount' => $totalAmount,
            'user_id' => $userId ? new ObjectId($userId) : null, // Store as ObjectId if available
            'username' => $username,
            'created_at' => new UTCDateTime(),
        ];
        $options = $dbSession ? ['session' => $dbSession] : [];
        $result = $this->billCollection->insertOne($billData, $options);
        return (string) $result->getInsertedId();
    }

    public function findAll(array $options = ['sort' => ['created_at' => -1]]): array
    {
        return $this->billCollection->find([], $options)->toArray();
    }

    public function findById(string $billId): ?object // Returns BSONDocument as object
    {
        try {
            return $this->billCollection->findOne(['_id' => new ObjectId($billId)]);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return null;
        }
    }
}
