<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_pixel_capi', 255)->nullable()->after('product_type');
            $table->string('meta_token', 255)->nullable()->after('meta_pixel_capi');
            $table->string('meta_test_code', 255)->nullable()->after('meta_token');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['meta_pixel_capi', 'meta_token', 'meta_test_code']);
        });
    }
};

