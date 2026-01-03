<?php

namespace App\Services;

use App\Models\Product;

class BarcodeService
{
    /**
     * Generate a unique EAN-13 barcode
     *
     * @param int $tenantId
     * @return string
     */
    public function generateUniqueEAN13(int $tenantId): string
    {
        do {
            // Generate 12 random digits
            $code = $this->generateRandomDigits(12);

            // Calculate check digit
            $checkDigit = $this->calculateEAN13CheckDigit($code);

            // Complete EAN-13 code
            $ean13 = $code . $checkDigit;

            // Verify it doesn't exist
            $exists = Product::where('tenant_id', $tenantId)
                ->where('barcode', $ean13)
                ->exists();

        } while ($exists);

        return $ean13;
    }

    /**
     * Generate random digits
     *
     * @param int $length
     * @return string
     */
    private function generateRandomDigits(int $length): string
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            $digits .= rand(0, 9);
        }
        return $digits;
    }

    /**
     * Calculate EAN-13 check digit
     *
     * @param string $code 12-digit code
     * @return int
     */
    private function calculateEAN13CheckDigit(string $code): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$code[$i];
            // Multiply odd positions by 1, even positions by 3
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit;
    }

    /**
     * Validate EAN-13 barcode
     *
     * @param string $barcode
     * @return bool
     */
    public function validateEAN13(string $barcode): bool
    {
        if (strlen($barcode) !== 13) {
            return false;
        }

        $code = substr($barcode, 0, 12);
        $providedCheckDigit = (int)substr($barcode, 12, 1);
        $calculatedCheckDigit = $this->calculateEAN13CheckDigit($code);

        return $providedCheckDigit === $calculatedCheckDigit;
    }

    /**
     * Generate barcode as SVG
     *
     * @param string $code
     * @param string $type
     * @return string SVG content
     */
    public function generateBarcodeSVG(string $code, string $type = 'ean13'): string
    {
        switch ($type) {
            case 'ean13':
                return $this->generateEAN13SVG($code);
            case 'code128':
                return $this->generateCode128SVG($code);
            default:
                return $this->generateEAN13SVG($code);
        }
    }

    /**
     * Generate EAN-13 barcode as SVG
     *
     * @param string $code
     * @return string
     */
    private function generateEAN13SVG(string $code): string
    {
        // EAN-13 encoding patterns
        $leftPatterns = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101',
            '4' => '0100011', '5' => '0110001', '6' => '0101111', '7' => '0111011',
            '8' => '0110111', '9' => '0001011'
        ];

        $rightPatterns = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010',
            '4' => '1011100', '5' => '1001110', '6' => '1010000', '7' => '1000100',
            '8' => '1001000', '9' => '1110100'
        ];

        $firstDigitPatterns = [
            '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL',
            '4' => 'LGLLGG', '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG',
            '8' => 'LGLGGL', '9' => 'LGGLGL'
        ];

        // Ensure code is exactly 13 digits
        $code = str_pad($code, 13, '0', STR_PAD_LEFT);
        $code = substr($code, 0, 13);

        // Validate all characters are digits
        if (!ctype_digit($code)) {
            $code = '0000000000000';
        }

        $firstDigit = $code[0];
        $pattern = $firstDigitPatterns[$firstDigit];

        $bars = '101'; // Start guard

        // Left side (6 digits)
        for ($i = 1; $i <= 6; $i++) {
            $digit = $code[$i];
            if ($pattern[$i - 1] === 'L') {
                $bars .= $leftPatterns[$digit];
            } else {
                // G pattern is L pattern inverted
                $bars .= str_replace(['0', '1'], ['x', '0'], $leftPatterns[$digit]);
                $bars = str_replace('x', '1', $bars);
            }
        }

        $bars .= '01010'; // Center guard

        // Right side (6 digits)
        for ($i = 7; $i <= 12; $i++) {
            $digit = $code[$i];
            $bars .= $rightPatterns[$digit];
        }

        $bars .= '101'; // End guard

        // Generate SVG
        $width = 300;
        $height = 100;
        $barWidth = $width / strlen($bars);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="white"/>';

        $x = 0;
        for ($i = 0; $i < strlen($bars); $i++) {
            if ($bars[$i] === '1') {
                $svg .= '<rect x="' . $x . '" y="0" width="' . $barWidth . '" height="' . ($height - 20) . '" fill="black"/>';
            }
            $x += $barWidth;
        }

        // Add text
        $svg .= '<text x="' . ($width / 2) . '" y="' . ($height - 5) . '" font-family="Arial" font-size="14" text-anchor="middle" fill="black">' . $code . '</text>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate Code 128 barcode as SVG (simplified)
     *
     * @param string $code
     * @return string
     */
    private function generateCode128SVG(string $code): string
    {
        // Simplified Code 128 - for full implementation, use a library
        $width = 300;
        $height = 100;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
        $svg .= '<rect width="' . $width . '" height="' . $height . '" fill="white"/>';

        // Placeholder bars
        $barCount = strlen($code) * 3;
        $barWidth = $width / $barCount;

        for ($i = 0; $i < $barCount; $i += 2) {
            $x = $i * $barWidth;
            $svg .= '<rect x="' . $x . '" y="0" width="' . $barWidth . '" height="' . ($height - 20) . '" fill="black"/>';
        }

        $svg .= '<text x="' . ($width / 2) . '" y="' . ($height - 5) . '" font-family="Arial" font-size="14" text-anchor="middle" fill="black">' . $code . '</text>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Generate label data for printing
     *
     * @param array $products Array of product data with quantities
     * @param array $options Label options (size, columns, etc.)
     * @return array
     */
    public function generateLabels(array $products, array $options = []): array
    {
        $labels = [];

        foreach ($products as $productData) {
            $product = $productData['product'];
            $quantity = $productData['quantity'] ?? 1;

            // Get barcode or use default
            $barcode = $product['barcode'] ?? null;

            // Skip products without barcode for now, or generate a placeholder
            if (empty($barcode)) {
                // Generate a default barcode for display
                $barcode = '0000000000000';
            }

            // Ensure barcode is 13 digits for EAN-13
            if (strlen($barcode) < 13) {
                $barcode = str_pad($barcode, 13, '0', STR_PAD_LEFT);
            } elseif (strlen($barcode) > 13) {
                $barcode = substr($barcode, 0, 13);
            }

            for ($i = 0; $i < $quantity; $i++) {
                $labels[] = [
                    'barcode' => $barcode,
                    'name' => $product['name'] ?? 'Sin nombre',
                    'sku' => $product['sku'] ?? '',
                    'price' => $product['price'] ?? 0,
                    'barcode_svg' => $this->generateBarcodeSVG($barcode, 'ean13'),
                    'barcode_html' => $this->generateBarcodeHTML($barcode)
                ];
            }
        }

        return $labels;
    }

    /**
     * Generate barcode as HTML (for PDF rendering)
     *
     * @param string $code
     * @return string
     */
    public function generateBarcodeHTML(string $code): string
    {
        // EAN-13 encoding patterns
        $leftPatterns = [
            '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101',
            '4' => '0100011', '5' => '0110001', '6' => '0101111', '7' => '0111011',
            '8' => '0110111', '9' => '0001011'
        ];

        $rightPatterns = [
            '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010',
            '4' => '1011100', '5' => '1001110', '6' => '1010000', '7' => '1000100',
            '8' => '1001000', '9' => '1110100'
        ];

        $firstDigitPatterns = [
            '0' => 'LLLLLL', '1' => 'LLGLGG', '2' => 'LLGGLG', '3' => 'LLGGGL',
            '4' => 'LGLLGG', '5' => 'LGGLLG', '6' => 'LGGGLL', '7' => 'LGLGLG',
            '8' => 'LGLGGL', '9' => 'LGGLGL'
        ];

        // Ensure code is exactly 13 digits
        $code = str_pad($code, 13, '0', STR_PAD_LEFT);
        $code = substr($code, 0, 13);

        if (!ctype_digit($code)) {
            $code = '0000000000000';
        }

        $firstDigit = $code[0];
        $pattern = $firstDigitPatterns[$firstDigit];

        $bars = '101'; // Start guard

        // Left side (6 digits)
        for ($i = 1; $i <= 6; $i++) {
            $digit = $code[$i];
            if ($pattern[$i - 1] === 'L') {
                $bars .= $leftPatterns[$digit];
            } else {
                // G pattern is L pattern inverted
                $bars .= str_replace(['0', '1'], ['x', '0'], $leftPatterns[$digit]);
                $bars = str_replace('x', '1', $bars);
            }
        }

        $bars .= '01010'; // Center guard

        // Right side (6 digits)
        for ($i = 7; $i <= 12; $i++) {
            $digit = $code[$i];
            $bars .= $rightPatterns[$digit];
        }

        $bars .= '101'; // End guard

        // Generate HTML with divs
        $html = '<div style="display:inline-block; background:white; padding:2px;">';

        $barWidth = 2; // Width in pixels
        for ($i = 0; $i < strlen($bars); $i++) {
            $color = $bars[$i] === '1' ? 'black' : 'white';
            $html .= '<div style="display:inline-block; width:' . $barWidth . 'px; height:40px; background-color:' . $color . '; vertical-align:bottom;"></div>';
        }

        $html .= '</div>';

        return $html;
    }
}
