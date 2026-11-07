<?php

namespace App\Services;

use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TargetProgressService
{
    /** Aggregate the selected targets in one sales query, including product/category filters. */
    public static function calculate(Collection $targets): array
    {
        if ($targets->isEmpty()) {
            return [];
        }
        $query = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->join('products', 'products.id', '=', 'sale_items.product_id');
        $starts = [];
        $ends = [];
        foreach ($targets as $target) {
            $start = $target->starts_on ?: sprintf('%04d-%02d-01', $target->year, $target->month);
            $end = $target->ends_on ?: Carbon::parse($start)->endOfMonth()->toDateString();
            $starts[] = $start;
            $ends[] = $end;
            $conditions = ['sales.franchise_id = ?', 'sales.sale_date >= ?', 'sales.sale_date <= ?'];
            $bindings = [$target->franchise_id, $start, $end];
            if ($target->product_id) {
                $conditions[] = 'products.id = ?';
                $bindings[] = $target->product_id;
            }
            if ($target->product_category_id) {
                $conditions[] = 'products.category_id = ?';
                $bindings[] = $target->product_category_id;
            }
            $query->selectRaw('COALESCE(SUM(CASE WHEN '.implode(' AND ', $conditions).' THEN sale_items.subtotal ELSE 0 END),0) AS target_'.(int) $target->id, $bindings);
        }
        $totals = (array) $query->whereIn('sales.franchise_id', $targets->pluck('franchise_id')->unique())->whereBetween('sales.sale_date', [min($starts), max($ends)])->first();
        $result = [];
        foreach ($targets as $target) {
            $actual = (float) ($totals['target_'.$target->id] ?? 0);
            $result[$target->id] = ['actual_amount' => $actual, 'achievement_percentage' => $target->target_amount > 0 ? round($actual / $target->target_amount * 100, 1) : null];
        }

        return $result;
    }

    public static function summary(User $actor): array
    {
        if (! $actor->isFranchisePartner() && ! $actor->isSalesHead()) {
            return [];
        }
        $targets = SalesTarget::when($actor->isFranchisePartner(), fn ($q) => $q->where('franchise_id', $actor->franchise_id))->when($actor->isSalesHead(), fn ($q) => $q->where('user_id', $actor->id))->where('year', now()->year)->get()->filter(function ($target) {
            $start = $target->starts_on ?: sprintf('%04d-%02d-01', $target->year, $target->month);
            $end = $target->ends_on ?: Carbon::parse($start)->endOfMonth()->toDateString();

            return now()->toDateString() >= $start && now()->toDateString() <= $end;
        });
        $actuals = self::calculate($targets);
        $result = [];
        foreach (['weekly', 'monthly', 'yearly'] as $period) {
            $selected = $targets->where('period', $period);
            $goal = (float) $selected->sum('target_amount');
            $actual = $selected->sum(fn ($t) => $actuals[$t->id]['actual_amount'] ?? 0);
            $result[$period] = ['actual' => $actual, 'target' => $goal, 'percentage' => $goal > 0 ? round($actual / $goal * 100, 1) : null];
        }

        return $result;
    }
}
