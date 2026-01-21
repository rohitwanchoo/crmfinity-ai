<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McaPattern extends Model
{
    use HasFactory;

    protected $fillable = [
        'description_pattern',
        'lender_id',
        'lender_name',
        'is_mca',
        'usage_count',
        'user_id',
    ];

    protected $casts = [
        'is_mca' => 'boolean',
    ];

    /**
     * Normalize a description into a pattern for matching.
     */
    public static function normalizePattern(string $description): string
    {
        // Remove dates
        $normalized = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $description);
        // Remove specific numbers but keep general structure
        $normalized = preg_replace('/\d{6,}/', '#ID#', $normalized); // Account/reference numbers
        $normalized = preg_replace('/\$[\d,]+\.?\d*/', '', $normalized); // Dollar amounts
        // Clean up whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }

    /**
     * Record an MCA pattern from user correction.
     */
    public static function recordPattern(
        string $description,
        string $lenderId,
        string $lenderName,
        bool $isMca = true,
        ?int $userId = null
    ): self {
        $pattern = self::normalizePattern($description);

        $existing = self::where('description_pattern', $pattern)->first();

        if ($existing) {
            $existing->update([
                'lender_id' => $lenderId,
                'lender_name' => $lenderName,
                'is_mca' => $isMca,
                'usage_count' => $existing->usage_count + 1,
            ]);
            return $existing;
        }

        return self::create([
            'description_pattern' => $pattern,
            'lender_id' => $lenderId,
            'lender_name' => $lenderName,
            'is_mca' => $isMca,
            'user_id' => $userId,
        ]);
    }

    /**
     * Check if a description matches a learned MCA pattern.
     * Returns lender info if match found, null otherwise.
     */
    public static function checkMcaPattern(string $description): ?array
    {
        $pattern = self::normalizePattern($description);
        $patternLower = strtolower($pattern);

        // Get all learned patterns ordered by usage count
        $learnedPatterns = self::where('is_mca', true)
            ->orderBy('usage_count', 'desc')
            ->get();

        foreach ($learnedPatterns as $learned) {
            $learnedPatternLower = strtolower($learned->description_pattern);

            // Check for various matching strategies
            if ($patternLower === $learnedPatternLower ||
                (!empty($learnedPatternLower) && stripos($patternLower, $learnedPatternLower) !== false) ||
                (!empty($patternLower) && stripos($learnedPatternLower, $patternLower) !== false)) {
                return [
                    'lender_id' => $learned->lender_id,
                    'lender_name' => $learned->lender_name,
                    'source' => 'learned',
                ];
            }

            // Check key words match
            $learnedWords = array_filter(explode(' ', $learnedPatternLower), fn($w) => strlen($w) > 3);
            if (count($learnedWords) >= 2) {
                $matchCount = 0;
                foreach ($learnedWords as $word) {
                    if (stripos($patternLower, $word) !== false) {
                        $matchCount++;
                    }
                }
                if ($matchCount >= ceil(count($learnedWords) * 0.6)) {
                    return [
                        'lender_id' => $learned->lender_id,
                        'lender_name' => $learned->lender_name,
                        'source' => 'learned',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check if a description is explicitly marked as NOT an MCA.
     */
    public static function isExcluded(string $description): bool
    {
        $pattern = self::normalizePattern($description);
        $patternLower = strtolower($pattern);

        return self::where('is_mca', false)
            ->whereRaw('LOWER(description_pattern) = ?', [$patternLower])
            ->exists();
    }

    /**
     * Get list of known lenders for UI dropdown.
     */
    public static function getKnownLenders(): array
    {
        return [
            'alliance_1st' => '1st Alliance',
            'advanced_servicing' => 'Advanced Servicing',
            'alpine_advance' => 'Alpine Advance',
            'balboa' => 'Balboa',
            'bitty_advance' => 'Bitty Advance',
            'bluevine' => 'BlueVine',
            'broadway_advance' => 'Broadway Advance',
            'byzfunder' => 'ByzFunder',
            'can_capital' => 'CAN Capital',
            'capybara' => 'Capybara',
            'cashfloit' => 'Cashfloit',
            'caymus' => 'Caymus',
            'cfg_merchant' => 'CFG Merchant Solutions',
            'channel' => 'Channel',
            'choice_financial' => 'Choice Financial',
            'cobalt_fund' => 'Cobalt Fund',
            'dlp' => 'DLP',
            'doordash' => 'DoorDash',
            'doordash_capital' => 'DoorDash Capital',
            'ebf' => 'EBF',
            'expansion' => 'Expansion',
            'forward_financing' => 'Forward Financing',
            'fox_funding' => 'Fox Funding',
            'fratello' => 'Fratello',
            'fundamental' => 'Fundamental',
            'funder' => 'Funder',
            'funding_futures' => 'Funding Futures',
            'funding_metrics' => 'Funding Metrics/Lendini',
            'gfe' => 'Global Funding Experts (GFE)',
            'gold_buyer' => 'Gold Buyer',
            'honest_funding' => 'Honest Funding',
            'iou_financial' => 'IOU Financial',
            'itra_ventures' => 'Itra Ventures',
            'kapitus' => 'Kapitus',
            'last_chance_funding' => 'Last Chance Funding (LCF)',
            'lexio' => 'Lexio',
            'lg' => 'LG',
            'liquidbee' => 'LiquidBee',
            'mantis' => 'Mantis',
            'mckensie_capital' => 'McKensie Capital',
            'merch_advance_now' => 'Merch Advance Now',
            'merchant_marketplace' => 'Merchant Market Place',
            'mint_funding' => 'Mint Funding',
            'olympus_lending' => 'Olympus Lending',
            'ondeck' => 'OnDeck Capital',
            'pinnacle' => 'Pinnacle',
            'principus' => 'Principus',
            'prosperum' => 'Prosperum',
            'retail_credibly' => 'Retail (Credibly)',
            'rewards_network' => 'Rewards Network',
            'samson' => 'Samson',
            'seamless' => 'Seamless',
            'silverline' => 'Silverline',
            'small_business' => 'Small Business',
            'speedy' => 'Speedy',
            'rapid_advance' => 'SBFS/Rapid Advance',
            'tvt_capital' => 'TVT Capital',
        ];
    }

    /**
     * Get list of known debt collectors for UI dropdown.
     */
    public static function getKnownDebtCollectors(): array
    {
        return [
            'bousilla_berkovitz' => 'Bousilla & Berkovitz',
            'brennan_clark' => 'Brennan and Clark',
            'ccs_cccs' => 'CCS, CCCS',
            'commercial_asset_recovery' => 'Commercial Asset Recovery',
            'david_fogel_pc' => 'David Fogel PC',
            'dcg' => 'DCG',
            'greenberg_grant_richards' => 'Greenberg Grant and Richards (GG&R)',
            'ivy_receivables' => 'Ivy Receivables',
            'km_recovery' => 'KM Recovery',
            'mca_recovery' => 'MCA Recovery',
            'mcallc' => 'MCALLC',
            'ram' => 'RAM',
            'secure_account_services' => 'Secure Account Services',
            'tfs' => 'TFS',
            'triton_recovery' => 'Triton Recovery',
            'zwicker_associates' => 'Zwicker & Associates',
        ];
    }
}
