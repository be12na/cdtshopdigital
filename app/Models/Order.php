<?php

namespace App\Models;

use Illuminate\Support\Str;
use App\Enums\ProductTypeEnum;
use App\Jobs\DispatchEventJob;
use Illuminate\Support\Carbon;
use App\Jobs\DispatchNotificationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
   use HasFactory;

   protected $guarded = [];

   public const PENDING = 'PENDING';
   public const TO_PROCESS = 'TO_PROCESS';
   public const COMPLETE = 'COMPLETE';
   public const CANCELED = 'CANCELED';

   public const PAYMENT_GATEWAY = 'PAYMENT_GATEWAY';
   public const PAYMEMT_DIRECT_TRANSFER = 'DIRECT_TRANSFER';
   public const PAYMEMT_SALDO_BALANCE = 'SALDO_BALANCE';

   public $appends = [
      'admin_status',
      'customer_status',
      'created',
      'billing_total',
      'can_review',
      'can_completed',
      'can_cancel_order',
      'can_confirm_payment',
      'can_delete_order'
   ];

   protected $with = ['transaction'];

   public function getBillingTotalAttribute()
   {
      return $this->order_total + $this->payment_fee;
   }

   public function getCanCancelOrderAttribute()
   {
      return in_array($this->order_status, [self::PENDING, self::TO_PROCESS]);
   }
   public function getCanDeleteOrderAttribute()
   {
      return in_array($this->order_status, [self::CANCELED]);
   }

   public function is_deposit_type()
   {
      return $this->product_type == ProductTypeEnum::Deposit->value;
   }
   public function is_digital_type()
   {
      return ProductTypeEnum::isDigital($this->product_type);
   }
   public function is_default_type()
   {
      return !$this->is_deposit_type() && $this->is_digital_type();
   }

   public function user()
   {
      return $this->belongsTo(User::class);
   }
   public function items()
   {
      return $this->hasMany(OrderItem::class);
   }
   public function item()
   {
      return $this->hasOne(OrderItem::class);
   }
   public function getCreatedReadableAttribute()
   {
      return $this->created_at->diffForHumans();
   }
   public function getCreatedAttribute()
   {
      return $this->created_at->format('d/m/Y ~ H:i');
   }
   public function getCanReviewAttribute()
   {
      if ($this->is_deposit_type()) {
         return false;
      }
      $userId = getCurrentSanctumUser('id');

      if (!$this->is_reviewed && $this->user_id == $userId && $this->order_status == self::COMPLETE) {
         return true;
      }

      return false;
   }

   public function transaction()
   {
      return $this->hasOne(Transaction::class);
   }
   public function getAdminStatusAttribute()
   {
      $reason = $this->cancellation_reason ? ': ' . $this->cancellation_reason : '';
      switch ($this->order_status) {
         case self::CANCELED:
            return [
               'label'  => 'Dibatalkan',
               'title'  => 'Pesanan Dibatalkan',
               'desc'   => 'Pesanan dibatalkan ' . $reason,
               'icon'   => 'production_quantity_limits'
            ];
            break;
         case self::TO_PROCESS:

            if ($this->transaction->status == 'UNPAID' && $this->transaction->payment_proof) {
               return [
                  'label'  => 'Bukti pembayaran dikirim',
                  'title'  => 'Menunggu Verifikasi Pembayaran',
                  'desc'   => 'Segera cek pembayaran dari pelanggan, jika sudah valid silahkan tekan konfirmasi pembayaran',
                  'icon'   => 'payments'
               ];
            }
            return [
               'label'  => 'Perlu Diproses',
               'title'  => 'Pesanan perlu diproses',
               'desc'   => 'Pesanan sedang dipersiapkan',
               'icon'   => 'av_timer'
            ];
            break;
         case self::COMPLETE:
            return [
               'label'  => 'Selesai',
               'title'  => 'Pesanan Selesai',
               'desc'   => 'Pesanan selesai',
               'icon'   => 'receipt_long'
            ];
            break;

         default:
            $exp = Carbon::parse($this->expired_at)->translatedFormat('l, d M Y H:i:s');
            $msg = 'Menuggu pembayaran dari pembeli, batas pembayaran ' . $exp;
            return [
               'label'  => 'Pending',
               'title'  => 'Menunggu Pembayaran',
               'desc'   => $msg,
               'icon'   => 'payments'
            ];
            break;
      }
   }
   public function getCustomerStatusAttribute()
   {
      $reason = $this->cancellation_reason ? ': ' . $this->cancellation_reason : '';
      switch ($this->order_status) {
         case self::CANCELED:
            return [
               'label'  => 'Dibatalkan',
               'title'  => 'Pesanan Dibatalkan',
               'desc'   => 'Pesanan dibatalkan ' . $reason,
               'icon'   => 'production_quantity_limits'
            ];
            break;
         case self::TO_PROCESS:
            if ($this->transaction->status == 'UNPAID' && $this->transaction->payment_proof) {
               return [
                  'label'  => 'Bukti pembayaran dikirim',
                  'title'  => 'Menunggu Verifikasi Pembayaran',
                  'desc'   => 'Pembayaran sedang di cek, setelah valid pesanan akan segera kami proses',
                  'icon'   => 'payments'
               ];
            }
            return [
               'label'  => 'Sedang Diproses',
               'title'  => 'Pesanan sedang diproses',
               'desc'   => 'Pesanan sedang dipersiapkan',
               'icon'   => 'av_timer'
            ];
            break;
         case self::COMPLETE:
            return [
               'label'  => 'Selesai',
               'title'  => 'Pesanan Selesai',
               'desc'   => 'Pesanan selesai, terima kasih telah berbelanja',
               'icon'   => 'receipt_long'
            ];
            break;

         default:
            $exp = Carbon::parse($this->expired_at)->translatedFormat('l, d M Y H:i');
            return [
               'label'  => 'Belum Bayar',
               'title'  => 'Menunggu Pembayaran',
               'desc'   => 'Lakukan pembayaran agar pesananmu dapat diproses, Pembayaran akan kadaluarsa pada ' . $exp,
               'icon'   => 'payments'
            ];
            break;
      }
   }
   protected static function boot()
   {
      parent::boot();

      static::creating(function ($model) {
         if (!$model->product_type) {
            $model->product_type = ProductTypeEnum::Digital->value;
         }
      });

      static::created(function ($model) {
         $prefix = 'INV';
         if ($model->product_type == ProductTypeEnum::Deposit->value) {
            $prefix = 'DPO';
         }
         $model->order_ref = $prefix . Carbon::now()->format('ym') . str_pad($model->id, 6, '0', STR_PAD_LEFT) . Str::upper(Str::random(3));
         $model->save();

         $model->pushHistory('Pesanan dibuat');

         self::clearCache();
      });

      static::updated(function ($model) {
         self::clearCache();
      });
   }

   public function pushHistory($desc)
   {
      $this->histories()->create([
         'description' => $desc,
         'date' => now()->toDateString(),
         'time' => now()->toTimeString(),
      ]);
   }

   public function dispatchEventMessage($event, $sync = false)
   {
      $order = $this;
      $order->fresh();
      DispatchNotificationJob::dispatch($event, $order);

      if ($sync) {
         if (!Cache::has('is_cron')) {
            DispatchEventJob::dispatch($event);
         }
      }
   }

   public function getCanConfirmPaymentAttribute()
   {
      return in_array($this->order_status, [self::PENDING, self::TO_PROCESS])
         && in_array($this->transaction->status, [Transaction::UNPAID]);
   }

   public function getCanCompletedAttribute()
   {
      return in_array($this->order_status, [self::TO_PROCESS]);
   }

   public function histories()
   {
      return $this->hasMany(OrderHistory::class)->orderByDesc('id');
   }

   public function vouchers()
   {
      return $this->belongsToMany(Voucher::class, 'order_vouchers', 'order_id', 'voucher_id');
   }

   public static function statusOptions()
   {

      return [
         ['label' => 'Semua', 'value' => 'ALL'],
         ['label' => 'Pending', 'value' => self::PENDING],
         ['label' => 'Perlu Diproses', 'value' => self::TO_PROCESS],
         ['label' => 'Selesai', 'value' => self::COMPLETE],
         ['label' => 'Batal', 'value' => self::CANCELED],
      ];
   }
   public static function eventOptions()
   {
      return [
         ['label' => 'PENDING', 'value' => self::PENDING],
         ['label' => 'COMPLETE', 'value' => self::COMPLETE],
         ['label' => 'CANCELED', 'value' => self::CANCELED],
      ];
   }

   public static function clearCache()
   {
      Cache::forget('latest_orders');
      Cache::forget('order_reports');
      Cache::forget('transaction_reports');
   }
   public function flush_cache()
   {
      Cache::forget('latest_orders');
      Cache::forget('order_reports');
      Cache::forget('transaction_reports');
   }
   public function update_stock($returned = false)
   {
      foreach ($this->items as $item) {

         $product = Product::where('sku', $item->sku)->first();
         $is_unlimited_stock = false;

         if ($product && $product->is_unlimited_stock) {
            $is_unlimited_stock = true;
         }

         if ($is_unlimited_stock == false) {

            if (!$product) {
               $product = ProductVarian::where('sku', $item->sku)->first();
            }
            if ($product) {
               if ($returned) {
                  $product->stock += $item->quantity;
               } else {
                  if ($product->stock > 0) {
                     $product->stock -= $item->quantity;
                  }
               }
               if ($product->stock < 0) {
                  $product->stock = 0;
               }
               $product->save();

               Cache::forget('product_' . $product->slug);
               Cache::forget('product_' . $product->id);
            }
         }
      }
   }
}
