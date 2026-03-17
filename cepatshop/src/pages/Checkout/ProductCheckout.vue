<template>
   <q-page class="q-pa-md page-sm checkout-page bg-grey-1">
      <q-card class="shadow checkout-card">
         <q-card-section class="row items-center justify-between q-gutter-sm">
            <div>
               <div class="text-h6 text-weight-bold">Form Pemesanan</div>
               <div class="text-grey-7" v-if="product">{{ product.title }}</div>
               <div class="text-grey-7" v-else>Silakan tunggu, sedang menyiapkan detail produk…</div>
            </div>
            <div class="row items-center q-gutter-xs">
               <div v-if="product" class="column items-center">
                  <favorite-button :product_id="product.id" lg outline />
                  <div class="text-caption text-grey-7">Simpan</div>
               </div>
               <q-btn
                  v-if="product"
                  unelevated
                  color="amber-6"
                  text-color="grey-10"
                  no-caps
                  icon="shopping_cart"
                  label="Masukkan Keranjang"
                  @click="addToCartNow"
               />
            </div>
         </q-card-section>

         <q-separator />

         <q-card-section class="q-pa-none">
            <div v-if="isProductLoading" class="q-pa-md">
               <q-skeleton type="text" />
               <q-skeleton type="text" />
               <q-skeleton type="text" />
               <q-skeleton type="QBtn" class="q-mt-md" />
            </div>

            <div v-else-if="productError" class="q-pa-md">
               <q-banner rounded class="bg-red-1 text-red-9">
                  {{ productError }}
               </q-banner>
            </div>

            <div v-else-if="product" class="q-pa-md">
               <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-5">
                     <q-card flat bordered class="bg-white">
                        <q-card-section class="row items-start q-gutter-sm">
                           <q-avatar rounded size="56px" color="grey-2" text-color="grey-8">
                              <q-img v-if="productImage" :src="productImage" ratio="1" />
                              <q-icon v-else name="inventory_2" />
                           </q-avatar>
                           <div class="col">
                              <div class="text-weight-bold">{{ product.title }}</div>
                              <div class="text-grey-7 text-caption">Ringkasan pesanan</div>
                           </div>
                        </q-card-section>
                        <q-separator />
                        <q-card-section class="q-gutter-y-xs">
                           <div class="row items-center justify-between">
                              <div class="text-grey-8">Harga</div>
                              <div class="text-weight-bold">{{ moneyIdr(product.price) }}</div>
                           </div>
                           <div class="row items-center justify-between">
                              <div class="text-grey-8">Jumlah</div>
                              <div class="row items-center q-gutter-xs">
                                 <q-btn
                                    round
                                    dense
                                    flat
                                    icon="remove"
                                    :disable="quantity <= 1 || isSubmitting"
                                    @click="quantity = Math.max(1, quantity - 1)"
                                 />
                                 <div class="text-weight-bold" style="min-width: 24px; text-align: center">
                                    {{ quantity }}
                                 </div>
                                 <q-btn
                                    round
                                    dense
                                    flat
                                    icon="add"
                                    :disable="quantity >= 100 || isSubmitting"
                                    @click="quantity = Math.min(100, quantity + 1)"
                                 />
                              </div>
                           </div>
                           <div class="row items-center justify-between">
                              <div class="text-grey-8">Cek ongkir & estimasi</div>
                              <div class="text-weight-bold">Rp0 • Instan</div>
                           </div>
                           <q-separator class="q-my-sm" />
                           <div class="row items-center justify-between">
                              <div class="text-grey-8">Metode bayar</div>
                              <div class="text-weight-bold text-right">
                                 {{ selectedPaymentLabel }}
                              </div>
                           </div>
                           <div v-if="selectedPaymentFee > 0" class="row items-center justify-between">
                              <div class="text-grey-8">Biaya layanan</div>
                              <div class="text-weight-bold">{{ moneyIdr(selectedPaymentFee) }}</div>
                           </div>
                           <div class="row items-center justify-between">
                              <div class="text-grey-8">Total</div>
                              <div class="text-weight-bold text-primary">{{ moneyIdr(grandTotal) }}</div>
                           </div>
                        </q-card-section>
                     </q-card>

                     <q-banner rounded class="bg-yellow-2 text-grey-9 q-mt-md">
                        <div class="text-weight-medium">Biar cepat diproses</div>
                        <div class="text-caption">
                           Pastikan email aktif dan nomor WhatsApp kamu benar. Kami kirim invoice & instruksi pembayaran
                           ke sana.
                        </div>
                     </q-banner>
                  </div>

                  <div class="col-12 col-md-7">
                     <q-card flat bordered class="bg-white">
                        <q-card-section>
                           <div class="text-subtitle1 text-weight-bold">Data Pembeli</div>
                           <div class="text-caption text-grey-7">Isi sekali, prosesnya lebih cepat.</div>
                        </q-card-section>
                        <q-separator />
                        <q-card-section>
                           <q-form ref="formRef" @submit.prevent="submit" class="q-gutter-y-md">
                              <q-input
                                 v-model="form.customer_name"
                                 outlined
                                 label="Nama Lengkap"
                                 :rules="rules.customer_name"
                                 lazy-rules="false"
                                 autocomplete="name"
                              />

                              <q-input
                                 v-model="form.customer_email"
                                 outlined
                                 label="Alamat Email"
                                 type="email"
                                 :rules="rules.customer_email"
                                 lazy-rules="false"
                                 autocomplete="email"
                              />

                              <q-input
                                 v-model="form.customer_whatsapp"
                                 outlined
                                 label="Nomor WhatsApp"
                                 type="tel"
                                 :rules="rules.customer_whatsapp"
                                 lazy-rules="false"
                                 hint="Contoh: +6281234567890"
                                 autocomplete="tel"
                              />

                              <q-card flat bordered class="bg-grey-1">
                                 <q-card-section class="row items-center justify-between q-gutter-sm">
                                    <div>
                                       <div class="text-subtitle1 text-weight-bold">Metode Pembayaran</div>
                                       <div class="text-caption text-grey-7">Pilih yang paling nyaman buat kamu.</div>
                                    </div>
                                    <q-btn
                                       unelevated
                                       color="green-6"
                                       no-caps
                                       icon="payments"
                                       label="Pilih"
                                       @click="paymentModal = true"
                                    />
                                 </q-card-section>
                                 <q-separator />
                                 <q-card-section v-if="payment">
                                    <div class="row items-start justify-between">
                                       <div>
                                          <div class="text-weight-bold">{{ selectedPaymentLabel }}</div>
                                          <div class="text-caption text-grey-7">
                                             {{ payment.payment_type === 'DIRECT_TRANSFER' ? 'Transfer bank (manual)' : 'Payment gateway' }}
                                          </div>
                                       </div>
                                       <q-badge
                                          v-if="payment.payment_type === 'DIRECT_TRANSFER'"
                                          color="green-1"
                                          text-color="green-9"
                                          class="q-ml-sm"
                                       >
                                          Transfer
                                       </q-badge>
                                       <q-badge
                                          v-else
                                          color="amber-2"
                                          text-color="grey-9"
                                          class="q-ml-sm"
                                       >
                                          Instan
                                       </q-badge>
                                    </div>
                                    <div v-if="selectedPaymentFee > 0" class="text-caption text-grey-8 q-mt-xs">
                                       Biaya layanan: {{ moneyIdr(selectedPaymentFee) }}
                                    </div>
                                 </q-card-section>
                                 <q-card-section v-else class="text-grey-7">
                                    Belum dipilih.
                                 </q-card-section>
                              </q-card>

                              <q-banner v-if="success" rounded class="bg-green-1 text-green-9">
                                 <div class="text-weight-medium">Pesanan berhasil dibuat.</div>
                                 <div v-if="success.invoice_ref" class="q-mt-xs">
                                    Invoice: <span class="text-weight-bold">{{ success.invoice_ref }}</span>
                                 </div>
                                 <template v-slot:action>
                                    <q-btn
                                       v-if="success.invoice_ref"
                                       flat
                                       no-caps
                                       color="green-9"
                                       label="Lihat Invoice"
                                       @click="goToInvoice(success.invoice_ref)"
                                    />
                                 </template>
                              </q-banner>

                              <div class="checkout-cta">
                                 <q-btn
                                    type="submit"
                                    unelevated
                                    color="green-7"
                                    no-caps
                                    class="full-width text-weight-bold"
                                    :loading="isSubmitting"
                                    :disable="isSubmitting || !product"
                                    label="Pesan Sekarang"
                                 />
                                 <div class="text-caption text-grey-7 q-mt-xs">
                                    Dengan menekan “Pesan Sekarang”, kamu setuju kami mengirim detail order lewat email & WhatsApp.
                                 </div>
                              </div>
                           </q-form>
                        </q-card-section>
                     </q-card>
                  </div>
               </div>
            </div>
         </q-card-section>
      </q-card>

      <q-dialog v-model="paymentModal" position="bottom">
         <q-card class="max-width-mobile q-pb-md" style="min-height: 320px">
            <div class="card-header bg-green-7 text-white row items-center justify-between q-pa-md">
               <div class="text-weight-bold">Pilih Metode Pembayaran</div>
               <q-btn flat icon="close" v-close-popup color="white" />
            </div>

            <q-card-section class="q-gutter-y-md">
               <div class="row q-gutter-sm">
                  <q-btn-toggle
                     v-model="paymentTab"
                     spread
                     no-caps
                     unelevated
                     toggle-color="green-7"
                     color="grey-3"
                     text-color="grey-9"
                     :options="paymentTabs"
                  />
               </div>

               <div v-if="paymentTab === 'transfer'">
                  <div class="text-caption text-grey-7 q-mb-sm">Transfer bank (paling familiar di Indonesia).</div>
                  <q-list bordered separator>
                     <q-item
                        v-for="(b, idx) in banks"
                        :key="idx"
                        clickable
                        @click="selectDirectTransfer(b)"
                        :class="{ 'bg-green-1': payment?.id === `bank:${b.id}` }"
                     >
                        <q-item-section side>
                           <q-icon
                              size="sm"
                              :name="payment?.id === `bank:${b.id}` ? 'radio_button_checked' : 'radio_button_unchecked'"
                              :color="payment?.id === `bank:${b.id}` ? 'green' : 'grey-8'"
                           />
                        </q-item-section>
                        <q-item-section>
                           <q-item-label class="text-weight-bold">{{ b.bank_name }}</q-item-label>
                           <q-item-label caption class="text-grey-7">{{ b.bank_detail }}</q-item-label>
                        </q-item-section>
                        <q-item-section side>
                           <q-item-label caption class="text-grey-8">{{ b.account_number }}</q-item-label>
                        </q-item-section>
                     </q-item>
                  </q-list>
                  <div v-if="banksError" class="text-red-8 text-caption q-mt-sm">
                     {{ banksError }}
                  </div>
               </div>

               <div v-else-if="paymentTab === 'ewallet'">
                  <div class="text-caption text-grey-7 q-mb-sm">Praktis untuk pengguna OVO / DANA / ShopeePay / QRIS.</div>
                  <q-list bordered separator>
                     <q-item
                        v-for="(p, idx) in gatewayEwallet"
                        :key="idx"
                        clickable
                        @click="selectGateway(p)"
                        :class="{ 'bg-green-1': payment?.id === p.id }"
                     >
                        <q-item-section side>
                           <q-icon
                              size="sm"
                              :name="payment?.id === p.id ? 'radio_button_checked' : 'radio_button_unchecked'"
                              :color="payment?.id === p.id ? 'green' : 'grey-8'"
                           />
                        </q-item-section>
                        <q-item-section>
                           <q-item-label class="text-weight-bold">{{ p.payment_name }}</q-item-label>
                           <q-item-label caption v-if="p.payment_fee" class="text-grey-8">
                              Biaya layanan {{ moneyIdr(p.payment_fee) }}
                           </q-item-label>
                        </q-item-section>
                        <q-item-section side>
                           <img v-if="p.icon_url" :src="p.icon_url" class="payment-icon" />
                           <q-icon v-else name="account_balance_wallet" color="grey-7" />
                        </q-item-section>
                     </q-item>
                  </q-list>
                  <div v-if="gatewayError" class="text-red-8 text-caption q-mt-sm">
                     {{ gatewayError }}
                  </div>
                  <div v-if="!isPgReady" class="text-grey-7 text-caption q-mt-sm">
                     Metode ini akan muncul otomatis kalau payment gateway di toko sudah aktif.
                  </div>
               </div>

               <div v-else>
                  <div class="text-caption text-grey-7 q-mb-sm">Bayar tunai di Alfamart / Indomaret (jika tersedia).</div>
                  <q-list bordered separator>
                     <q-item
                        v-for="(p, idx) in gatewayMinimarket"
                        :key="idx"
                        clickable
                        @click="selectGateway(p)"
                        :class="{ 'bg-green-1': payment?.id === p.id }"
                     >
                        <q-item-section side>
                           <q-icon
                              size="sm"
                              :name="payment?.id === p.id ? 'radio_button_checked' : 'radio_button_unchecked'"
                              :color="payment?.id === p.id ? 'green' : 'grey-8'"
                           />
                        </q-item-section>
                        <q-item-section>
                           <q-item-label class="text-weight-bold">{{ p.payment_name }}</q-item-label>
                           <q-item-label caption v-if="p.payment_fee" class="text-grey-8">
                              Biaya layanan {{ moneyIdr(p.payment_fee) }}
                           </q-item-label>
                        </q-item-section>
                        <q-item-section side>
                           <img v-if="p.icon_url" :src="p.icon_url" class="payment-icon" />
                           <q-icon v-else name="store" color="grey-7" />
                        </q-item-section>
                     </q-item>
                  </q-list>
                  <div v-if="gatewayError" class="text-red-8 text-caption q-mt-sm">
                     {{ gatewayError }}
                  </div>
                  <div v-if="!isPgReady" class="text-grey-7 text-caption q-mt-sm">
                     Metode ini akan muncul otomatis kalau payment gateway di toko sudah aktif.
                  </div>
               </div>
            </q-card-section>
         </q-card>
      </q-dialog>

      <q-inner-loading :showing="isSubmitting" color="green-7">
         <div class="text-center q-pa-md">
            <q-spinner size="42px" color="green-7" />
            <div class="text-weight-bold q-mt-sm">Sedang memproses pesanan…</div>
            <div class="text-caption text-grey-7">Mohon tunggu ya, jangan tutup halaman ini dulu.</div>
         </div>
      </q-inner-loading>
   </q-page>
</template>

<script>
import { Api } from "boot/axios";
import { moneyIdr } from "src/utils";
import FavoriteButton from "components/FavoriteButton.vue";

export default {
   name: "ProductCheckoutPage",
   components: { FavoriteButton },
   data() {
      return {
         isProductLoading: false,
         isSubmitting: false,
         product: null,
         productError: null,
         quantity: 1,
         success: null,
         paymentModal: false,
         paymentTab: "transfer",
         payment: null,
         banks: [],
         banksError: null,
         gatewayChanels: [],
         gatewayError: null,
         form: {
            customer_name: "",
            customer_email: "",
            customer_whatsapp: "",
         },
      };
   },
   computed: {
      config() {
         return this.$store.state.config;
      },
      isPgReady() {
         if (this.config && this.config.is_pg_ready) return true;
         return false;
      },
      productImage() {
         const src = this.product?.assets?.[0]?.src;
         if (!src) return null;
         return src;
      },
      total() {
         if (!this.product) return 0;
         return parseInt(this.product.price) * this.quantity;
      },
      paymentTabs() {
         return [
            { label: "Transfer Bank", value: "transfer" },
            { label: "E-Wallet", value: "ewallet" },
            { label: "Minimarket", value: "minimarket" },
         ];
      },
      selectedPaymentFee() {
         return Number(this.payment?.payment_fee || 0);
      },
      grandTotal() {
         return this.total + this.selectedPaymentFee;
      },
      selectedPaymentLabel() {
         if (!this.payment) return "Belum dipilih";
         if (this.payment.payment_type === "DIRECT_TRANSFER") {
            const code = this.payment.payment_code ? ` • ${this.payment.payment_code}` : "";
            return `${this.payment.payment_name}${code}`;
         }
         return this.payment.payment_name || "Payment gateway";
      },
      gatewayOptions() {
         const raw = Array.isArray(this.gatewayChanels) ? this.gatewayChanels : [];
         return raw
            .map((item) => {
               if (item?.code && item?.name) {
                  const fee = this.calculateTripayFee(item, this.total);
                  return {
                     id: `pg:${item.code}`,
                     payment_name: item.name,
                     payment_method: item.code,
                     payment_type: "PAYMENT_GATEWAY",
                     payment_fee: fee,
                     icon_url: item.icon_url,
                     raw: item,
                  };
               }
               if (item?.paymentMethod && item?.paymentName) {
                  return {
                     id: `pg:${item.paymentMethod}`,
                     payment_name: item.paymentName,
                     payment_method: item.paymentMethod,
                     payment_type: "PAYMENT_GATEWAY",
                     payment_fee: Number(item.totalFee || 0),
                     icon_url: item.paymentImage,
                     raw: item,
                  };
               }
               if (item?.id && item?.name) {
                  return {
                     id: `pg:${item.id}`,
                     payment_name: item.name,
                     payment_method: item.id,
                     payment_type: "PAYMENT_GATEWAY",
                     payment_fee: Number(item.totalFee || item.payment_fee || 0),
                     icon_url: item.icon_url,
                     raw: item,
                  };
               }
               return null;
            })
            .filter(Boolean);
      },
      gatewayEwallet() {
         return this.gatewayOptions.filter((p) => this.detectPaymentGroup(p) === "ewallet");
      },
      gatewayMinimarket() {
         return this.gatewayOptions.filter((p) => this.detectPaymentGroup(p) === "minimarket");
      },
      rules() {
         return {
            customer_name: [
               (v) => (v && String(v).trim().length > 0) || "Nama lengkapnya boleh diisi dulu ya.",
               (v) => String(v || "").trim().length <= 190 || "Nama terlalu panjang, coba diringkas ya.",
            ],
            customer_email: [
               (v) => (v && String(v).trim().length > 0) || "Emailnya boleh diisi dulu ya, biar kami kirim invoice.",
               (v) => /.+@.+\..+/.test(String(v || "").trim()) || "Emailnya belum sesuai format (contoh: nama@email.com).",
            ],
            customer_whatsapp: [
               (v) => (v && String(v).trim().length > 0) || "Nomor WhatsApp boleh diisi dulu ya.",
               (v) => /^\+62\d{9,13}$/.test(String(v || "").trim()) || "Gunakan format +62, contoh: +6281234567890.",
            ],
         };
      },
   },
   watch: {
      "form.customer_whatsapp": function (val) {
         const formatted = this.normalizeWhatsapp(val);
         if (formatted !== val) {
            this.form.customer_whatsapp = formatted;
         }
      },
      quantity: function () {
         if (this.isPgReady) {
            this.loadGatewayChanels();
         }
      },
   },
   methods: {
      moneyIdr,
      detectPaymentGroup(p) {
         const key = `${p.payment_method || ""} ${p.payment_name || ""}`.toUpperCase();
         if (/(ALFAMART|INDOMARET|ALFA|INDO|MINIMARKET)/.test(key)) return "minimarket";
         if (/(OVO|DANA|SHOPEE|SHOPEEPAY|GOPAY|LINKAJA|QRIS|EWALLET|E-WALLET)/.test(key)) return "ewallet";
         return "transfer";
      },
      calculateTripayFee(item, amount) {
         const fee = item?.fee_customer;
         if (!fee) return 0;
         const flat = Number(fee.flat || 0);
         const percent = Number(fee.percent || 0);
         const minimum = Number(item.minimum_fee || 0);
         let totalFee = flat;
         if (percent > 0) {
            totalFee += Math.ceil((Number(amount) * percent) / 100);
         }
         if (totalFee > 0 && minimum > totalFee) totalFee = minimum;
         return totalFee;
      },
      normalizeWhatsapp(val) {
         if (val === null || val === undefined) return "";
         let v = String(val).trim();
         v = v.replace(/\s+/g, "").replace(/-/g, "");

         if (v.startsWith("+")) {
            return v;
         }
         if (v.startsWith("0")) {
            return "+62" + v.slice(1);
         }
         if (v.startsWith("62")) {
            return "+" + v;
         }

         return v;
      },
      async loadProduct() {
         this.isProductLoading = true;
         this.productError = null;
         this.product = null;

         try {
            const slug = this.$route.params.productSlug;
            const res = await Api.get("product-item/" + slug);
            this.product = res.data.data;
            await this.loadBanks();
            if (this.isPgReady) {
               await this.loadGatewayChanels();
            }
         } catch (e) {
            this.productError = e?.response?.data?.message || "Produk tidak ditemukan";
         } finally {
            this.isProductLoading = false;
         }
      },
      async loadBanks() {
         this.banksError = null;
         try {
            const res = await Api.get("getBanks");
            this.banks = res?.data?.data || [];
            if (!this.payment && this.banks.length) {
               this.selectDirectTransfer(this.banks[0]);
            }
         } catch (e) {
            this.banksError = e?.response?.data?.message || "Gagal memuat daftar bank.";
         }
      },
      async loadGatewayChanels() {
         this.gatewayError = null;
         try {
            const res = await Api.get(`payment-chanels?amount=${this.total}`);
            this.gatewayChanels = res?.data?.data || [];
         } catch (e) {
            this.gatewayError = e?.response?.data?.message || "Gagal memuat metode pembayaran.";
         }
      },
      selectDirectTransfer(bank) {
         this.payment = {
            id: `bank:${bank.id}`,
            payment_type: "DIRECT_TRANSFER",
            payment_name: bank.bank_name,
            payment_method: bank.bank_detail,
            payment_code: bank.account_number,
            payment_fee: 0,
         };
         this.paymentModal = false;
      },
      selectGateway(p) {
         this.payment = {
            id: p.id,
            payment_type: "PAYMENT_GATEWAY",
            payment_name: p.payment_name,
            payment_method: p.payment_method,
            payment_code: null,
            payment_fee: Number(p.payment_fee || 0),
         };
         this.paymentModal = false;
      },
      goToInvoice(order_ref) {
         this.$router.push({ name: "UserInvoice", params: { order_ref }, query: { pay: true } });
      },
      addToCartNow() {
         if (!this.product || !this.product.sku) {
            this.$q.notify({ type: "negative", message: "Produk ini belum bisa dimasukkan ke keranjang." });
            return;
         }
         const payload = {
            sku: this.product.sku,
            name: this.product.title,
            price: parseInt(this.product.price),
            quantity: this.quantity,
            product_id: this.product.id,
         };
         this.$store.dispatch("cart/addToCart", payload);
         this.$q.notify({ type: "positive", message: "Berhasil dimasukkan ke keranjang." });
      },
      async submit() {
         this.success = null;

         const valid = await this.$refs.formRef.validate();
         if (!valid) return;
         if (!this.product) return;
         if (!this.payment) {
            this.$q.notify({ type: "negative", message: "Pilih metode pembayaran dulu ya." });
            this.paymentModal = true;
            return;
         }

         this.isSubmitting = true;

         try {
            const slug = this.$route.params.productSlug;
            const payload = {
               customer_name: this.form.customer_name,
               customer_email: this.form.customer_email,
               customer_whatsapp: this.form.customer_whatsapp,
               quantity: this.quantity,
               payment_type: this.payment.payment_type,
               payment_method: this.payment.payment_method,
               payment_name: this.payment.payment_name,
               payment_code: this.payment.payment_code,
               payment_fee: this.payment.payment_fee,
            };

            const res = await Api.post(`products/${slug}/checkout`, payload);
            const orderRef = res?.data?.data?.order_ref || null;

            this.success = { invoice_ref: orderRef };
         } catch (e) {
            const msg = e?.response?.data?.message || "Gagal mengirim order";
            this.$q.notify({ type: "negative", message: msg });
         } finally {
            this.isSubmitting = false;
         }
      },
   },
   created() {
      this.loadProduct();
   },
   meta() {
      const productTitle = this.product?.title ? ` - ${this.product.title}` : "";
      return {
         title: `Checkout${productTitle}`,
      };
   },
};
</script>

<style scoped>
.checkout-page {
   min-height: calc(100vh - 50px);
}
.checkout-card {
   border-radius: 14px;
}
.checkout-cta {
   position: sticky;
   bottom: 0;
   padding-top: 8px;
   background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 1) 30%);
}
.payment-icon {
   width: 34px;
   height: 34px;
   object-fit: contain;
}
</style>
