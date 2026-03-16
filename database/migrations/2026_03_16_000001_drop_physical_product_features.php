<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('configs')) {
            $configColumns = [
                'cod_list',
                'is_cod_payment',
                'rajaongkir_type',
                'rajaongkir_apikey',
                'rajaongkir_couriers',
                'warehouse_id',
                'warehouse_address',
                'is_shipping_active',
                'is_order_pickup',
                'is_local_shipping',
                'is_cash_payment',
                'biteship_apikey',
                'courier_default',
                'biteship_couriers',
                'biteship_warehouse',
                'mapbox_access_token',
                'local_shipping_costs',
                'warehouse_coordinate',
                'local_shipping_label',
            ];

            foreach ($configColumns as $column) {
                if (Schema::hasColumn('configs', $column)) {
                    Schema::table('configs', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('orders')) {
            $orderColumns = [
                'order_weight',
                'shipping_type',
                'shipping_courier_id',
                'shipping_courier_code',
                'shipping_courier_name',
                'shipping_courier_service',
                'shipping_cost',
                'shipping_address',
                'shipping_at',
                'received_at',
                'shipping_coordinate',
                'shipping_discount',
            ];

            foreach ($orderColumns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    Schema::table('orders', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('carts') && Schema::hasColumn('carts', 'weight')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->dropColumn('weight');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'weight')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('weight');
            });
        }

        if (Schema::hasTable('product_varians') && Schema::hasColumn('product_varians', 'weight')) {
            Schema::table('product_varians', function (Blueprint $table) {
                $table->dropColumn('weight');
            });
        }

        if (Schema::hasTable('order_histories')) {
            $historyColumns = ['city_name', 'manifest_code'];
            foreach ($historyColumns as $column) {
                if (Schema::hasColumn('order_histories', $column)) {
                    Schema::table('order_histories', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'is_type_shipping')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('is_type_shipping');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_type')) {
            DB::table('products')
                ->where('product_type', 'Default')
                ->update(['product_type' => 'Digital']);
        }
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'product_type')) {
            DB::table('orders')
                ->where('product_type', 'Default')
                ->update(['product_type' => 'Digital']);
        }
        if (Schema::hasTable('carts') && Schema::hasColumn('carts', 'product_type')) {
            DB::table('carts')
                ->where('product_type', 'Default')
                ->update(['product_type' => 'Digital']);
        }

        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('subdistricts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
