<?php

namespace App\Http\Controllers\Api;

use App\Models\CreditNote;
use App\Models\Franchise;
use App\Models\Order;
use App\Models\PaymentSubmission;
use App\Models\Sale;
use App\Models\StockReceipt;
use App\Models\User;

/**
 * Who may print which document. Everyone can print, but franchise partners
 * only their own franchise's documents.
 */
class PrintAccess
{
    public const TYPES = ['order', 'delivery-note', 'receipt', 'payment', 'sale', 'statement', 'stock-report', 'credit-note'];

    public static function check(User $user, string $type, int $id): bool
    {
        if (! in_array($type, self::TYPES, true) || ! $user->is_active) {
            return false;
        }

        $franchiseId = match ($type) {
            'order', 'delivery-note' => Order::whereKey($id)->value('franchise_id'),
            'receipt' => StockReceipt::whereKey($id)->value('franchise_id'),
            'payment' => PaymentSubmission::whereKey($id)->value('franchise_id'),
            'sale' => Sale::whereKey($id)->value('franchise_id'),
            'credit-note' => CreditNote::whereKey($id)->value('franchise_id'),
            'statement' => Franchise::whereKey($id)->value('id'),
            'stock-report' => $id ?: null,
        };

        if ($type !== 'stock-report' && ! $franchiseId) {
            return false;
        }

        if ($user->isFranchisePartner()) {
            return $type === 'stock-report'
                ? ($id === 0 || $id === (int) $user->franchise_id)
                : (int) $franchiseId === (int) $user->franchise_id;
        }

        // Admin, sales staff, warehouse and finance may print any document;
        // sales/franchise documents are not finance's concern but harmless.
        return $user->isAdmin() || $user->isStaff() || $user->isWarehouse() || $user->isFinance();
    }
}
