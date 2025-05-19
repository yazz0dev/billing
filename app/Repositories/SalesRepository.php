<?php
namespace App\Repositories;

use App\Models\Bill;

class SalesRepository
{
    public function getAllSales(array $options = [])
    {
        // Default sort option from original code
        $query = Bill::query();
        if (isset($options['sort'])) {
            foreach ($options['sort'] as $field => $direction) {
                $query->orderBy($field, $direction === -1 ? 'desc' : 'asc');
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }
        return $query->get()->toArray(); // Return as array to match old repo
    }
}