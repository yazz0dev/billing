<?php // src/Admin/SalesRepository.php

namespace App\Admin;

use App\Core\Database;
use MongoDB\Collection;

class SalesRepository
{
    private Collection $billCollection;

    public function __construct()
    {
        // Assuming bills are stored in 'bills_new' as per your server.php
        $this->billCollection = Database::connect()->selectCollection('bills_new');
    }

    public function getAllSales(array $options = ['sort' => ['created_at' => -1]]): array
    {
        return $this->billCollection->find([], $options)->toArray();
    }

    // Add other sales-related query methods if needed (e.g., sales by date range, by product)
}
