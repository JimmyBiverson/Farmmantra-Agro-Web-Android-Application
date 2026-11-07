<?php

namespace App\Console\Commands;

use App\Models\FranchiseInventory;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendOperationalAlerts extends Command
{
    protected $signature = 'farmmantra:operational-alerts';

    protected $description = 'Send daily low stock and payment-due alerts, fifteen days after stock receipt';

    public function handle(): int
    {
        Order::with('franchise')->whereNotNull('received_at')->where('received_at', '<=', now()->subDays(15))->whereRaw('total_amount > amount_paid')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                if (! Cache::add('payment-due:'.$order->id.':'.now()->toDateString(), true, now()->endOfDay())) {
                    continue;
                }
                $ids = User::whereHas('role', fn ($q) => $q->whereIn('name', ['Sales Head', 'Finance Department', 'System Administrator']))->pluck('id')->merge(OrderService::franchiseUserIds($order->franchise_id))->unique()->all();
                NotificationService::send($ids, 'Payment due · '.$order->order_number, $order->franchise->name.' owes UGX '.number_format($order->balance_due).'; stock received '.$order->received_at->toDateString(), 'payment_due', Order::class, $order->id, 'operations');
            }
        });
        FranchiseInventory::with('product')->whereColumn('quantity', '<=', 'reorder_level')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                if (! Cache::add('low-stock:'.$row->id.':'.now()->toDateString(), true, now()->endOfDay())) {
                    continue;
                }
                NotificationService::send(OrderService::franchiseUserIds($row->franchise_id), 'Low inventory', $row->product->name.': '.$row->quantity.' remaining', 'stock_alert', FranchiseInventory::class, $row->id, 'franchise/inventory');
            }
        });
        $this->info('Operational alerts processed.');

        return self::SUCCESS;
    }
}
