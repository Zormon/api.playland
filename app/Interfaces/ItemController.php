<?php

namespace App\Interfaces;

/**
 * Interface for controllers that handle items (CRUD operations)
 */
interface ItemController {
    /**
     * Get all items
     * 
     * @return void
     */
    public function all();
    
    /**
     * Get a specific item by ID
     * 
     * @param int $id ID of the item
     * @return void
     */
    public function get(int $id);
    
    /**
     * Create a new item
     * 
     * @return void
     */
    public function create();
    
    /**
     * Update an existing item with put request
     * 
     * @param int $id ID of the item
     * @return void
     */
    public function put(int $id);

    /**
     * Delete an item
     * 
     * @param int $id ID of the item
     * @return void
     */
    public function delete(int $id);
}
