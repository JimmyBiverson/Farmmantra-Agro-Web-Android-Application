<?php

use App\Support\DefaultTerms;
use App\Support\UgandaDistricts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client requirements update (October 2026 handwritten notes).
 *
 * Purely additive: new tables and nullable/defaulted columns only. Existing
 * data is preserved; the down() method drops only what this migration adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Business regions and Uganda districts ────────────────────
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Official sub-region (Central / Eastern / Northern / Western)
            $table->string('official_region')->nullable();
            // Business region the district is assigned to (admin-managed)
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('franchises', function (Blueprint $table) {
            if (! Schema::hasColumn('franchises', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('region')->constrained('regions')->nullOnDelete();
            }
            if (! Schema::hasColumn('franchises', 'district_id')) {
                $table->foreignId('district_id')->nullable()->after('region_id')->constrained('districts')->nullOnDelete();
            }
        });

        // ── Customers: type (retailer/farmer), district, creator ─────
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_type')) {
                $table->string('customer_type', 20)->default('farmer')->after('name');
            }
            if (! Schema::hasColumn('customers', 'district_id')) {
                $table->foreignId('district_id')->nullable()->after('city')->constrained('districts')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            }
        });

        // ── Products: three-tier pricing ─────────────────────────────
        // standard_price = Franchise (base) price, selling_price = Retail price,
        // farmer_price  = Farmer price. All set by the administrator only.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'farmer_price')) {
                $table->decimal('farmer_price', 15, 2)->nullable()->after('selling_price');
            }
        });

        // ── Sales: price tier + cost for profit reporting ────────────
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'price_tier')) {
                $table->string('price_tier', 20)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('sales', 'total_cost')) {
                $table->decimal('total_cost', 15, 2)->default(0)->after('final_amount');
            }
        });
        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->default(0)->after('unit_price');
            }
        });

        // ── Orders: payment terms, dispatch details, signatures ──────
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_terms')) {
                $table->string('payment_terms', 10)->default('credit')->after('delivery_status');
            }
            if (! Schema::hasColumn('orders', 'amount_paid')) {
                $table->decimal('amount_paid', 15, 2)->default(0)->after('tax_amount');
            }
            if (! Schema::hasColumn('orders', 'dispatched_by')) {
                $table->foreignId('dispatched_by')->nullable()->after('served_at')->constrained('users')->nullOnDelete();
                $table->timestamp('dispatched_at')->nullable()->after('dispatched_by');
                $table->string('dispatch_means', 30)->nullable()->after('dispatched_at');
                $table->string('dispatch_vehicle')->nullable()->after('dispatch_means');
                $table->text('dispatch_remarks')->nullable()->after('dispatch_vehicle');
            }
            if (! Schema::hasColumn('orders', 'approval_signature')) {
                $table->string('approval_signature', 40)->nullable()->after('approved_at');
                $table->string('dispatch_signature', 40)->nullable()->after('dispatch_remarks');
                $table->string('delivery_signature', 40)->nullable()->after('completed_at');
                $table->foreignId('delivery_confirmed_by')->nullable()->after('delivery_signature')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('stock_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_receipts', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('received_at');
                $table->string('signature_number', 40)->nullable()->after('terms_accepted_at');
            }
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_submissions', 'acceptance_signature')) {
                $table->string('acceptance_signature', 40)->nullable()->after('accepted_at');
            }
        });

        // ── Users: terms & conditions acceptance ─────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('last_login_at');
                $table->unsignedInteger('terms_version')->nullable()->after('terms_accepted_at');
            }
        });

        // ── Digital signatures (who exactly confirmed what) ──────────
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signature_number', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name');
            $table->string('user_role')->nullable();
            $table->string('action', 50);
            $table->string('signable_type');
            $table->unsignedBigInteger('signable_id');
            $table->string('payload_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->index(['signable_type', 'signable_id']);
        });

        // ── Franchise account ledger (statement) ─────────────────────
        // Columns follow the client's statement sketch: purchases are posted
        // in "credit"; payments, credit notes and sales returns in "debit".
        // Balance owed = SUM(credit) - SUM(debit).
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening_balance|purchase|payment|credit_note|sales_return|adjustment
            $table->string('reference_number')->nullable();
            $table->nullableMorphs('reference');
            $table->string('description');
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('debit', 15, 2)->default(0);
            $table->date('transaction_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['franchise_id', 'transaction_date']);
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('note_number')->unique();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // credit_note | sales_return
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->json('items')->nullable();
            $table->text('reason');
            $table->string('signature_number', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Bulk SMS log ─────────────────────────────────────────────
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience');
            $table->text('message');
            $table->json('recipients');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('driver', 30);
            $table->string('status', 20)->default('queued'); // sent|failed|logged
            $table->text('provider_response')->nullable();
            $table->timestamps();
        });

        $this->seedReferenceData();
    }

    private function seedReferenceData(): void
    {
        $now = now();

        // Warehouse role (new user type that accepts and dispatches orders).
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('name', 'Warehouse')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Warehouse',
                'description' => 'Farmmantra warehouse — accepts and dispatches franchise orders',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default business regions = Uganda's four official regions. Admin can
        // add finer regions (e.g. "Gulu Region") and reassign districts.
        $regionIds = [];
        foreach (['Central', 'Eastern', 'Northern', 'Western'] as $name) {
            $existing = DB::table('regions')->where('name', $name)->value('id');
            $regionIds[$name] = $existing ?: DB::table('regions')->insertGetId([
                'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (UgandaDistricts::all() as $official => $districts) {
            foreach ($districts as $district) {
                if (! DB::table('districts')->where('name', $district)->exists()) {
                    DB::table('districts')->insert([
                        'name' => $district,
                        'official_region' => $official,
                        'region_id' => $regionIds[$official] ?? null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Link existing franchises by their free-text region where it matches.
        foreach (DB::table('franchises')->whereNull('region_id')->get(['id', 'region']) as $franchise) {
            $match = $franchise->region ? DB::table('regions')->whereRaw('LOWER(name) = ?', [strtolower(trim($franchise->region))])->value('id') : null;
            if ($match) {
                DB::table('franchises')->where('id', $franchise->id)->update(['region_id' => $match]);
            }
        }

        // Existing products: farmer price defaults to the retail price so that
        // nothing is priced at zero until the admin sets a farmer price.
        DB::table('products')->whereNull('farmer_price')->update(['farmer_price' => DB::raw('selling_price')]);

        // Backfill sale cost (franchise price) so historical profit is meaningful.
        if (Schema::hasTable('sale_items')) {
            $items = DB::table('sale_items')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sale_items.cost_price', 0)
                ->get(['sale_items.id', 'sale_items.sale_id', 'sale_items.quantity', 'products.standard_price']);
            foreach ($items as $item) {
                DB::table('sale_items')->where('id', $item->id)->update(['cost_price' => $item->standard_price]);
            }
            foreach (DB::table('sales')->pluck('id') as $saleId) {
                $cost = DB::table('sale_items')->where('sale_id', $saleId)->sum(DB::raw('quantity * cost_price'));
                DB::table('sales')->where('id', $saleId)->update(['total_cost' => $cost]);
            }
        }

        // Ledger backfill from history so statements reconcile with the
        // existing account_balance. Any unexplained remainder becomes an
        // opening balance entry.
        foreach (DB::table('franchises')->get(['id', 'account_balance', 'created_at']) as $franchise) {
            if (DB::table('account_transactions')->where('franchise_id', $franchise->id)->exists()) {
                continue;
            }

            $credit = 0.0;
            $debit = 0.0;
            $rows = [];

            $receipts = DB::table('stock_receipts')
                ->join('orders', 'orders.id', '=', 'stock_receipts.order_id')
                ->where('stock_receipts.franchise_id', $franchise->id)
                ->where('stock_receipts.status', 'confirmed')
                ->get(['orders.id as order_id', 'orders.order_number', 'orders.total_amount', 'stock_receipts.received_at', 'stock_receipts.updated_at']);
            foreach ($receipts as $r) {
                $credit += (float) $r->total_amount;
                $rows[] = [
                    'franchise_id' => $franchise->id, 'type' => 'purchase', 'reference_number' => $r->order_number,
                    'reference_type' => 'App\\Models\\Order', 'reference_id' => $r->order_id,
                    'description' => "Purchase — order {$r->order_number}", 'credit' => $r->total_amount, 'debit' => 0,
                    'transaction_date' => substr((string) ($r->received_at ?? $r->updated_at), 0, 10),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }

            $payments = DB::table('payment_submissions')
                ->where('franchise_id', $franchise->id)
                ->where('status', 'accepted')
                ->get(['id', 'payment_number', 'verified_amount', 'amount', 'accepted_at', 'updated_at']);
            foreach ($payments as $p) {
                $amount = (float) ($p->verified_amount ?? $p->amount);
                $debit += $amount;
                $rows[] = [
                    'franchise_id' => $franchise->id, 'type' => 'payment', 'reference_number' => $p->payment_number,
                    'reference_type' => 'App\\Models\\PaymentSubmission', 'reference_id' => $p->id,
                    'description' => "Payment {$p->payment_number}", 'credit' => 0, 'debit' => $amount,
                    'transaction_date' => substr((string) ($p->accepted_at ?? $p->updated_at), 0, 10),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }

            $difference = round((float) $franchise->account_balance - ($credit - $debit), 2);
            if (abs($difference) >= 0.01) {
                array_unshift($rows, [
                    'franchise_id' => $franchise->id, 'type' => 'opening_balance', 'reference_number' => null,
                    'reference_type' => null, 'reference_id' => null,
                    'description' => 'Opening balance (migrated)',
                    'credit' => $difference > 0 ? $difference : 0, 'debit' => $difference < 0 ? abs($difference) : 0,
                    'transaction_date' => substr((string) ($franchise->created_at ?? $now), 0, 10),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('account_transactions')->insert($chunk);
            }
        }

        // Default settings (only created if missing).
        $defaults = [
            ['terms_and_conditions', DefaultTerms::text(), 'terms'],
            ['terms_version', '1', 'terms'],
            ['dispatch_target_days', '15', 'operations'],
            ['allow_sale_discount', '0', 'operations'],
        ];
        foreach ($defaults as [$key, $value, $group]) {
            if (Schema::hasTable('settings') && ! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key, 'value' => $value, 'group_name' => $group,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('digital_signatures');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn('acceptance_signature');
        });
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'signature_number']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatched_by');
            $table->dropConstrainedForeignId('delivery_confirmed_by');
            $table->dropColumn([
                'payment_terms', 'amount_paid', 'dispatched_at', 'dispatch_means', 'dispatch_vehicle',
                'dispatch_remarks', 'approval_signature', 'dispatch_signature', 'delivery_signature',
            ]);
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['price_tier', 'total_cost']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('farmer_price');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('customer_type');
        });
        Schema::table('franchises', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('region_id');
        });
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regions');
    }
};
