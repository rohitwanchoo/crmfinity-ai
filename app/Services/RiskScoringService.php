<?php

namespace App\Services;

use Exception;

class RiskScoringService
{
    protected array $weights;

    protected array $thresholds;

    protected array $industryRisk;

    protected ?TransactionParserService $transactionParser = null;

    protected ?BankStatementAnalyzerService $bankAnalyzer = null;

    protected ?FraudDetectionService $fraudDetector = null;

    protected ?CustomRiskRulesEngine $rulesEngine = null;

    protected ?PositionStackingOptimizer $stackingOptimizer = null;

    public function __construct(
        ?TransactionParserService $transactionParser = null,
        ?BankStatementAnalyzerService $bankAnalyzer = null,
        ?FraudDetectionService $fraudDetector = null,
        ?CustomRiskRulesEngine $rulesEngine = null,
        ?PositionStackingOptimizer $stackingOptimizer = null
    ) {
        $this->weights = config('risk_scoring.weights');
        $this->thresholds = config('risk_scoring.decision_thresholds');
        $this->industryRisk = config('risk_scoring.industry_risk_levels');

        $this->transactionParser = $transactionParser;
        $this->bankAnalyzer = $bankAnalyzer;
        $this->fraudDetector = $fraudDetector;
        $this->rulesEngine = $rulesEngine;
        $this->stackingOptimizer = $stackingOptimizer;
    }

    /**
     * Calculate overall risk for an application model
     */
    public function calculateOverallRisk($application): array
    {
        // Prepare data from application model
        $data = [];

        // Add credit data if available
        if ($application->credit_score) {
            $data['credit'] = [
                'credit_score' => $application->credit_score,
                'flags' => []
            ];
        }

        // Add industry data if available
        if ($application->business_type) {
            $data['industry'] = $application->business_type;
        }

        // Add bank analysis data from bank statements if available
        if ($application->plaidItems()->exists()) {
            $plaidItem = $application->plaidItems()->first();
            if ($plaidItem && $plaidItem->accounts()->exists()) {
                $account = $plaidItem->accounts()->first();
                $data['bank_analysis'] = [
                    'avg_daily_balance' => $account->balances['current'] ?? 0,
                    'revenue_consistency' => 0.7, // Default - could be calculated from transactions
                    'nsf_count' => 0,
                    'negative_days' => 0,
                ];
            }
        }

        // Calculate using the main risk score method
        return $this->calculateRiskScore($data);
    }

    /**
     * Calculate comprehensive risk score
     */
    public function calculateRiskScore(array $data): array
    {
        $scores = [];
        $details = [];

        // Credit Score Component
        if (isset($data['credit'])) {
            $creditScore = $this->normalizeCreditScore($data['credit']['credit_score'] ?? 0);
            $scores['credit_score'] = $creditScore;
            $details['credit'] = [
                'raw_score' => $data['credit']['credit_score'] ?? 0,
                'normalized_score' => $creditScore,
                'weight' => $this->weights['credit_score'],
                'weighted_score' => $creditScore * $this->weights['credit_score'],
                'flags' => $data['credit']['flags'] ?? [],
            ];
        }

        // Bank Analysis Component
        if (isset($data['bank_analysis'])) {
            $bankScore = $this->calculateBankScore($data['bank_analysis']);
            $scores['bank_analysis'] = $bankScore;
            $details['bank_analysis'] = [
                'normalized_score' => $bankScore,
                'weight' => $this->weights['bank_analysis'],
                'weighted_score' => $bankScore * $this->weights['bank_analysis'],
                'metrics' => $data['bank_analysis'],
            ];
        }

        // Identity Verification Component
        if (isset($data['identity'])) {
            $identityScore = $data['identity']['score'] ?? 50;
            $scores['identity_verification'] = $identityScore;
            $details['identity'] = [
                'normalized_score' => $identityScore,
                'weight' => $this->weights['identity_verification'],
                'weighted_score' => $identityScore * $this->weights['identity_verification'],
                'status' => $data['identity']['status'] ?? 'pending',
                'flags' => $data['identity']['flags'] ?? [],
            ];
        }

        // Stacking Check Component (DataMerch)
        if (isset($data['stacking'])) {
            $stackingScore = $data['stacking']['risk_score'] ?? 100;
            $scores['stacking_check'] = $stackingScore;
            $details['stacking'] = [
                'normalized_score' => $stackingScore,
                'weight' => $this->weights['stacking_check'],
                'weighted_score' => $stackingScore * $this->weights['stacking_check'],
                'active_mcas' => $data['stacking']['active_mcas'] ?? 0,
                'flags' => $data['stacking']['flags'] ?? [],
            ];
        }

        // UCC Filings Component
        if (isset($data['ucc'])) {
            $uccScore = $data['ucc']['risk_score'] ?? 100;
            $scores['ucc_filings'] = $uccScore;
            $details['ucc'] = [
                'normalized_score' => $uccScore,
                'weight' => $this->weights['ucc_filings'],
                'weighted_score' => $uccScore * $this->weights['ucc_filings'],
                'active_filings' => $data['ucc']['active_filings'] ?? 0,
                'flags' => $data['ucc']['flags'] ?? [],
            ];
        }

        // Industry Risk Component
        if (isset($data['industry'])) {
            $industryScore = $this->calculateIndustryRiskScore($data['industry']);
            $scores['industry_risk'] = $industryScore;
            $details['industry'] = [
                'normalized_score' => $industryScore,
                'weight' => $this->weights['industry_risk'],
                'weighted_score' => $industryScore * $this->weights['industry_risk'],
                'industry' => $data['industry'],
                'risk_level' => $this->getIndustryRiskLevel($data['industry']),
            ];
        }

        // Calculate weighted average
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($scores as $key => $score) {
            $weight = $this->weights[$key] ?? 0;
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        $overallScore = $totalWeight > 0 ? round($weightedSum / $totalWeight) : 0;

        // Get decision recommendation
        $decision = $this->getDecision($overallScore);

        // Compile all flags
        $allFlags = [];
        foreach ($details as $component => $detail) {
            if (isset($detail['flags'])) {
                foreach ($detail['flags'] as $flag) {
                    $allFlags[] = "[{$component}] {$flag}";
                }
            }
        }

        return [
            'overall_score' => $overallScore,
            'risk_level' => $this->getOverallRiskLevel($overallScore),
            'decision' => $decision,
            'component_scores' => $scores,
            'component_details' => $details,
            'flags' => $allFlags,
            'summary' => $this->generateSummary($overallScore, $decision, $allFlags),
        ];
    }

    /**
     * Normalize credit score to 0-100 scale
     */
    protected function normalizeCreditScore(int $creditScore): int
    {
        if ($creditScore >= 750) {
            return 100;
        }
        if ($creditScore >= 700) {
            return 80;
        }
        if ($creditScore >= 650) {
            return 60;
        }
        if ($creditScore >= 600) {
            return 40;
        }
        if ($creditScore >= 550) {
            return 20;
        }

        return 0;
    }

    /**
     * Calculate bank analysis score
     */
    protected function calculateBankScore(array $analysis): int
    {
        $score = 50; // Base score

        // Monthly revenue consistency
        if (isset($analysis['revenue_consistency'])) {
            if ($analysis['revenue_consistency'] >= 0.8) {
                $score += 20;
            } elseif ($analysis['revenue_consistency'] >= 0.6) {
                $score += 10;
            } else {
                $score -= 10;
            }
        }

        // Average daily balance
        if (isset($analysis['avg_daily_balance'])) {
            if ($analysis['avg_daily_balance'] >= 10000) {
                $score += 15;
            } elseif ($analysis['avg_daily_balance'] >= 5000) {
                $score += 10;
            } elseif ($analysis['avg_daily_balance'] < 1000) {
                $score -= 15;
            }
        }

        // NSF/Overdraft history
        if (isset($analysis['nsf_count'])) {
            $score -= min($analysis['nsf_count'] * 5, 25);
        }

        // Negative days
        if (isset($analysis['negative_days'])) {
            $score -= min($analysis['negative_days'] * 2, 20);
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate industry risk score
     */
    protected function calculateIndustryRiskScore(string $industry): int
    {
        $industryLower = strtolower($industry);

        foreach ($this->industryRisk['high'] as $highRisk) {
            if (str_contains($industryLower, strtolower($highRisk))) {
                return 30;
            }
        }

        foreach ($this->industryRisk['medium'] as $mediumRisk) {
            if (str_contains($industryLower, strtolower($mediumRisk))) {
                return 60;
            }
        }

        foreach ($this->industryRisk['low'] as $lowRisk) {
            if (str_contains($industryLower, strtolower($lowRisk))) {
                return 90;
            }
        }

        return 70; // Default for unknown industries
    }

    /**
     * Get industry risk level
     */
    protected function getIndustryRiskLevel(string $industry): string
    {
        $industryLower = strtolower($industry);

        foreach ($this->industryRisk['high'] as $highRisk) {
            if (str_contains($industryLower, strtolower($highRisk))) {
                return 'high';
            }
        }

        foreach ($this->industryRisk['medium'] as $mediumRisk) {
            if (str_contains($industryLower, strtolower($mediumRisk))) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * Get overall risk level
     */
    protected function getOverallRiskLevel(int $score): string
    {
        if ($score >= 80) {
            return 'low';
        }
        if ($score >= 60) {
            return 'medium-low';
        }
        if ($score >= 40) {
            return 'medium';
        }
        if ($score >= 20) {
            return 'medium-high';
        }

        return 'high';
    }

    /**
     * Get decision based on score
     */
    protected function getDecision(int $score): array
    {
        if ($score >= $this->thresholds['auto_approve']) {
            return [
                'action' => 'APPROVE',
                'type' => 'auto',
                'message' => 'Application meets all criteria for automatic approval',
            ];
        }

        if ($score >= $this->thresholds['manual_review']) {
            return [
                'action' => 'REVIEW',
                'type' => 'manual',
                'message' => 'Application requires manual review by underwriter',
            ];
        }

        if ($score >= $this->thresholds['auto_decline']) {
            return [
                'action' => 'REVIEW',
                'type' => 'senior',
                'message' => 'Application requires senior underwriter review',
            ];
        }

        return [
            'action' => 'DECLINE',
            'type' => 'auto',
            'message' => 'Application does not meet minimum criteria',
        ];
    }

    /**
     * Generate human-readable summary
     */
    protected function generateSummary(int $score, array $decision, array $flags): string
    {
        $riskLevel = $this->getOverallRiskLevel($score);

        $summary = "Overall Risk Score: {$score}/100 ({$riskLevel} risk)\n";
        $summary .= "Recommendation: {$decision['action']}\n";

        if (! empty($flags)) {
            $summary .= "\nKey Findings:\n";
            foreach (array_slice($flags, 0, 5) as $flag) {
                $summary .= "- {$flag}\n";
            }
        }

        return $summary;
    }

    /**
     * Calculate offer terms based on risk score
     */
    public function calculateOfferTerms(int $riskScore, float $requestedAmount): array
    {
        // Base factor rate by risk
        if ($riskScore >= 80) {
            $factorRate = 1.15;
            $maxTerm = 12;
            $approvalPercentage = 1.0;
        } elseif ($riskScore >= 60) {
            $factorRate = 1.25;
            $maxTerm = 9;
            $approvalPercentage = 0.8;
        } elseif ($riskScore >= 40) {
            $factorRate = 1.35;
            $maxTerm = 6;
            $approvalPercentage = 0.6;
        } else {
            $factorRate = 1.45;
            $maxTerm = 4;
            $approvalPercentage = 0.4;
        }

        $approvedAmount = $requestedAmount * $approvalPercentage;
        $paybackAmount = $approvedAmount * $factorRate;
        $dailyPayment = $paybackAmount / ($maxTerm * 22); // 22 business days per month

        return [
            'approved_amount' => round($approvedAmount, 2),
            'factor_rate' => $factorRate,
            'payback_amount' => round($paybackAmount, 2),
            'term_months' => $maxTerm,
            'daily_payment' => round($dailyPayment, 2),
            'weekly_payment' => round($dailyPayment * 5, 2),
            'holdback_percentage' => $this->calculateHoldback($riskScore),
        ];
    }

    /**
     * Calculate holdback percentage
     */
    protected function calculateHoldback(int $riskScore): float
    {
        if ($riskScore >= 80) {
            return 0.10;
        }
        if ($riskScore >= 60) {
            return 0.12;
        }
        if ($riskScore >= 40) {
            return 0.15;
        }

        return 0.18;
    }

    /**
     * Perform comprehensive risk assessment with all components
     */
    public function performComprehensiveAssessment(array $data): array
    {
        $startTime = microtime(true);
        $results = [];

        // Parse and analyze bank statements if raw text provided
        if (isset($data['bank_statement_text']) && $this->transactionParser) {
            $parsed = $this->transactionParser->parseTransactions(
                $data['bank_statement_text'],
                $data['bank_name'] ?? null
            );
            $data['parsed_transactions'] = $parsed['transactions'] ?? [];
            $results['transaction_parsing'] = [
                'success' => $parsed['success'] ?? false,
                'transaction_count' => count($parsed['transactions'] ?? []),
                'summary' => $parsed['summary'] ?? [],
            ];
        }

        // Perform bank statement analysis
        if (isset($data['parsed_transactions']) && $this->bankAnalyzer) {
            $bankAnalysis = $this->bankAnalyzer->analyze(
                $data['parsed_transactions'],
                $data['bank_metadata'] ?? []
            );
            $data['bank_analysis'] = $bankAnalysis;
            $results['bank_analysis'] = $bankAnalysis;
        }

        // Run fraud detection
        if (isset($data['parsed_transactions']) && $this->fraudDetector) {
            $fraudAnalysis = $this->fraudDetector->analyze(
                $data['parsed_transactions'],
                $data['application_data'] ?? []
            );
            $data['fraud_analysis'] = $fraudAnalysis;
            $results['fraud_analysis'] = $fraudAnalysis;
        }

        // Calculate base risk score
        $baseRiskScore = $this->calculateRiskScore($data);
        $results['base_risk_score'] = $baseRiskScore;

        // Apply custom rules
        $adjustedScore = $baseRiskScore['overall_score'];
        $allFlags = $baseRiskScore['flags'];

        if ($this->rulesEngine) {
            $ruleResults = $this->rulesEngine->evaluate($data);
            $results['custom_rules'] = $ruleResults;

            // Apply score adjustments
            $adjustedScore += $ruleResults['total_score_adjustment'];
            if (isset($ruleResults['score_multiplier'])) {
                $adjustedScore = (int) round($adjustedScore * $ruleResults['score_multiplier']);
            }
            $adjustedScore = max(0, min(100, $adjustedScore));

            // Add rule flags
            foreach ($ruleResults['flags'] as $flag) {
                $allFlags[] = "[rule] {$flag['message']}";
            }

            // Check for blocking rules
            if ($ruleResults['blocked']) {
                $results['blocked'] = true;
                $results['block_reason'] = $ruleResults['block_reason'];
            }
        }

        // Apply fraud score impact
        if (isset($data['fraud_analysis'])) {
            $fraudScore = $data['fraud_analysis']['fraud_score'] ?? 100;
            if ($fraudScore < 60) {
                $fraudPenalty = (int) round((100 - $fraudScore) * 0.3);
                $adjustedScore = max(0, $adjustedScore - $fraudPenalty);
                $allFlags[] = '[fraud] Fraud risk detected - score penalty applied';
            }
        }

        // Calculate position optimization if stacking data available
        if ($this->stackingOptimizer && isset($data['requested_amount'])) {
            $stackingData = [
                'monthly_revenue' => $data['monthly_revenue'] ?? 0,
                'requested_amount' => $data['requested_amount'],
                'existing_positions' => $data['existing_positions'] ?? [],
                'bank_analysis' => $data['bank_analysis'] ?? [],
                'risk_score' => $adjustedScore,
            ];
            $positionOptimization = $this->stackingOptimizer->calculateOptimalPosition($stackingData);
            $results['position_optimization'] = $positionOptimization;
        }

        // Get final decision
        $finalDecision = $this->getFinalDecision($adjustedScore, $results);

        // Calculate offer terms if approved or in review
        $offerTerms = null;
        if (in_array($finalDecision['action'], ['APPROVE', 'REVIEW'])) {
            $requestedAmount = $data['requested_amount'] ?? 0;
            if ($requestedAmount > 0) {
                $offerTerms = $this->calculateOfferTerms($adjustedScore, $requestedAmount);

                // Apply term adjustments from rules
                if (isset($results['custom_rules']['term_adjustments'])) {
                    $offerTerms = $this->applyTermAdjustments($offerTerms, $results['custom_rules']['term_adjustments']);
                }

                // Use position optimizer terms if available
                if (isset($results['position_optimization']['optimal_position']['position'])) {
                    $optimizedTerms = $results['position_optimization']['optimal_position']['position'];
                    $offerTerms = array_merge($offerTerms, [
                        'approved_amount' => $optimizedTerms['funding_amount'],
                        'factor_rate' => $optimizedTerms['factor_rate'],
                        'payback_amount' => $optimizedTerms['payback_amount'],
                        'term_months' => $optimizedTerms['term_months'],
                        'daily_payment' => $optimizedTerms['daily_payment'],
                        'holdback_percentage' => $optimizedTerms['holdback_percentage'],
                    ]);
                }
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'overall_score' => $adjustedScore,
            'base_score' => $baseRiskScore['overall_score'],
            'score_adjustments' => $adjustedScore - $baseRiskScore['overall_score'],
            'risk_level' => $this->getOverallRiskLevel($adjustedScore),
            'decision' => $finalDecision,
            'offer_terms' => $offerTerms,
            'flags' => $allFlags,
            'component_scores' => $baseRiskScore['component_scores'],
            'component_details' => $baseRiskScore['component_details'],
            'analysis_results' => $results,
            'required_verifications' => $results['custom_rules']['required_verifications'] ?? [],
            'execution_time_ms' => $executionTime,
            'summary' => $this->generateComprehensiveSummary($adjustedScore, $finalDecision, $allFlags, $results),
        ];
    }

    /**
     * Get final decision considering all factors
     */
    protected function getFinalDecision(int $score, array $results): array
    {
        // Check for blocking conditions
        if ($results['blocked'] ?? false) {
            return [
                'action' => 'DECLINE',
                'type' => 'auto',
                'message' => 'Application blocked: '.($results['block_reason'] ?? 'Policy violation'),
                'reason_code' => 'BLOCKED',
            ];
        }

        // Check for high fraud risk
        if (isset($results['fraud_analysis']['risk_level']) && $results['fraud_analysis']['risk_level'] === 'high') {
            return [
                'action' => 'DECLINE',
                'type' => 'auto',
                'message' => 'High fraud risk detected',
                'reason_code' => 'FRAUD_RISK',
            ];
        }

        // Check for rule-based decisions
        if (! empty($results['custom_rules']['decisions'])) {
            // Get highest priority decision
            $decisions = $results['custom_rules']['decisions'];
            usort($decisions, fn ($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
            $topDecision = $decisions[0];

            if ($topDecision['decision'] === 'DECLINE') {
                return [
                    'action' => 'DECLINE',
                    'type' => 'rule',
                    'message' => $topDecision['reason'],
                    'reason_code' => 'RULE_DECLINE',
                ];
            }
        }

        // Check position viability
        if (isset($results['position_optimization']['optimal_position']['can_fund'])) {
            if (! $results['position_optimization']['optimal_position']['can_fund']) {
                return [
                    'action' => 'DECLINE',
                    'type' => 'capacity',
                    'message' => $results['position_optimization']['optimal_position']['reason'],
                    'reason_code' => 'CAPACITY_EXCEEDED',
                ];
            }
        }

        // Standard score-based decision
        return $this->getDecision($score);
    }

    /**
     * Apply term adjustments from rules
     */
    protected function applyTermAdjustments(array $terms, array $adjustments): array
    {
        foreach ($adjustments as $adjustment) {
            if (isset($adjustment['max_term_months'])) {
                $terms['term_months'] = min($terms['term_months'], $adjustment['max_term_months']);
            }
            if (isset($adjustment['factor_rate_adjustment'])) {
                $terms['factor_rate'] += $adjustment['factor_rate_adjustment'];
            }
            if (isset($adjustment['max_amount_percentage'])) {
                $terms['approved_amount'] *= $adjustment['max_amount_percentage'];
            }
        }

        // Recalculate payback and payments
        $terms['payback_amount'] = round($terms['approved_amount'] * $terms['factor_rate'], 2);
        $terms['daily_payment'] = round($terms['payback_amount'] / ($terms['term_months'] * 22), 2);
        $terms['weekly_payment'] = round($terms['daily_payment'] * 5, 2);

        return $terms;
    }

    /**
     * Generate comprehensive summary
     */
    protected function generateComprehensiveSummary(int $score, array $decision, array $flags, array $results): string
    {
        $riskLevel = $this->getOverallRiskLevel($score);

        $summary = "=== COMPREHENSIVE RISK ASSESSMENT ===\n\n";
        $summary .= "Overall Risk Score: {$score}/100 ({$riskLevel} risk)\n";
        $summary .= "Decision: {$decision['action']} ({$decision['type']})\n";
        $summary .= "Reason: {$decision['message']}\n\n";

        // Fraud analysis summary
        if (isset($results['fraud_analysis'])) {
            $fraudScore = $results['fraud_analysis']['fraud_score'] ?? 'N/A';
            $fraudLevel = $results['fraud_analysis']['risk_level'] ?? 'N/A';
            $summary .= "Fraud Score: {$fraudScore}/100 ({$fraudLevel} risk)\n";
        }

        // Bank analysis summary
        if (isset($results['bank_analysis']['scoring'])) {
            $bankScore = $results['bank_analysis']['scoring']['score'] ?? 'N/A';
            $summary .= "Bank Analysis Score: {$bankScore}/100\n";
        }

        // Position optimization summary
        if (isset($results['position_optimization']['recommendation'])) {
            $posDecision = $results['position_optimization']['recommendation']['decision'] ?? 'N/A';
            $summary .= "Position Recommendation: {$posDecision}\n";
        }

        if (! empty($flags)) {
            $summary .= "\nKey Flags (".count($flags)." total):\n";
            foreach (array_slice($flags, 0, 10) as $flag) {
                $summary .= "- {$flag}\n";
            }
        }

        if (! empty($results['custom_rules']['matched_rules'])) {
            $summary .= "\nMatched Rules:\n";
            foreach ($results['custom_rules']['matched_rules'] as $rule) {
                $summary .= "- {$rule['name']}\n";
            }
        }

        return $summary;
    }

    /**
     * Analyze bank statements from PDF text
     */
    public function analyzeFromBankStatement(string $pdfText, ?string $bankName = null, array $applicationData = []): array
    {
        if (! $this->transactionParser || ! $this->bankAnalyzer) {
            throw new Exception('Transaction parser or bank analyzer not configured');
        }

        // Parse transactions
        $parsed = $this->transactionParser->parseTransactions($pdfText, $bankName);

        if (! $parsed['success'] || empty($parsed['transactions'])) {
            return [
                'success' => false,
                'error' => 'Failed to parse transactions from statement',
                'parsed' => $parsed,
            ];
        }

        // Analyze transactions
        $analysis = $this->bankAnalyzer->analyze($parsed['transactions'], $parsed['metadata'] ?? []);

        // Run fraud detection
        $fraudAnalysis = null;
        if ($this->fraudDetector) {
            $fraudAnalysis = $this->fraudDetector->analyze($parsed['transactions'], $applicationData);
        }

        return [
            'success' => true,
            'bank' => $parsed['bank'],
            'metadata' => $parsed['metadata'],
            'transaction_count' => count($parsed['transactions']),
            'summary' => $parsed['summary'],
            'analysis' => $analysis,
            'fraud_analysis' => $fraudAnalysis,
            'monthly_breakdown' => $this->transactionParser->getMonthlyBreakdown($parsed['transactions']),
        ];
    }

    /**
     * Quick risk check without full analysis
     */
    public function quickRiskCheck(array $data): array
    {
        $score = 50; // Base score
        $flags = [];

        // Quick credit check
        if (isset($data['credit_score'])) {
            if ($data['credit_score'] < 500) {
                $score -= 25;
                $flags[] = 'Very low credit score';
            } elseif ($data['credit_score'] >= 700) {
                $score += 15;
            }
        }

        // Quick stacking check
        if (isset($data['active_mcas'])) {
            if ($data['active_mcas'] >= 4) {
                return [
                    'score' => 0,
                    'decision' => 'DECLINE',
                    'reason' => 'Too many active MCA positions',
                    'flags' => ['Maximum MCA positions exceeded'],
                ];
            }
            $score -= $data['active_mcas'] * 10;
        }

        // Quick revenue check
        if (isset($data['monthly_revenue'])) {
            if ($data['monthly_revenue'] < 10000) {
                $score -= 15;
                $flags[] = 'Low monthly revenue';
            }
        }

        // Time in business
        if (isset($data['time_in_business_months'])) {
            if ($data['time_in_business_months'] < 6) {
                $score -= 20;
                $flags[] = 'Less than 6 months in business';
            }
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'risk_level' => $this->getOverallRiskLevel($score),
            'decision' => $score >= $this->thresholds['auto_decline'] ? 'CONTINUE' : 'DECLINE',
            'flags' => $flags,
            'message' => $score >= $this->thresholds['auto_decline']
                ? 'Preliminary check passed - proceed with full analysis'
                : 'Application does not meet minimum requirements',
        ];
    }
}
