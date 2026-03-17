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
         'title.unique' => 'Nama produk sudah digunakan'
      ];
   }

   protected function prepareForValidation(): void
   {
      $this->merge([
         'title' => strip_tags($this->title),
         'product_type' => $this->product_type ?? ProductTypeEnum::Digital->value,
      ]);
   }
}
