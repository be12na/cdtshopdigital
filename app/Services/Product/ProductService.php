<?php

namespace App\Services\Product;

use App\Enums\ProductTypeEnum;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPromo;
use App\Models\ProductVarian;
use App\Models\Review;
use App\Models\UploadTemp;
use App\Services\Media\MediaService;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        protected MediaService $mediaService
    ) {
    }

    public function index($request)
    {
        $data = Product::with(['assets'])
            ->select(
                'products.id',
                'products.title',
                'products.slug',
                'products.price',
                'products.stock',
                'products.product_type',
                'products.category_id',
                'products.aff_is_active',
                'products.aff_is_percentage',
                'products.aff_amount',
                DB::raw('MIN(product_varians.price) as minPrice'),
                DB::raw('MAX(product_varians.price) as maxPrice'),
                DB::raw('SUM(CASE WHEN product_varians.has_subvarian = 0 THEN 1 ELSE 0 END) as total_count'),
                DB::raw('SUM(CASE WHEN product_varians.has_subvarian = 0 THEN product_varians.stock ELSE 0 END) as total_stock'),
            )
            ->leftJoin('product_varians', 'products.id', 'product_varians.product_id')
            ->orderByDesc('products.id')
            ->groupBy('products.id')
            ->when($request->search, function ($q) use ($request) {
                $keyword = $request->search;
                $q->where('products.title', 'like', '%'.$keyword.'%');
            })
            ->when($request->is_aff, function ($q) {
                $q->where('products.aff_is_active', 1);
            })
            ->when($request->product_type && $request->product_type != 'ALL', function ($q) use ($request) {
                $q->where('products.product_type', 'like', $request->product_type.'%');
            })
            ->when($request->category && $request->category != 'ALL', function ($q) use ($request) {
                $categoryId = $request->category;
                if ($categoryId == 'Uncategory') {
                    $q->whereNull('products.category_id');
                } else {
                    $ids = [intval($categoryId)];
                    $cids = Cache::remember('categories-'.$categoryId, now()->addHours(3), function () use ($categoryId) {
                        return Category::where('category_id', $categoryId)->select('id')->pluck('id')->toArray();
                    });
                    $ids = array_merge($ids, $cids);
                    $q->whereIn('products.category_id', $ids);
                }
            })
           // ->toSql();
            ->paginate($request->per_page ?? 10);

        if ($data instanceof \Illuminate\Pagination\AbstractPaginator) {
            $data->withQueryString();
        }

        return $data;
    }

    public function show($id)
    {
        $product = Product::with('assets', 'category', 'varians.subvarian', 'links', 'digitalDownloads', 'digitalVideos')
            ->where('id', $id)
            ->first();
        return $product ? $product->makeVisible(['meta_pixel_capi', 'meta_token', 'meta_test_code']) : null;
    }

    public function edit($id)
    {
        $product = Product::with('assets', 'category', 'varians.subvarian')
            ->where('id', $id)
            ->first();
        return $product ? $product->makeVisible(['meta_pixel_capi', 'meta_token', 'meta_test_code']) : null;
    }

    public function store($request)
    {

        DB::beginTransaction();

        try {

            $product = new Product();
            $product->title = $request->title;
            $product->category_id = $request->category_id;
            $product->description = $request->description;
            $product->product_type = $request->product_type ?? ProductTypeEnum::Digital->value;
            $product->meta_pixel_capi = $request->meta_pixel_capi;
            $product->meta_token = $request->meta_token;
            $product->meta_test_code = $request->meta_test_code;

            $product->aff_is_active = $request->boolean('aff_is_active') ?? false;
            $product->aff_is_percentage = $request->boolean('aff_is_percentage') ?? false;
            $product->aff_amount = $request->aff_amount ?? 0;

            $is_simple_product = $request->boolean('simple_product');

            $product_type = $request->product_type ?? ProductTypeEnum::Digital->value;
            $defaultPrice = $request->price ?? 0;
            $defaultStock = $request->stock ?? 0;

            if (
                ProductTypeEnum::Digital->value == $product_type
                || ProductTypeEnum::DigitalDownload->value == $product_type
                || ProductTypeEnum::DigitalVideo->value == $product_type
            ) {
                $is_simple_product = true;
                $product->is_unlimited_stock = $request->has('is_unlimited_stock')
                   ? $request->boolean('is_unlimited_stock')
                   : true;
                $defaultStock = $product->is_unlimited_stock ? 0 : ($request->stock ?? 0);
            } else {
                $product->is_unlimited_stock = false;
            }

            if ($is_simple_product) {

                $product->price = $defaultPrice;
                $product->stock = $defaultStock;
            } else {

                $product->price = 0;
                $product->stock = 0;
            }

            $product->save();

            if ($request->digital_videos) {
                foreach ($request->digital_videos as $video) {
                    $product->digitalVideos()->create($video);
                }
            }

            if ($request->digital_downloads) {
                // Log::debug($request->digital_downloads);
                foreach ($request->digital_downloads as $file) {

                    if ($file['download_type'] == 'file') {

                        $temp = UploadTemp::where('filepath', $file['filepath'])->first();

                        if ($temp) {

                            $dd = $product->digitalDownloads()->create([
                                'disk' => 'local',
                                'filepath' => $temp->filepath,
                                'filename' => $file['filename'],
                                'caption' => $file['caption'] ?? null,
                                'download_type' => $file['download_type'],
                                'filesize' => $temp->filesize ?? 100,
                            ]);

                            $temp->delete();
                        }
                    }
                    if ($file['download_type'] == 'url') {

                        $product->digitalDownloads()->create([
                            'disk' => 'local',
                            'filepath' => $file['filepath'],
                            'filename' => $file['filename'],
                            'caption' => $file['caption'] ?? null,
                            'download_type' => $file['download_type'],
                        ]);
                    }
                }
            }

            $sort = 1;
            foreach ($request->assets as $asset) {
                $product->assets()->attach($asset['id'], ['sort' => $sort]);
                $sort++;
            }

            $product->fresh();

            if (! $is_simple_product && $request->varians) {

                foreach ($request->varians as $data) {

                    if ($request->boolean('has_subvarian') === true && count($data['subvarian']) > 0) {

                        $varian = $product->varians()->create([
                            'has_subvarian' => 1,
                            'label' => $data['label'],
                            'value' => $data['value'],
                        ]);

                        foreach ($data['subvarian'] as $item) {
                            $item['product_id'] = $product->id;
                            $item['price'] = str_replace('.', '', $item['price']);

                            $varian->subvarian()->create($item);
                        }
                    } else {

                        $data['price'] = str_replace('.', '', $data['price']);
                        $product->varians()->create($data);
                    }
                }
            }

            if ($request->marketplaces) {

                foreach ($request->marketplaces as $mp) {
                    if ($mp['product_url']) {
                        $product->links()->create([
                            'marketplace_id' => $mp['id'],
                            'product_url' => $mp['product_url'],
                        ]);
                    }
                }
            }

            DB::commit();

            if (! is_cron_running()) {
                Artisan::call('app:delete-file-temp');
            }

            Product::clearCache();

            return $product->load('assets', 'varians.subvarian');
        } catch (Exception $e) {

            DB::rollBack();

            throw $e;
        }
    }

    public function update($request, $id)
    {

        DB::beginTransaction();

        try {

            $product = Product::find($id);
            $is_simple_product = false;

            $product->title = $request->title;
            $product->description = $request->description;
            $product->category_id = $request->category_id;
            $product->meta_pixel_capi = $request->meta_pixel_capi;
            $product->meta_token = $request->meta_token;
            $product->meta_test_code = $request->meta_test_code;

            $product->aff_is_active = $request->boolean('aff_is_active') ?? false;
            $product->aff_is_percentage = $request->boolean('aff_is_percentage') ?? false;
            $product->aff_amount = $request->aff_amount ?? 0;

            $is_simple_product = $request->boolean('simple_product');

            $product_type = $request->product_type ?? $product->product_type ?? ProductTypeEnum::Digital->value;
            $product->product_type = $product_type;
            $defaultPrice = $request->price ?? 0;
            $defaultStock = $request->stock ?? 0;

            if (
                ProductTypeEnum::Digital->value == $product_type
                || ProductTypeEnum::DigitalDownload->value == $product_type
                || ProductTypeEnum::DigitalVideo->value == $product_type
            ) {
                $is_simple_product = true;
                $product->is_unlimited_stock = $request->has('is_unlimited_stock')
                   ? $request->boolean('is_unlimited_stock')
                   : ($product->is_unlimited_stock ?? true);
                $defaultStock = $product->is_unlimited_stock ? 0 : ($request->stock ?? 0);
            } else {
                $product->is_unlimited_stock = false;
            }

            if ($is_simple_product) {

                $product->price = $defaultPrice;
                $product->stock = $defaultStock;

                $product->varians()->delete();
            }

            $product->digitalVideos()->delete();

            if ($request->digital_videos) {
                foreach ($request->digital_videos as $video) {
                    $product->digitalVideos()->create($video);
                }
            }

            if ($request->digital_downloads) {

                foreach ($request->digital_downloads as $file) {

                    if ($file['download_type'] == 'file') {
                        if (isset($file['id'])) {
                            $product->digitalDownloads()->find($file['id'])->update([
                                'caption' => $file['caption'],
                                'filename' => $file['filename'],
                            ]);
                        } else {
                            $temp = UploadTemp::where('filepath', $file['filepath'])
                                ->first();

                            if ($temp) {

                                $product->digitalDownloads()->create([
                                    'disk' => 'local',
                                    'filepath' => $file['filepath'],
                                    'filename' => $file['filename'],
                                    'caption' => $file['caption'] ?? null,
                                    'download_type' => $file['download_type'],
                                ]);

                                $temp->delete();
                            }
                        }
                    }

                    if ($file['download_type'] == 'url') {

                        if (isset($file['id'])) {
                            $product->digitalDownloads()->find($file['id'])->update([
                                'caption' => $file['caption'],
                                'filename' => $file['filename'],
                            ]);
                        } else {

                            $product->digitalDownloads()->create([
                                'disk' => 'local',
                                'filepath' => $file['filepath'],
                                'filename' => $file['filename'],
                                'caption' => $file['caption'] ?? null,
                                'download_type' => $file['download_type'],
                            ]);
                        }
                    }
                }
            }

            $product->save();

            $product->assets()->sync([]);

            $sort = 1;
            foreach ($request->assets as $asset) {
                $product->assets()->attach($asset['id'], ['sort' => $sort]);
                $sort++;
            }

            if ($request->remove_varian) {
                $varianIds = json_decode($request->remove_varian);

                ProductVarian::whereIn('id', $varianIds)->delete();
            }

            if (! $is_simple_product && $request->varians) {

                $product->stock = 0;
                $product->price = 0;
                $product->save();

                foreach ($request->varians as $data) {

                    if ($request->boolean('has_subvarian') === true) {

                        if (isset($data['id'])) {

                            $varian = ProductVarian::find($data['id']);
                        } else {

                            $varian = new ProductVarian();
                        }

                        $varian->product_id = $product->id;
                        $varian->has_subvarian = 1;
                        $varian->label = $data['label'];
                        $varian->value = $data['value'];
                        $varian->save();

                        foreach ($data['subvarian'] as $item) {

                            $item['product_id'] = $product->id;
                            $item['price'] = str_replace('.', '', $item['price']);

                            if (isset($item['id'])) {

                                ProductVarian::find($item['id'])->update($item);
                            } else {
                                $varian->subvarian()->create($item);
                            }
                        }
                    } else {

                        $data['price'] = str_replace('.', '', $data['price']);

                        if (isset($data['id'])) {

                            ProductVarian::find($data['id'])->update($data);
                        } else {
                            $product->varians()->create($data);
                        }
                    }
                }
            }

            $product->links()->delete();

            if ($request->marketplaces) {

                foreach ($request->marketplaces as $mp) {
                    if ($mp['product_url']) {
                        $product->links()->create([
                            'marketplace_id' => $mp['id'],
                            'product_url' => $mp['product_url'],
                        ]);
                    }
                }
            }

            DB::commit();

            if (! is_cron_running()) {
                Artisan::call('app:delete-file-temp');
            }

            Product::clearCache($id);

            return true;
        } catch (Exception $e) {

            DB::rollBack();

            throw $e;
        }
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        DB::beginTransaction();

        try {

            ProductPromo::where('product_id', $product->id)->delete();
            Cart::where('product_id', $product->id)->delete();
            Review::where('product_id', $product->id)->delete();
            ProductVarian::where('product_id', $product->id)->delete();
            ProductVarian::where('product_id', $product->id)->delete();
            DB::table('product_asset')->where('product_id', $product->id)->delete();

            DB::table('licenses')->where('product_id', $product->id)->delete();

            $product->digitalVideos()->delete();
            $product->digitalDownloads()->delete();
            $product->delete();

            if (! is_cron_running()) {
                Artisan::call('app:auto-delete-files');
            }

            DB::commit();

            Product::clearCache($id);

            return true;
        } catch (Exception $e) {

            DB::rollBack();

            throw $e;
        }
    }

    public function productVarianByProductId($productId)
    {
        return ProductVarian::leftJoin('product_varians as parent', 'product_varians.varian_id', 'parent.id')
            ->select(
                'product_varians.price',
                'product_varians.stock',
                DB::raw("CONCAT_WS(' ',parent.label,parent.value,product_varians.label, product_varians.value) as product_name"),
            )
            ->where('product_varians.product_id', $productId)
            ->where('product_varians.has_subvarian', 0)
            ->orderBy('product_name')
            ->orderBy('product_varians.price')
            ->get();
    }

    public function removeVarian($id)
    {
        $varian = ProductVarian::find($id);
        Product::clearCache($varian->product_id);
        $varian->delete();

        return 1;
    }

    public function search($keyword, $limit = 20)
    {
        return Product::where('title', 'like', '%'.$keyword.'%')
            ->with(['minPrice', 'maxPrice', 'featuredImage', 'category', 'varianItems.parent'])
            ->paginate($limit);
    }

    public function getSubscriptionOptions()
    {
        return Product::SUBSCRIPTION_OPTIONS;
    }

    protected function clearCache()
    {
        Cache::forget('categories');
        Cache::forget('products');
        Cache::forget('initial_products');
        Cache::forget('product_promo');
    }
}
