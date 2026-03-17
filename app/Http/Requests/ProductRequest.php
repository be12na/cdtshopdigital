<?php

namespace App\Http\Requests;

use App\Enums\ProductTypeEnum;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
   /**
    * Determine if the user is authorized to make this request.
    *
    * @return bool
    */
   public function authorize()
   {
      return true;
   }

   /**
    * Get the validation rules that apply to the request.
    *
    * @return array
    */
   public function rules()
   {
      $rules = [
         'title' => 'required|string|max:190',
         'price' => 'required',
         'stock' => 'required',
         'description' => 'required',
         'meta_pixel_capi' => ['nullable', 'alpha_num', 'max:255'],
         'meta_token' => ['nullable', 'alpha_num', 'max:255'],
         'meta_test_code' => ['nullable', 'alpha_num', 'max:255'],
         'aff_amount' => 'numeric',
         'assets' => ['required', 'array'],
         'is_unlimited_stock' => ['nullable', 'boolean'],
      ];

      $isDigitalType = in_array($this->product_type, [
         ProductTypeEnum::Digital->value,
         ProductTypeEnum::DigitalDownload->value,
         ProductTypeEnum::DigitalVideo->value,
      ], true);

      if ($isDigitalType) {
         $isUnlimitedStock = $this->has('is_unlimited_stock') ? $this->boolean('is_unlimited_stock') : true;
         $rules['stock'] = $isUnlimitedStock ? 'nullable' : 'required|integer|min:0';
      }

      if ($this->product_type == ProductTypeEnum::DigitalVideo->value) {
         $rules['digital_videos'] = ['required', 'array'];
      }
      if ($this->product_type == ProductTypeEnum::DigitalDownload->value) {
         $rules['digital_downloads'] = ['required', 'array'];
      }

      return $rules;
   }
   public function messages()
   {
      return [
         'title.unique' => 'Nama produk sudah digunakan',
         'meta_pixel_capi.alpha_num' => 'Meta Pixel CAPI hanya boleh berisi huruf dan angka.',
         'meta_pixel_capi.max' => 'Meta Pixel CAPI maksimal 255 karakter.',
         'meta_token.alpha_num' => 'Token Meta hanya boleh berisi huruf dan angka.',
         'meta_token.max' => 'Token Meta maksimal 255 karakter.',
         'meta_test_code.alpha_num' => 'KODE TEST hanya boleh berisi huruf dan angka.',
         'meta_test_code.max' => 'KODE TEST maksimal 255 karakter.',
      ];
   }

   protected function prepareForValidation(): void
   {
      $this->merge([
         'title' => strip_tags($this->title),
         'product_type' => $this->product_type ?? ProductTypeEnum::Digital->value,
         'meta_pixel_capi' => $this->meta_pixel_capi === '' ? null : $this->meta_pixel_capi,
         'meta_token' => $this->meta_token === '' ? null : $this->meta_token,
         'meta_test_code' => $this->meta_test_code === '' ? null : $this->meta_test_code,
      ]);
   }
}
