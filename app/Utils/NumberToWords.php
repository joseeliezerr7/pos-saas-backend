<?php

namespace App\Utils;

class NumberToWords
{
    private array $units = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
    ];

    private array $teens = [
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
        'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    private array $tens = [
        '', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA',
        'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private array $hundreds = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS',
        'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    /**
     * Convert a number to words in Spanish
     */
    public function convert(float $number): string
    {
        $currency = config('fiscal.currency.code', 'HNL');
        $currencyName = $currency === 'HNL' ? 'LEMPIRAS' : 'PESOS';

        $integerPart = (int) $number;
        $decimalPart = round(($number - $integerPart) * 100);

        if ($integerPart === 0) {
            $words = 'CERO';
        } else {
            $words = $this->convertInteger($integerPart);
        }

        if ($decimalPart > 0) {
            return sprintf(
                '%s CON %02d/100 %s',
                $words,
                $decimalPart,
                $currencyName
            );
        }

        return sprintf('%s %s EXACTOS', $words, $currencyName);
    }

    /**
     * Convert integer part to words
     */
    private function convertInteger(int $number): string
    {
        if ($number < 10) {
            return $this->units[$number];
        }

        if ($number < 20) {
            return $this->teens[$number - 10];
        }

        if ($number < 100) {
            $unit = $number % 10;
            $ten = (int) ($number / 10);

            if ($unit === 0) {
                return $this->tens[$ten];
            }

            if ($ten === 2) {
                return 'VEINTI' . $this->units[$unit];
            }

            return $this->tens[$ten] . ' Y ' . $this->units[$unit];
        }

        if ($number < 1000) {
            $hundred = (int) ($number / 100);
            $remainder = $number % 100;

            if ($number === 100) {
                return 'CIEN';
            }

            if ($remainder === 0) {
                return $this->hundreds[$hundred];
            }

            return $this->hundreds[$hundred] . ' ' . $this->convertInteger($remainder);
        }

        if ($number < 1000000) {
            $thousand = (int) ($number / 1000);
            $remainder = $number % 1000;

            $words = '';

            if ($thousand === 1) {
                $words = 'MIL';
            } else {
                $words = $this->convertInteger($thousand) . ' MIL';
            }

            if ($remainder > 0) {
                $words .= ' ' . $this->convertInteger($remainder);
            }

            return $words;
        }

        if ($number < 1000000000) {
            $million = (int) ($number / 1000000);
            $remainder = $number % 1000000;

            $words = '';

            if ($million === 1) {
                $words = 'UN MILLON';
            } else {
                $words = $this->convertInteger($million) . ' MILLONES';
            }

            if ($remainder > 0) {
                $words .= ' ' . $this->convertInteger($remainder);
            }

            return $words;
        }

        return 'NUMERO DEMASIADO GRANDE';
    }
}
