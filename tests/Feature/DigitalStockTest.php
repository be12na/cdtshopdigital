<?php

namespace Tests\Feature;

use App\Enums\ProductTypeEnum;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_digital_unlimited_stock_does_not_decrement_on_order_update_stock(): void
    {
        $product = Product::create([
            'title' => 'Produk Digital Unlimited',
            'description' => 'desc',
            'stock' => 0,
            'price' => 10000,
            'sold' => 0,
            'status' => true,
            'category_id' => null,
            'product_type' => ProductTypeEnum::Digital->value,
            'is_unlimited_stock' => true,
        ]);

        $order = Order::create([
            'user_id' => null,
            'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com',
            'customer_whatsapp' => '081234567890',
            'order_qty' => 1,
            'order_subtotal' => 10000,
            'order_unique_code' => 0,
            'order_total' => 10000,
            'order_status' => Order::PENDING,
            'product_type' => ProductTypeEnum::Digital->value,
        ]);

        $order->items()->create([
            'name' => $product->title,
            'sku' => $product->sku,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
        ]);

        $order->update_stock();

        $product->refresh();
        $this->assertSame(0, (int) $product->stock);
    }

    public function test_digital_limited_stock_decrements_on_order_update_stock(): void
    {
        $product = Product::create([
            'title' => 'Produk Digital Terbatas',
            'description' => 'desc',
            'stock' => 5,
            'price' => 10000,
            'sold' => 0,
            'status' => true,
            'category_id' => null,
            'product_type' => ProductTypeEnum::Digital->value,
            'is_unlimited_stock' => false,
        ]);

        $order = Order::create([
            'user_id' => null,
            'customer_name' => 'Customer',
            'customer_email' => 'customer@example.com',
            'customer_whatsapp' => '081234567890',
            'order_qty' => 2,
            'order_subtotal' => 20000,
            'order_unique_code' => 0,
            'order_total' => 20000,
            'order_status' => Order::PENDING,
            'product_type' => ProductTypeEnum::Digital->value,
        ]);

        $order->items()->create([
            'name' => $product->title,
            'sku' => $product->sku,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => $product->price,
        ]);

        $order->update_stock();

        $product->refresh();
        $this->assertSame(3, (int) $product->stock);
    }

    public function test_admin_can_toggle_digital_product_stock_mode_via_api(): void
    {
        config(['installer.installed' => true]);

        $admin = User::factory()->create(['role_id' => 1]);
        Sanctum::actingAs($admin);

        $product = Product::create([
            'title' => 'Produk Digital',
            'description' => 'desc',
            'stock' => 0,
            'price' => 10000,
            'sold' => 0,
            'status' => true,
            'category_id' => null,
            'product_type' => ProductTypeEnum::Digital->value,
            'is_unlimited_stock' => true,
        ]);

        $this->postJson("/api/products/{$product->id}/toggle-unlimited-stock", [
            'is_unlimited_stock' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_unlimited_stock', false);

        $product->refresh();
        $this->assertFalse((bool) $product->is_unlimited_stock);
    }
}
