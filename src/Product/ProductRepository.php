<?php // src/Product/ProductRepository.php

namespace App\Product;

use App\Core\Database;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;

class ProductRepository
{
    private Collection $collection;

    public function __construct()
    {
        $this->collection = Database::connect()->selectCollection('products');
    }

    public function findAll(array $options = ['sort' => ['name' => 1]]): array
    {
        return $this->collection->find([], $options)->toArray();
    }

    public function findById(string $id): ?BSONDocument
    {
        try {
            return $this->collection->findOne(['_id' => new ObjectId($id)]);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return null; // Invalid ObjectId format
        }
    }
    
    public function findByNameOrBarcode(string $identifier): ?BSONDocument // If barcode is stored in name or another field
    {
        // Example: searching by name OR a hypothetical 'barcode' field
        return $this->collection->findOne([
            '$or' => [
                ['name' => $identifier],
                ['barcode' => $identifier] 
            ]
        ]);
    }


    public function create(string $name, float $price, int $stock): ?string
    {
        if ($price < 0 || $stock < 0) return null;
        $result = $this->collection->insertOne([
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'updated_at' => new \MongoDB\BSON\UTCDateTime(),
        ]);
        return (string) $result->getInsertedId();
    }

    public function update(string $id, array $data): bool
    {
        try {
            $objectId = new ObjectId($id);
            $updateData = ['$set' => []];
            if (isset($data['name'])) $updateData['$set']['name'] = (string) $data['name'];
            if (isset($data['price'])) $updateData['$set']['price'] = (float) $data['price'];
            if (isset($data['stock'])) $updateData['$set']['stock'] = (int) $data['stock'];
            
            if (empty($updateData['$set'])) return false; // Nothing to update

            $updateData['$set']['updated_at'] = new \MongoDB\BSON\UTCDateTime();

            $result = $this->collection->updateOne(['_id' => $objectId], $updateData);
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }
    
    public function updateStock(string $id, int $newStock, ?\MongoDB\Driver\Session $session = null): bool
    {
        try {
            $options = $session ? ['session' => $session] : [];
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => ['stock' => $newStock, 'updated_at' => new \MongoDB\BSON\UTCDateTime()]],
                $options
            );
            return $result->getModifiedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            return false;
        }
    }
}
