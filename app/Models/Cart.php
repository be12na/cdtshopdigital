<?php

namespace App\Models;

use App\Traits\HasSession;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
   use HasFactory, HasSession;

   protected $fillable = [
      'session_id',
      'name',
      'sku',
      'price',
      'quantity',
      'image_url',
      'product_url',
      'product_stock',
      'product_id',
      'note',
      'product_type',
      'affiliate_code'
   ];
   public function sumPrice()
   {
      return $this->price * $this->quantity;
   }
}
