<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupPrice;
use Illuminate\Support\Facades\DB;

class CustomerGroupService
{
    /**
     * Calcular y actualizar análisis RFM para todos los clientes de una empresa
     */
    public function calculateRFMForCompany($companyId)
    {
        $customers = Customer::where('tenant_id', $companyId)->with('sales')->get();

        $customerMetrics = $customers->map(function ($customer) {
            $sales = $customer->sales()->where('status', 'completed')->get();
            $recency = $customer->last_purchase_date ? now()->diffInDays($customer->last_purchase_date) : 9999;
            $frequency = $sales->count();
            $monetary = $sales->sum('total');

            return [
                'customer_id' => $customer->id,
                'recency' => $recency,
                'frequency' => $frequency,
                'monetary' => $monetary,
            ];
        });

        $recencyQuintiles = $this->calculateQuintiles($customerMetrics->pluck('recency')->toArray(), true);
        $frequencyQuintiles = $this->calculateQuintiles($customerMetrics->pluck('frequency')->toArray());
        $monetaryQuintiles = $this->calculateQuintiles($customerMetrics->pluck('monetary')->toArray());

        foreach ($customerMetrics as $metrics) {
            $recencyScore = $this->assignScore($metrics['recency'], $recencyQuintiles, true);
            $frequencyScore = $this->assignScore($metrics['frequency'], $frequencyQuintiles);
            $monetaryScore = $this->assignScore($metrics['monetary'], $monetaryQuintiles);
            $segment = $this->determineSegment($recencyScore, $frequencyScore, $monetaryScore);

            Customer::where('id', $metrics['customer_id'])->update([
                'rfm_recency_score' => $recencyScore,
                'rfm_frequency_score' => $frequencyScore,
                'rfm_monetary_score' => $monetaryScore,
                'rfm_segment' => $segment,
            ]);
        }

        return true;
    }

    private function calculateQuintiles(array $values, $reverse = false)
    {
        $sortedValues = collect($values)->filter()->sort()->values();
        if ($sortedValues->isEmpty()) return [0, 0, 0, 0, 0];
        $count = $sortedValues->count();

        return [
            $sortedValues[$count >= 5 ? intval($count * 0.2) : 0] ?? 0,
            $sortedValues[$count >= 5 ? intval($count * 0.4) : 0] ?? 0,
            $sortedValues[$count >= 5 ? intval($count * 0.6) : 0] ?? 0,
            $sortedValues[$count >= 5 ? intval($count * 0.8) : 0] ?? 0,
            $sortedValues[$count - 1] ?? 0,
        ];
    }

    private function assignScore($value, $quintiles, $reverse = false)
    {
        if ($value <= $quintiles[0]) return $reverse ? 5 : 1;
        if ($value <= $quintiles[1]) return $reverse ? 4 : 2;
        if ($value <= $quintiles[2]) return $reverse ? 3 : 3;
        if ($value <= $quintiles[3]) return $reverse ? 2 : 4;
        return $reverse ? 1 : 5;
    }

    private function determineSegment($r, $f, $m)
    {
        if ($r >= 4 && $f >= 4 && $m >= 4) return 'Champions';
        if ($r >= 3 && $f >= 4) return 'Loyal';
        if ($r >= 4 && $f >= 2 && $f <= 3) return 'Potential Loyalist';
        if ($r >= 4 && $f <= 2) return 'New Customer';
        if ($r >= 3 && $r <= 4 && $f <= 2 && $m >= 3) return 'Promising';
        if ($r >= 2 && $r <= 3 && $f >= 3 && $m >= 3) return 'Need Attention';
        if ($r >= 2 && $r <= 3 && $f <= 2) return 'About to Sleep';
        if ($r <= 2 && $f >= 2 && $m >= 4) return 'At Risk';
        if ($r <= 2 && $f >= 4 && $m >= 4) return 'Cant Lose';
        if ($r <= 2 && $f <= 2) return 'Hibernating';
        return 'Others';
    }

    public function updateCustomerMetrics($customerId, $saleTotal)
    {
        $customer = Customer::find($customerId);
        if (!$customer) return false;

        $customer->update([
            'last_purchase_date' => now(),
            'total_purchases' => $customer->total_purchases + 1,
            'lifetime_value' => $customer->lifetime_value + $saleTotal,
        ]);

        return true;
    }

    public function getApplicablePrice($customerId, $productId, $basePrice)
    {
        $customer = Customer::with('customerGroup')->find($customerId);
        if (!$customer) return $basePrice;

        if ($customer->customerGroup) {
            $specialPrice = $customer->customerGroup->getProductPrice($productId);
            if ($specialPrice !== null) return $specialPrice;

            if ($customer->customerGroup->hasDiscount()) {
                $discount = $customer->customerGroup->discount_percentage;
                return $basePrice * (1 - ($discount / 100));
            }
        }

        return $basePrice;
    }

    public function setGroupPrice($groupId, $productId, $price, $validFrom = null, $validUntil = null)
    {
        return CustomerGroupPrice::updateOrCreate(
            ['customer_group_id' => $groupId, 'product_id' => $productId],
            [
                'price' => $price,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_active' => true
            ]
        );
    }

    public function getSegmentationStats($companyId)
    {
        return [
            'by_segment' => Customer::where('tenant_id', $companyId)
                ->select('rfm_segment', DB::raw('count(*) as count'))
                ->whereNotNull('rfm_segment')
                ->groupBy('rfm_segment')
                ->get(),
            'by_group' => Customer::where('tenant_id', $companyId)
                ->join('customer_groups', 'customers.customer_group_id', '=', 'customer_groups.id')
                ->select('customer_groups.name', DB::raw('count(*) as count'))
                ->groupBy('customer_groups.name')
                ->get(),
            'total_customers' => Customer::where('tenant_id', $companyId)->count(),
            'avg_lifetime_value' => Customer::where('tenant_id', $companyId)->avg('lifetime_value'),
        ];
    }
}
