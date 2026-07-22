<?php

declare(strict_types=1);

namespace App\Modules\Protocol\Application\Services;

use App\Modules\Protocol\Infrastructure\Models\Protocol;
use App\Modules\Protocol\Infrastructure\Models\ProtocolDefect;

/**
 * Service for calculating deposit amounts based on defects.
 *
 * Formula: amount_to_return = deposit_amount - total_damage_cost
 *
 * Note: This is manual calculation only (no auto-assessment by design).
 */
final class DepositCalculationService
{
    /**
     * Calculate total damage cost from all defects.
     */
    public function calculateTotalDamageCost(Protocol $protocol): float
    {
        return (float) $protocol->defects()
            ->whereNotNull('estimated_cost')
            ->sum('estimated_cost');
    }

    /**
     * Calculate amount to return after deducting damages.
     */
    public function calculateAmountToReturn(Protocol $protocol): float
    {
        $depositAmount = (float) ($protocol->deposit_amount ?? 0);
        $totalDamageCost = $this->calculateTotalDamageCost($protocol);

        $amountToReturn = $depositAmount - $totalDamageCost;

        // Cannot return negative amount
        return max(0, $amountToReturn);
    }

    /**
     * Get calculation breakdown.
     */
    public function getCalculationBreakdown(Protocol $protocol): array
    {
        $depositAmount = (float) ($protocol->deposit_amount ?? 0);
        $defects = $protocol->defects()
            ->whereNotNull('estimated_cost')
            ->orderBy('estimated_cost', 'desc')
            ->get();

        $totalDamageCost = $defects->sum('estimated_cost');
        $amountToReturn = max(0, $depositAmount - $totalDamageCost);

        return [
            'deposit_amount' => $depositAmount,
            'total_damage_cost' => (float) $totalDamageCost,
            'amount_to_return' => $amountToReturn,
            'defects' => $defects->map(fn (ProtocolDefect $defect) => [
                'id' => $defect->id,
                'title' => $defect->title,
                'severity' => $defect->severity->value,
                'estimated_cost' => (float) $defect->estimated_cost,
                'is_manual' => $defect->is_cost_manual,
            ])->toArray(),
            'defect_count' => $defects->count(),
        ];
    }

    /**
     * Check if deposit covers all damages.
     */
    public function isDepositSufficient(Protocol $protocol): bool
    {
        $depositAmount = (float) ($protocol->deposit_amount ?? 0);
        $totalDamageCost = $this->calculateTotalDamageCost($protocol);

        return $depositAmount >= $totalDamageCost;
    }

    /**
     * Get shortfall amount if deposit doesn't cover damages.
     */
    public function getShortfallAmount(Protocol $protocol): float
    {
        $depositAmount = (float) ($protocol->deposit_amount ?? 0);
        $totalDamageCost = $this->calculateTotalDamageCost($protocol);

        $shortfall = $totalDamageCost - $depositAmount;

        return max(0, $shortfall);
    }
}
