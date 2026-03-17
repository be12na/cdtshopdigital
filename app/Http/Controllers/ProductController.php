<?php

namespace App\Http\Controllers;

use App\Enums\ProductTypeEnum;
use App\Helpers\ApiResponse;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $service)
    {
    }

    public function index(Request $request)
    {
        try {
            $data = $this->service->index($request);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function productVarians($id)
    {
        try {

            $data = $this->service->productVarianByProductId($id);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function removeVarian($id)
    {
        try {

            $this->service->removeVarian($id);

            return ApiResponse::success();
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function getSubscriptionOptions()
    {
        try {
            $this->service->getSubscriptionOptions();

            return ApiResponse::success();
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function searchProducts($key)
    {
        try {

            $data = $this->service->search($key);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function show($id)
    {

        try {

            $data = $this->service->show($id);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function edit($id)
    {

        try {

            $data = $this->service->edit($id);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function store(ProductRequest $request)
    {

        try {

            $data = $this->service->store($request);

            return ApiResponse::success($data);
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function update(ProductRequest $request, $id)
    {

        try {

            $this->service->update($request, $id);

            return ApiResponse::success();
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->destroy($id);

            return APiResponse::success();
        } catch (Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function toggleUnlimitedStock(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            if (! ProductTypeEnum::isDigital($product->product_type)) {
                return ApiResponse::withStatusCode(422)->failed('Hanya produk digital yang bisa menggunakan stok unlimited');
            }

            $isUnlimitedStock = $request->has('is_unlimited_stock')
               ? $request->boolean('is_unlimited_stock')
               : ! $product->is_unlimited_stock;

            $product->is_unlimited_stock = $isUnlimitedStock;
            if ($isUnlimitedStock) {
                $product->stock = 0;
            }
            $product->save();
            Product::clearCache($product->id);

            return ApiResponse::success([
                'id' => $product->id,
                'is_unlimited_stock' => $product->is_unlimited_stock,
            ]);
        } catch (Exception $e) {
            return ApiResponse::failed($e);
        }
    }
}
