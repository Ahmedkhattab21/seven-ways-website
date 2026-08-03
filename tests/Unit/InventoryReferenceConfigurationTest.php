<?php

namespace Tests\Unit;

use Tests\TestCase;

class InventoryReferenceConfigurationTest extends TestCase
{
    public function test_sales_product_return_is_an_allowed_inventory_reference_type(): void
    {
        $this->assertContains('sales_product_return', config('inventory.reservation_reference_types'));
    }
}
