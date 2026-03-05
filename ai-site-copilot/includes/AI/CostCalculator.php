<?php
namespace AISC\AI;

if (!defined('ABSPATH')) exit;

class CostCalculator {

    /**
     * Returns USD cost estimate based on model + tokens.
     * Note: Pricing can change; keep this table editable later if you want.
     */
    public static function estimate(string $model, int $totalTokens, int $inputTokens = 0, int $outputTokens = 0): float
    {
        $model = trim(strtolower($model));

        // If we don't have split tokens, estimate 70% input / 30% output
        if ($inputTokens <= 0 && $outputTokens <= 0) {
            $inputTokens  = (int) round($totalTokens * 0.7);
            $outputTokens = max(0, $totalTokens - $inputTokens);
        }

        // Pricing table (USD per 1M tokens) — update anytime
        $prices = [
            // Example defaults (adjust to your actual model choice)
            'gpt-4.1-mini' => ['in' => 0.15, 'out' => 0.60],
            'gpt-4.1'      => ['in' => 5.00, 'out' => 15.00],
            'gpt-4o-mini'  => ['in' => 0.15, 'out' => 0.60],
            'gpt-4o'       => ['in' => 5.00, 'out' => 15.00],
        ];

        // fallback pricing if model unknown
        $p = $prices[$model] ?? ['in' => 0.15, 'out' => 0.60];

        $costIn  = ($inputTokens  / 1_000_000) * (float)$p['in'];
        $costOut = ($outputTokens / 1_000_000) * (float)$p['out'];

        return round($costIn + $costOut, 6);
    }
}