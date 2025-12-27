<?php

namespace App\Services;

use App\Events\LowCorrelativesAlert;
use App\Models\Fiscal\CAI;
use App\Models\Fiscal\Correlative;
use Illuminate\Support\Facades\DB;

class CorrelativeService
{
    /**
     * Generate correlatives for a CAI
     */
    public function generateCorrelatives(CAI $cai): int
    {
        $start = $this->extractNumber($cai->range_start);
        $end = $this->extractNumber($cai->range_end);

        $totalGenerated = 0;
        $correlatives = [];

        for ($i = $start; $i <= $end; $i++) {
            $correlatives[] = [
                'cai_id' => $cai->id,
                'number' => $i,
                'formatted_number' => $this->formatNumber($i),
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in batches of 1000
            if (count($correlatives) >= 1000) {
                Correlative::insert($correlatives);
                $totalGenerated += count($correlatives);
                $correlatives = [];
            }
        }

        // Insert remaining
        if (count($correlatives) > 0) {
            Correlative::insert($correlatives);
            $totalGenerated += count($correlatives);
        }

        // Update CAI total_documents
        $cai->update(['total_documents' => $totalGenerated]);

        return $totalGenerated;
    }

    /**
     * Get next available correlative for a branch and document type
     */
    public function getNextCorrelative(int $branchId, string $documentType = 'FACTURA'): Correlative
    {
        return DB::transaction(function () use ($branchId, $documentType) {
            // Find active CAI
            $cai = CAI::where('branch_id', $branchId)
                      ->where('document_type', $documentType)
                      ->where('status', 'active')
                      ->where('expiration_date', '>=', now()->toDateString())
                      ->firstOrFail();

            // Get next available correlative with pessimistic locking
            $correlative = Correlative::where('cai_id', $cai->id)
                                      ->where('status', 'available')
                                      ->orderBy('number')
                                      ->lockForUpdate()
                                      ->firstOrFail();

            // Mark as used
            $correlative->update([
                'status' => 'used',
                'used_at' => now(),
            ]);

            // Update CAI used_documents counter
            $cai->increment('used_documents');

            // Check if we're running low on correlatives
            $remaining = $cai->getAvailableCorrelativesCount();
            $threshold = config('fiscal.sar.correlative.alert_count', 100);

            if ($remaining <= $threshold && $remaining > 0) {
                event(new LowCorrelativesAlert($cai, $remaining));
            }

            // Check if CAI is depleted
            if ($remaining === 0) {
                $cai->update(['status' => 'depleted']);
            }

            return $correlative;
        });
    }

    /**
     * Extract numeric part from formatted correlative
     */
    protected function extractNumber(string $formatted): int
    {
        // Remove dashes and convert to integer
        return (int) str_replace('-', '', $formatted);
    }

    /**
     * Format number to correlative format: XXX-XXX-XX-XXXXXXXX
     */
    protected function formatNumber(int $number): string
    {
        $format = config('fiscal.sar.correlative.format', '%03d-%03d-%02d-%08d');

        // For now, using fixed values for establishment and point of emission
        // In production, these should come from branch configuration
        return sprintf($format, 1, 1, 1, $number);
    }

    /**
     * Void a correlative (used when an invoice is voided)
     */
    public function voidCorrelative(Correlative $correlative): bool
    {
        return $correlative->update([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    /**
     * Get available correlatives count for a CAI
     */
    public function getAvailableCount(CAI $cai): int
    {
        return Correlative::where('cai_id', $cai->id)
                          ->where('status', 'available')
                          ->count();
    }

    /**
     * Check if a CAI needs more correlatives
     */
    public function needsAlert(CAI $cai): bool
    {
        $threshold = config('fiscal.sar.correlative.alert_count', 100);
        return $this->getAvailableCount($cai) <= $threshold;
    }
}
