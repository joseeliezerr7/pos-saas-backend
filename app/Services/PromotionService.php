<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Product;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Obtiene las promociones aplicables para un carrito de compras
     *
     * @param array $cartItems Array de items con product_id, variant_id, quantity, price
     * @param int $branchId
     * @param int|null $customerId
     * @return \Illuminate\Support\Collection
     */
    public function getApplicablePromotions(array $cartItems, int $branchId, ?int $customerId = null)
    {
        $totalAmount = $this->calculateCartTotal($cartItems);

        // Obtener promociones activas ordenadas por prioridad
        $promotions = Promotion::active()
            ->autoApply()
            ->byPriority()
            ->get()
            ->filter(function ($promotion) use ($branchId, $customerId, $totalAmount, $cartItems) {
                // Verificar restricciones básicas
                if (!$promotion->isCurrentlyActive()) {
                    return false;
                }

                if (!$promotion->appliesToBranch($branchId)) {
                    return false;
                }

                if (!$promotion->canBeUsedByCustomer($customerId)) {
                    return false;
                }

                if ($promotion->min_purchase_amount && $totalAmount < $promotion->min_purchase_amount) {
                    return false;
                }

                // Verificar si aplica a algún producto del carrito
                if ($promotion->applicable_to !== 'all') {
                    $hasApplicableProduct = false;
                    foreach ($cartItems as $item) {
                        if ($promotion->appliesToProduct($item['product_id'], $item['variant_id'] ?? null)) {
                            $hasApplicableProduct = true;
                            break;
                        }
                    }
                    if (!$hasApplicableProduct) {
                        return false;
                    }
                }

                return true;
            });

        return $promotions;
    }

    /**
     * Aplica una promoción a un carrito y calcula los descuentos
     *
     * @param Promotion $promotion
     * @param array $cartItems
     * @return array Carrito con descuentos aplicados
     */
    public function applyPromotionToCart(Promotion $promotion, array $cartItems): array
    {
        $updatedCart = $cartItems;

        switch ($promotion->type) {
            case 'percentage':
            case 'fixed_amount':
                $updatedCart = $this->applySimpleDiscount($promotion, $cartItems);
                break;

            case 'bogo':
                $updatedCart = $this->applyBogoDiscount($promotion, $cartItems);
                break;

            case 'volume':
                $updatedCart = $this->applyVolumeDiscount($promotion, $cartItems);
                break;

            case 'bundle':
                $updatedCart = $this->applyBundleDiscount($promotion, $cartItems);
                break;
        }

        return $updatedCart;
    }

    /**
     * Valida un código de cupón
     *
     * @param string $code
     * @param int $branchId
     * @param int|null $customerId
     * @return Promotion|null
     */
    public function validateCouponCode(string $code, int $branchId, ?int $customerId = null): ?Promotion
    {
        $promotion = Promotion::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$promotion) {
            return null;
        }

        // Verificar todas las restricciones
        if (!$promotion->isCurrentlyActive()) {
            return null;
        }

        if (!$promotion->appliesToBranch($branchId)) {
            return null;
        }

        if (!$promotion->canBeUsedByCustomer($customerId)) {
            return null;
        }

        return $promotion;
    }

    /**
     * Registra el uso de una promoción
     *
     * @param Promotion $promotion
     * @param Sale $sale
     * @param float $discountAmount
     * @param string|null $couponCode
     * @return PromotionUsage
     */
    public function recordPromotionUsage(
        Promotion $promotion,
        Sale $sale,
        float $discountAmount,
        ?string $couponCode = null
    ): PromotionUsage {
        // Crear registro de uso
        $usage = PromotionUsage::create([
            'tenant_id' => $promotion->tenant_id,
            'promotion_id' => $promotion->id,
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'user_id' => auth()->id(),
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
        ]);

        // Incrementar contador de uso en la promoción
        $promotion->incrementUsage();

        return $usage;
    }

    /**
     * Calcula el total del carrito
     */
    private function calculateCartTotal(array $cartItems): float
    {
        $total = 0;
        foreach ($cartItems as $item) {
            $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }
        return $total;
    }

    /**
     * Aplica descuento simple (porcentaje o monto fijo)
     */
    private function applySimpleDiscount(Promotion $promotion, array $cartItems): array
    {
        $totalDiscount = 0;
        $applicableItems = [];

        // Filtrar solo items aplicables
        foreach ($cartItems as $index => $item) {
            if ($promotion->applicable_to === 'all' ||
                $promotion->appliesToProduct($item['product_id'], $item['variant_id'] ?? null)) {
                $applicableItems[] = $index;
            }
        }

        if (empty($applicableItems)) {
            return $cartItems;
        }

        // Calcular total aplicable
        $applicableTotal = 0;
        foreach ($applicableItems as $index) {
            $applicableTotal += $cartItems[$index]['price'] * $cartItems[$index]['quantity'];
        }

        // Calcular descuento
        if ($promotion->type === 'percentage') {
            $totalDiscount = $applicableTotal * ($promotion->discount_value / 100);
        } else {
            $totalDiscount = $promotion->discount_value;
        }

        // Aplicar límite máximo si existe
        if ($promotion->max_discount_amount) {
            $totalDiscount = min($totalDiscount, $promotion->max_discount_amount);
        }

        // Distribuir el descuento proporcionalmente entre los items aplicables
        foreach ($applicableItems as $index) {
            $itemTotal = $cartItems[$index]['price'] * $cartItems[$index]['quantity'];
            $itemDiscount = ($itemTotal / $applicableTotal) * $totalDiscount;

            $cartItems[$index]['promotion_id'] = $promotion->id;
            $cartItems[$index]['promotion_name'] = $promotion->name;
            $cartItems[$index]['discount'] = round($itemDiscount, 2);
        }

        return $cartItems;
    }

    /**
     * Aplica descuento BOGO (Buy One Get One)
     */
    private function applyBogoDiscount(Promotion $promotion, array $cartItems): array
    {
        $buyQuantity = $promotion->buy_quantity ?? 1;
        $getQuantity = $promotion->get_quantity ?? 1;

        foreach ($cartItems as $index => $item) {
            if (!$promotion->appliesToProduct($item['product_id'], $item['variant_id'] ?? null)) {
                continue;
            }

            $quantity = $item['quantity'];
            $price = $item['price'];

            // Calcular cuántos sets completos hay (compra X + lleva Y)
            $totalPerSet = $buyQuantity + $getQuantity;
            $completeSets = floor($quantity / $totalPerSet);

            if ($completeSets > 0) {
                // El descuento es el precio de los items gratis
                $freeItems = $completeSets * $getQuantity;
                $discount = $freeItems * $price;

                $cartItems[$index]['promotion_id'] = $promotion->id;
                $cartItems[$index]['promotion_name'] = $promotion->name;
                $cartItems[$index]['discount'] = round($discount, 2);
                $cartItems[$index]['bogo_free_items'] = $freeItems;
            }
        }

        return $cartItems;
    }

    /**
     * Aplica descuento por volumen
     */
    private function applyVolumeDiscount(Promotion $promotion, array $cartItems): array
    {
        $minQuantity = $promotion->buy_quantity ?? 1;

        foreach ($cartItems as $index => $item) {
            if (!$promotion->appliesToProduct($item['product_id'], $item['variant_id'] ?? null)) {
                continue;
            }

            if ($item['quantity'] >= $minQuantity) {
                $itemTotal = $item['price'] * $item['quantity'];
                $discount = $promotion->calculateDiscount($itemTotal, $item['quantity']);

                $cartItems[$index]['promotion_id'] = $promotion->id;
                $cartItems[$index]['promotion_name'] = $promotion->name;
                $cartItems[$index]['discount'] = $discount;
            }
        }

        return $cartItems;
    }

    /**
     * Aplica descuento de bundle/combo
     */
    private function applyBundleDiscount(Promotion $promotion, array $cartItems): array
    {
        // Verificar que todos los productos del bundle estén en el carrito
        $requiredProductIds = $promotion->applicable_ids ?? [];

        if (empty($requiredProductIds)) {
            return $cartItems;
        }

        $cartProductIds = array_column($cartItems, 'product_id');

        // Verificar que todos los productos requeridos estén presentes
        foreach ($requiredProductIds as $requiredId) {
            if (!in_array($requiredId, $cartProductIds)) {
                return $cartItems; // No se cumple el bundle
            }
        }

        // Calcular el total del bundle
        $bundleTotal = 0;
        $bundleIndices = [];

        foreach ($cartItems as $index => $item) {
            if (in_array($item['product_id'], $requiredProductIds)) {
                $bundleTotal += $item['price'] * $item['quantity'];
                $bundleIndices[] = $index;
            }
        }

        // Calcular descuento del bundle
        $bundleDiscount = $promotion->calculateDiscount($bundleTotal);

        // Distribuir el descuento proporcionalmente
        foreach ($bundleIndices as $index) {
            $itemTotal = $cartItems[$index]['price'] * $cartItems[$index]['quantity'];
            $itemDiscount = ($itemTotal / $bundleTotal) * $bundleDiscount;

            $cartItems[$index]['promotion_id'] = $promotion->id;
            $cartItems[$index]['promotion_name'] = $promotion->name;
            $cartItems[$index]['discount'] = round($itemDiscount, 2);
        }

        return $cartItems;
    }

    /**
     * Obtiene el mejor descuento disponible para un carrito
     *
     * @param array $cartItems
     * @param int $branchId
     * @param int|null $customerId
     * @return array|null ['promotion' => Promotion, 'cart' => array, 'total_discount' => float]
     */
    public function getBestPromotion(array $cartItems, int $branchId, ?int $customerId = null): ?array
    {
        $applicablePromotions = $this->getApplicablePromotions($cartItems, $branchId, $customerId);

        if ($applicablePromotions->isEmpty()) {
            return null;
        }

        $bestPromotion = null;
        $bestCart = null;
        $bestDiscount = 0;

        foreach ($applicablePromotions as $promotion) {
            $updatedCart = $this->applyPromotionToCart($promotion, $cartItems);
            $totalDiscount = $this->calculateTotalDiscount($updatedCart);

            if ($totalDiscount > $bestDiscount) {
                $bestDiscount = $totalDiscount;
                $bestPromotion = $promotion;
                $bestCart = $updatedCart;
            }
        }

        if (!$bestPromotion) {
            return null;
        }

        return [
            'promotion' => $bestPromotion,
            'cart' => $bestCart,
            'total_discount' => $bestDiscount,
        ];
    }

    /**
     * Calcula el descuento total de un carrito
     */
    private function calculateTotalDiscount(array $cartItems): float
    {
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['discount'] ?? 0;
        }
        return $total;
    }
}
