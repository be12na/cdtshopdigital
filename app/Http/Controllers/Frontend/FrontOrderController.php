<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\MutasiSaldoStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Events\OrderPaid;
use App\Helpers\ApiResponse;
use App\Http\Requests\OrderRequest;
use App\Models\Affiliate;
use App\Models\Config;
use App\Models\Message;
use App\Models\MutasiSaldo;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVarian;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Message\MessageService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FrontOrderController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    public function getInvoice($invoice)
    {
        try {
            $data = Order::with(['items', 'transaction'])->where('order_ref', $invoice)->first();

            return ApiResponse::success($data);
        } catch (\Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function searchOrder(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string'],
        ]);

        try {

            $q = filter_var($request->key, FILTER_SANITIZE_SPECIAL_CHARS);

            $data = Order::with('transaction')
                ->where('customer_whatsapp', $q)
                ->orWhere('order_ref', $q)
                ->orderByDesc('updated_at')
                ->get();

            return ApiResponse::success($data);
        } catch (\Exception $e) {

            return ApiResponse::failed($e);
        }
    }

    public function getRandomOrder()
    {

        // FIXED Performance Issue
        $max = OrderItem::max('id');
        $latest = $max <= 60 ? 0 : $max - 60;
        $data = Cache::remember('order_items_random', now()->addMinutes(15), function () use ($latest) {

            return DB::table('order_items')
                ->select('order_items.id', 'order_items.name', 'order_items.created_at', 'orders.customer_name', 'assets.filepath', 'orders.order_total')
                ->join('orders', 'order_items.order_id', 'orders.id')
                ->join('products', 'order_items.product_id', 'products.id')
                ->join('product_asset', 'product_asset.product_id', 'products.id')
                ->join('assets', 'product_asset.asset_id', 'assets.id')
                ->where('order_items.id', '>=', $latest)
                ->where('orders.product_type', '<>', ProductTypeEnum::Deposit->value)
                ->inRandomOrder()
                ->groupBy('orders.id')
                ->get()->map(function ($item) {
                    $name = $item->name;

                    // $name =  $item->name . ' dengan harga total ' . number_format($item->order_total, 0, ',', '.');
                    return [
                        'id' => $item->id,
                        'name' => $name,
                        'customer_name' => $item->customer_name,
                        'created' => $item->created_at >= Carbon::now()->subDays(5) ? Carbon::parse($item->created_at)->diffForHumans() : 'Beberapa waktu lalu',
                        'image' => url($item->filepath),
                    ];
                });
        });

        return ApiResponse::success($data);
    }

    public function storeOrder(OrderRequest $request)
    {

        $user = auth('sanctum')->user();

        if ($request->voucher_id) {

            $v = Voucher::withCount('orders')->where('id', $request->voucher_id)->first();

            if ($v->usage_quota > 0 && $v->usage_quota <= $v->orders_count) {

                return ApiResponse::failed('Kuota pemakaian voucher habis, silahkan ganti voucher yang lain');
            }
            if ($v->end_date < now()) {
                return ApiResponse::failed('Masa aktif voucher telah kadaluarsa, silahkan ganti voucher yang lain');
            }
        }

        DB::beginTransaction();

        try {

            $config = Config::first();
            $order_expired_time = (int) $config->order_expired_time;

            $referal_code = $request->referal_code ?? null;

            $is_bank_transfer = $request->payment_type == PaymentTypeEnum::PAYMEMT_DIRECT_TRANSFER->value;
            $is_payment_gateway = $request->payment_type == PaymentTypeEnum::PAYMENT_GATEWAY->value;
            $is_saldo_balance = $request->payment_type == PaymentTypeEnum::PAYMEMT_SALDO_BALANCE->value;

            $payment_fee = $request->payment_fee ? intval($request->payment_fee) : 0;
            $service_fee = $request->service_fee ?? 0;

            $grand_total = $request->grand_total;

            $is_free_product = $grand_total == 0;

            $product_type = $request->product_type;

            $event = null;

            $expired_at = Carbon::now()->addHours($order_expired_time);

            $order_status = Order::PENDING;

            $kode_unik = intval($request->order_unique_code) ?? 0;

            $this->validateStockOrFail($request->order_items);

            $order = Order::create([
                'user_id' => $user ? $user->id : null,
                'customer_name' => $request->customer_name,
                'customer_whatsapp' => $request->customer_phone,
                'customer_email' => $request->customer_email ?? null,
                'order_qty' => $request->order_qty,
                'order_unique_code' => $kode_unik,
                'order_subtotal' => $request->order_subtotal,
                'order_total' => $request->grand_total,
                'order_status' => $order_status,
                'payment_fee' => $payment_fee,
                'service_fee' => $request->service_fee ?? 0,
                'voucher_discount' => $request->voucher_discount ?? 0,
                'expired_at' => $expired_at,
                'product_type' => $product_type,
                'note' => $request->customer_note ?? null,
            ]);

            foreach ($request->order_items as $item) {

                if (intval($item['price']) > 0 && $user) {
                    $affiliate = null;

                    if ($referal_code) {
                        $affiliate = Affiliate::where('user_id', '!=', $user->id)
                            ->where(function ($q) use ($referal_code) {
                                $q->where('code', $referal_code)->orWhere('coupon_code', $referal_code);
                            })
                            ->first();
                    } elseif (isset($item['affiliate_code']) && $item['affiliate_code']) {
                        $affiliate = Affiliate::where('code', $item['affiliate_code'])
                            ->where('user_id', '!=', $user->id)
                            ->first();
                    }

                    if ($affiliate && $affiliate->status == Affiliate::Active) {
                        $item['affiliate_id'] = $affiliate->user_id;
                    }
                }

                $order->items()->create($item);
            }

            if ($is_payment_gateway) {
                $email = $order->customer_email;
                if (! $email) {
                    $admin = User::whereNotNull('role_id')->first();
                    $email = $admin->email;
                }

                $items = [];

                foreach ($request->order_items as $row) {
                    $item = [
                        'sku' => $row['sku'],
                        'name' => $row['name'],
                        'price' => $row['price'],
                        'quantity' => $row['quantity'],
                    ];
                    $items[] = $item;
                }

                if ($kode_unik > 0) {
                    $items[] = [
                        'sku' => 'kdu',
                        'name' => 'kode Unik',
                        'price' => $kode_unik,
                        'quantity' => 1,
                    ];
                }

                $payload = [
                    'method' => $request->payment_method,
                    'merchant_ref' => $order->order_ref,
                    'amount' => $order->order_total,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $email,
                    'customer_phone' => $order->customer_whatsapp,
                    'order_items' => $items,
                    'order_expired_time' => $order_expired_time,
                ];

                $result = $this->paymentService->createTransaction($payload);

                // Log::debug('response payment service', $result);

                $transaction = new Transaction();

                $paymentName = $request->payment_name ?? $config->payment_default;

                if (isset($result['payment_channel'])) {
                    $paymentName = $result['payment_channel'];
                }

                $paymentMethod = $request->payment_method ?? null;

                if (! $paymentMethod && isset($result['payment_method'])) {
                    $paymentMethod = $result['payment_method'];
                }

                $expired_date = $result['expired_date'] ?? $expired_at;

                $transaction->order_id = $order->id;
                $transaction->payment_type = $request->payment_type;
                $transaction->payment_name = $paymentName;
                $transaction->payment_method = $paymentMethod;

                $transaction->snap_token = $result['snap_token'] ?? null;
                $transaction->qr_url = $result['qr_url'] ?? null;
                $transaction->pay_url = $result['pay_url'] ?? null;
                $transaction->checkout_url = $result['checkout_url'] ?? null;
                $transaction->payment_code = $result['pay_code'] ?? null;
                $transaction->qr_string = $result['qr_string'] ?? null;

                $transaction->payment_ref = $result['reference'] ?? null;
                $transaction->expired_time = Carbon::parse($expired_date)->timestamp;

                $transaction->amount = $result['amount'];
                $transaction->total_fee = $result['total_fee'] ?? 0;
                $transaction->fee_merchant = $result['fee_merchant'] ?? 0;
                $transaction->fee_customer = $result['fee_customer'] ?? $payment_fee;
                $transaction->instructions = isset($result['instructions']) ? json_encode($result['instructions']) : null;

                $transaction->save();

                $order->fresh();

                $order->payment_fee = $result['fee_customer'] ?? 0;
                $order->expired_at = $expired_date;
                $order->save();
            } else {
                $transaction = new Transaction();

                $transaction->order_id = $order->id;
                $transaction->payment_type = $request->payment_type ?? null;
                $transaction->payment_method = $request->payment_method ?? null;
                $transaction->payment_code = $request->payment_code ?? null;
                $transaction->payment_name = $request->payment_name ?? null;
                $transaction->amount = $order->order_total;

                $transaction->payment_ref = 'DTR'.Carbon::now()->format('ymd').rand(10, 99).Str::upper(Str::random(5));
                $transaction->expired_time = Carbon::now()->addHours($order_expired_time)->timestamp;

                $transaction->save();
            }
            if ($request->voucher_id) {
                $order->vouchers()->attach($request->voucher_id);
            }

            if ($is_saldo_balance) {
                MutasiSaldo::create([
                    'amount' => $order->billing_total,
                    'category' => MutasiSaldo::CATEGORY_DEFAULT,
                    'type' => MutasiSaldo::TYPE_OUT,
                    'user_id' => $order->user_id,
                    'status' => MutasiSaldoStatusEnum::Success,
                    'description' => 'Transaksi #'.$order->order_ref,
                ]);
                OrderPaid::dispatch($order);
            } elseif ($is_free_product) {
                OrderPaid::dispatch($order);
            } else {
                $event = NotificationTemplate::ORDER_CREATED;
                $order->dispatchEventMessage($event);
            }

            if (! $order->is_deposit_type()) {
                $order->update_stock();
            }

            DB::commit();

            $order->flush_cache();

            return ApiResponse::withEvent($event)->success($order);

            Cache::forget('latest_orders');
            Cache::forget('order_reports');

            $data = $order->load('items', 'transaction');

            return ApiResponse::withEvent($event)->success($data);
        } catch (\Exception $e) {

            DB::rollBack();

            return ApiResponse::failed($e);
        }
    }

    public function productCheckout(Request $request, string $slug)
    {
        $request->validate([
            'customer_name' => ['required', 'string', 'max:190'],
            'customer_email' => ['required', 'email', 'max:190'],
            'customer_whatsapp' => ['required', 'regex:/^\\+62\\d{9,13}$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'payment_type' => ['required', 'in:'.PaymentTypeEnum::PAYMEMT_DIRECT_TRANSFER->value.','.PaymentTypeEnum::PAYMENT_GATEWAY->value],
            'payment_method' => ['required', 'string', 'max:80'],
            'payment_name' => ['required', 'string', 'max:80'],
            'payment_code' => ['nullable', 'string', 'max:100'],
            'payment_fee' => ['nullable', 'integer', 'min:0', 'max:5000000'],
        ]);

        DB::beginTransaction();

        try {
            $config = Config::first();
            $orderExpiredTime = (int) ($config?->order_expired_time ?? 24);

            $product = Product::where('slug', $slug)->firstOrFail();
            if (! $product->status) {
                throw ValidationException::withMessages([
                    'product' => ['Produk tidak tersedia.'],
                ]);
            }

            $qty = (int) ($request->quantity ?? 1);

            if (! $product->sku) {
                throw ValidationException::withMessages([
                    'product' => ['Checkout sederhana belum mendukung produk tanpa SKU.'],
                ]);
            }

            $orderItems = [
                [
                    'sku' => $product->sku,
                    'name' => $product->title,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'price' => (int) $product->price,
                ],
            ];

            $this->validateStockOrFail($orderItems);

            $expiredAt = Carbon::now()->addHours($orderExpiredTime);
            $subtotal = (int) $product->price * $qty;
            $paymentFee = (int) ($request->payment_fee ?? 0);
            $orderTotal = $subtotal + $paymentFee;
            $isPaymentGateway = $request->payment_type === PaymentTypeEnum::PAYMENT_GATEWAY->value;

            $order = Order::create([
                'user_id' => getCurrentSanctumUser('id'),
                'customer_name' => $request->customer_name,
                'customer_whatsapp' => $request->customer_whatsapp,
                'customer_email' => $request->customer_email,
                'order_qty' => $qty,
                'order_unique_code' => 0,
                'order_subtotal' => $subtotal,
                'order_total' => $orderTotal,
                'order_status' => Order::PENDING,
                'payment_fee' => $paymentFee,
                'service_fee' => 0,
                'voucher_discount' => 0,
                'expired_at' => $expiredAt,
                'product_type' => $product->product_type,
                'note' => null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            $transaction = new Transaction();
            $transaction->order_id = $order->id;
            $transaction->payment_type = $request->payment_type;
            $transaction->amount = $order->order_total;
            $transaction->payment_ref = 'DTR'.Carbon::now()->format('ymd').rand(10, 99).Str::upper(Str::random(5));
            $transaction->expired_time = $expiredAt->timestamp;
            $transaction->status = Transaction::UNPAID;

            if ($isPaymentGateway) {
                $payload = [
                    'method' => $request->payment_method,
                    'merchant_ref' => $order->order_ref,
                    'amount' => $order->order_total,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $order->customer_whatsapp,
                    'order_items' => [
                        [
                            'sku' => $product->sku,
                            'name' => $product->title,
                            'price' => (int) $product->price,
                            'quantity' => $qty,
                        ],
                    ],
                    'order_expired_time' => $orderExpiredTime,
                ];

                $result = $this->paymentService->createTransaction($payload);

                $paymentName = $request->payment_name ?? ($config?->payment_default ?? 'Payment Gateway');
                if (isset($result['payment_channel'])) {
                    $paymentName = $result['payment_channel'];
                }

                $paymentMethod = $request->payment_method;
                if (isset($result['payment_method']) && $result['payment_method']) {
                    $paymentMethod = $result['payment_method'];
                }

                $expiredDate = $result['expired_date'] ?? $expiredAt;

                $transaction->payment_name = $paymentName;
                $transaction->payment_method = $paymentMethod;
                $transaction->snap_token = $result['snap_token'] ?? null;
                $transaction->qr_url = $result['qr_url'] ?? null;
                $transaction->pay_url = $result['pay_url'] ?? null;
                $transaction->checkout_url = $result['checkout_url'] ?? null;
                $transaction->payment_code = $result['pay_code'] ?? null;
                $transaction->qr_string = $result['qr_string'] ?? null;
                $transaction->payment_ref = $result['reference'] ?? $transaction->payment_ref;
                $transaction->expired_time = Carbon::parse($expiredDate)->timestamp;
                $transaction->amount = $result['amount'] ?? $transaction->amount;
                $transaction->total_fee = $result['total_fee'] ?? 0;
                $transaction->fee_merchant = $result['fee_merchant'] ?? 0;
                $transaction->fee_customer = $result['fee_customer'] ?? $paymentFee;
                $transaction->instructions = isset($result['instructions']) ? json_encode($result['instructions']) : null;

                $transaction->save();

                $order->payment_fee = $result['fee_customer'] ?? $paymentFee;
                $order->expired_at = $expiredDate;
                $order->save();
            } else {
                $transaction->payment_method = $request->payment_method;
                $transaction->payment_name = $request->payment_name;
                $transaction->payment_code = $request->payment_code;
                $transaction->save();
            }

            if (! $order->is_deposit_type()) {
                $order->update_stock();
            }

            DB::commit();

            $order->flush_cache();

            $price = number_format((int) $product->price, 0, ',', '.');
            $total = number_format((int) $order->order_total, 0, ',', '.');
            $invoiceLink = route('invoice', $order->order_ref);
            $paymentTitle = $transaction->payment_name ?: $request->payment_name;
            $paymentInfo = $paymentTitle ? "Metode Bayar: {$paymentTitle}\n" : '';
            if ($transaction->pay_url) {
                $paymentInfo .= "Link Pembayaran:\n{$transaction->pay_url}\n";
            }
            if ($transaction->checkout_url) {
                $paymentInfo .= "Checkout URL:\n{$transaction->checkout_url}\n";
            }
            if ($transaction->payment_code) {
                $paymentInfo .= "Kode Pembayaran: {$transaction->payment_code}\n";
            }

            $adminBody = "Pesanan baru diterima\n\n".
                "Invoice: {$order->order_ref}\n".
                "Nama: {$order->customer_name}\n".
                "Email: {$order->customer_email}\n".
                "WhatsApp: {$order->customer_whatsapp}\n\n".
                "Produk:\n".
                "{$qty}x {$product->title}\n".
                "Harga: Rp{$price}\n".
                "Total: Rp{$total}\n".
                $paymentInfo."\n".
                "Invoice Link:\n{$invoiceLink}";

            $customerBody = "Halo kak {$order->customer_name},\n\n".
                "Order kamu berhasil dibuat.\n".
                "Invoice: {$order->order_ref}\n\n".
                "Produk:\n".
                "{$qty}x {$product->title}\n".
                "Harga: Rp{$price}\n".
                "Total: Rp{$total}\n".
                $paymentInfo."\n".
                "Invoice Link:\n{$invoiceLink}";

            $shop = Store::first();
            $messages = [];

            if ($shop?->email) {
                $messages[] = Message::create([
                    'user_id' => null,
                    'via' => Message::VIA_EMAIL,
                    'event' => 'product_checkout',
                    'recipient' => $shop->email,
                    'subject' => "Pesanan Baru #{$order->order_ref}",
                    'body' => $adminBody,
                    'status' => Message::Pending,
                ]);
            }

            if ($shop?->phone) {
                $messages[] = Message::create([
                    'user_id' => null,
                    'via' => Message::VIA_WHATSAPP,
                    'event' => 'product_checkout',
                    'recipient' => $shop->phone,
                    'subject' => "Pesanan Baru #{$order->order_ref}",
                    'body' => $adminBody,
                    'status' => Message::Pending,
                ]);
            }

            $messages[] = Message::create([
                'user_id' => $order->user_id,
                'via' => Message::VIA_EMAIL,
                'event' => 'product_checkout',
                'recipient' => $order->customer_email,
                'subject' => "Konfirmasi Order #{$order->order_ref}",
                'body' => $customerBody,
                'status' => Message::Pending,
            ]);

            $messages[] = Message::create([
                'user_id' => $order->user_id,
                'via' => Message::VIA_WHATSAPP,
                'event' => 'product_checkout',
                'recipient' => $order->customer_whatsapp,
                'subject' => "Konfirmasi Order #{$order->order_ref}",
                'body' => $customerBody,
                'status' => Message::Pending,
            ]);

            $messageService = new MessageService();
            $sendSummary = [];

            foreach ($messages as $message) {
                try {
                    $messageService->sendMessage($message);
                    $message->pushComplete();
                    $sendSummary[] = [
                        'id' => $message->id,
                        'via' => $message->via,
                        'recipient' => $message->recipient,
                        'status' => Message::Sent,
                    ];
                } catch (\Throwable $th) {
                    $message->pushFailed($th->getMessage());
                    $sendSummary[] = [
                        'id' => $message->id,
                        'via' => $message->via,
                        'recipient' => $message->recipient,
                        'status' => Message::Failed,
                    ];
                }
            }

            $data = $order->load('items', 'transaction');

            return ApiResponse::success([
                'order_ref' => $order->order_ref,
                'order' => $data,
                'messages' => $sendSummary,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::failed($e);
        }
    }

    private function validateStockOrFail(array $orderItems): void
    {
        foreach ($orderItems as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $sku = $item['sku'] ?? null;
            $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;

            if (! $productId || ! $sku || $qty < 1) {
                throw ValidationException::withMessages([
                    'order_items' => ['Data item pesanan tidak valid.'],
                ]);
            }

            $product = Product::where('id', $productId)->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages([
                    'order_items' => ['Produk tidak ditemukan.'],
                ]);
            }

            if ($product->product_type === ProductTypeEnum::Deposit->value) {
                continue;
            }

            if ($product->is_unlimited_stock) {
                continue;
            }

            if ($product->sku && $product->sku === $sku) {
                if ((int) $product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => ["Stok produk {$product->title} tidak mencukupi."],
                    ]);
                }

                continue;
            }

            $varian = ProductVarian::where('product_id', $product->id)
                ->where('sku', $sku)
                ->lockForUpdate()
                ->first();

            if (! $varian) {
                throw ValidationException::withMessages([
                    'order_items' => ["Varian produk {$product->title} tidak ditemukan."],
                ]);
            }

            if ((int) $varian->stock < $qty) {
                throw ValidationException::withMessages([
                    'stock' => ["Stok varian produk {$product->title} tidak mencukupi."],
                ]);
            }
        }
    }
}
