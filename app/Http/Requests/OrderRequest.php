<?php

namespace App\Http\Requests;

use App\Enums\ProductTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
      $rule = [
         'product_type' => ['nullable'],
         'customer_name' => ['required', 'string'],
         'customer_phone' => ['required', 'string'],
         'customer_email' => ['nullable', 'email'],
         'payment_type' =>  ['required', 'string'],
         'payment_method' =>  ['required', 'string'],
         'payment_name' =>  ['required', 'string'],
         'payment_code' => ['nullable'],
         'order_items' => ['required', 'array'],
         'order_qty' => ['required', 'numeric'],
         'order_unique_code' => ['required', 'numeric'],
         'order_subtotal' => ['required', 'numeric'],
         'grand_total' => ['required', 'numeric'],
         'payment_fee' => ['required', 'numeric'],
         'service_fee' => ['required', 'numeric'],
         'voucher_discount' => ['required', 'numeric'],
      ];

      return $rule;
   }

   protected function prepareForValidation(): void
   {
      $this->merge([
         'product_type' => $this->product_type ?? ProductTypeEnum::Digital->value,
      ]);
   }
}
