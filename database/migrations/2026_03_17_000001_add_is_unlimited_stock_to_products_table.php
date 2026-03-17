<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'is_unlimited_stock')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_unlimited_stock')->default(true)->after('status');
        });

        DB::table('products')->update(['is_unlimited_stock' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'is_unlimited_stock')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_unlimited_stock');
        });
    }
};
