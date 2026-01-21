<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomRiskRulesEngine
{
    protected array $operators = [
        'eq' => '=',
        'neq' => '!=',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'in' => 'IN',
        'not_in' => 'NOT IN',
        'contains' => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'between' => 'BETWEEN',
        'is_null' => 'IS NULL',
        'is_not_null' => 'IS NOT NULL',
    ];

    protected array $actions = [
        'adjust_score' => 'Adjust risk score by amount',
        'set_score' => 'Set risk score to specific value',
        'multiply_score' => 'Multiply risk score by factor',
        'add_flag' => 'Add a risk flag',
        'set_decision' => 'Set decision to approve/decline/review',
        'require_verification' => 'Require additional verification',
        'adjust_terms' => 'Adjust offer terms',
        'block' => 'Block application',
    ];

    /**
     * Evaluate all active rules against application data
     */
    public function evaluate(array $data): array
    {
        $rules = $this->getActiveRules();
        $results = [
            'score_adjustments' => [],
            'flags' => [],
            'decisions' => [],
            'term_adjustments' => [],
            'required_verifications' => [],
            'blocked' => false,
            'block_reason' => null,
            'matched_rules' => [],
        ];

        foreach ($rules as $rule) {
            $matched = $this->evaluateRule($rule, $data);

            if ($matched) {
                $results['matched_rules'][] = [
                    'id' => $rule['id'],
                    'name' => $rule['name'],
                    'action' => $rule['action'],
                ];

                $this->applyRuleAction($rule, $data, $results);
            }
        }

        // Calculate final score adjustment
        $results['total_score_adjustment'] = array_sum($results['score_adjustments']);

        return $results;
    }

    /**
     * Evaluate a single rule
     */
    protected function evaluateRule(array $rule, array $data): bool
    {
        $conditions = $rule['conditions'] ?? [];

        if (empty($conditions)) {
            return false;
        }

        $logicOperator = $rule['logic'] ?? 'AND';

        if ($logicOperator === 'AND') {
            foreach ($conditions as $condition) {
                if (! $this->evaluateCondition($condition, $data)) {
                    return false;
                }
            }

            return true;
        }

        // OR logic
        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($condition, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? null;

        if (! $field) {
            return false;
        }

        // Get the field value from nested data using dot notation
        $fieldValue = $this->getNestedValue($data, $field);

        return match ($operator) {
            'eq' => $fieldValue == $value,
            'neq' => $fieldValue != $value,
            'gt' => is_numeric($fieldValue) && $fieldValue > $value,
            'gte' => is_numeric($fieldValue) && $fieldValue >= $value,
            'lt' => is_numeric($fieldValue) && $fieldValue < $value,
            'lte' => is_numeric($fieldValue) && $fieldValue <= $value,
            'in' => is_array($value) && in_array($fieldValue, $value),
            'not_in' => is_array($value) && ! in_array($fieldValue, $value),
            'contains' => is_string($fieldValue) && str_contains(strtolower($fieldValue), strtolower($value)),
            'not_contains' => is_string($fieldValue) && ! str_contains(strtolower($fieldValue), strtolower($value)),
            'between' => is_array($value) && count($value) === 2 && $fieldValue >= $value[0] && $fieldValue <= $value[1],
            'is_null' => is_null($fieldValue),
            'is_not_null' => ! is_null($fieldValue),
            'regex' => is_string($fieldValue) && preg_match($value, $fieldValue),
            default => false,
        };
    }

    /**
     * Apply rule action to results
     */
    protected function applyRuleAction(array $rule, array $data, array &$results): void
    {
        $action = $rule['action'] ?? null;
        $actionValue = $rule['action_value'] ?? null;

        switch ($action) {
            case 'adjust_score':
                $results['score_adjustments'][] = (int) $actionValue;
                break;

            case 'set_score':
                $results['score_adjustments'] = [(int) $actionValue - 50]; // Relative to base
                break;

            case 'multiply_score':
                // Store as a multiplier to be applied later
                $results['score_multiplier'] = ($results['score_multiplier'] ?? 1.0) * (float) $actionValue;
                break;

            case 'add_flag':
                $results['flags'][] = [
                    'type' => $rule['name'],
                    'message' => $actionValue,
                    'severity' => $rule['severity'] ?? 'medium',
                    'rule_id' => $rule['id'],
                ];
                break;

            case 'set_decision':
                $results['decisions'][] = [
                    'decision' => strtoupper($actionValue),
                    'reason' => $rule['name'],
                    'rule_id' => $rule['id'],
                    'priority' => $rule['priority'] ?? 0,
                ];
                break;

            case 'require_verification':
                $results['required_verifications'][] = [
                    'type' => $actionValue,
                    'reason' => $rule['name'],
                    'rule_id' => $rule['id'],
                ];
                break;

            case 'adjust_terms':
                $termAdjustments = is_array($actionValue) ? $actionValue : json_decode($actionValue, true);
                if ($termAdjustments) {
                    $results['term_adjustments'][] = $termAdjustments;
                }
                break;

            case 'block':
                $results['blocked'] = true;
                $results['block_reason'] = $actionValue ?? $rule['name'];
                break;
        }
    }

    /**
     * Get nested value from array using dot notation
     */
    protected function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get all active rules from database
     */
    public function getActiveRules(): array
    {
        return Cache::remember('risk_rules_active', 300, function () {
            try {
                return DB::table('risk_rules')
                    ->where('is_active', true)
                    ->orderBy('priority', 'desc')
                    ->get()
                    ->map(function ($rule) {
                        $rule->conditions = json_decode($rule->conditions, true) ?? [];

                        return (array) $rule;
                    })
                    ->toArray();
            } catch (\Exception $e) {
                Log::warning('Could not load risk rules from database: '.$e->getMessage());

                return $this->getDefaultRules();
            }
        });
    }

    /**
     * Create a new rule
     */
    public function createRule(array $ruleData): array
    {
        $rule = [
            'name' => $ruleData['name'],
            'description' => $ruleData['description'] ?? null,
            'conditions' => json_encode($ruleData['conditions'] ?? []),
            'logic' => $ruleData['logic'] ?? 'AND',
            'action' => $ruleData['action'],
            'action_value' => is_array($ruleData['action_value'] ?? null)
                ? json_encode($ruleData['action_value'])
                : $ruleData['action_value'],
            'severity' => $ruleData['severity'] ?? 'medium',
            'priority' => $ruleData['priority'] ?? 0,
            'is_active' => $ruleData['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            $id = DB::table('risk_rules')->insertGetId($rule);
            Cache::forget('risk_rules_active');
            $rule['id'] = $id;

            return ['success' => true, 'rule' => $rule];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an existing rule
     */
    public function updateRule(int $id, array $ruleData): array
    {
        $updates = [];

        if (isset($ruleData['name'])) {
            $updates['name'] = $ruleData['name'];
        }
        if (isset($ruleData['description'])) {
            $updates['description'] = $ruleData['description'];
        }
        if (isset($ruleData['conditions'])) {
            $updates['conditions'] = json_encode($ruleData['conditions']);
        }
        if (isset($ruleData['logic'])) {
            $updates['logic'] = $ruleData['logic'];
        }
        if (isset($ruleData['action'])) {
            $updates['action'] = $ruleData['action'];
        }
        if (isset($ruleData['action_value'])) {
            $updates['action_value'] = is_array($ruleData['action_value'])
                ? json_encode($ruleData['action_value'])
                : $ruleData['action_value'];
        }
        if (isset($ruleData['severity'])) {
            $updates['severity'] = $ruleData['severity'];
        }
        if (isset($ruleData['priority'])) {
            $updates['priority'] = $ruleData['priority'];
        }
        if (isset($ruleData['is_active'])) {
            $updates['is_active'] = $ruleData['is_active'];
        }

        $updates['updated_at'] = now();

        try {
            DB::table('risk_rules')->where('id', $id)->update($updates);
            Cache::forget('risk_rules_active');

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a rule
     */
    public function deleteRule(int $id): array
    {
        try {
            DB::table('risk_rules')->where('id', $id)->delete();
            Cache::forget('risk_rules_active');

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Toggle rule active status
     */
    public function toggleRule(int $id): array
    {
        try {
            $rule = DB::table('risk_rules')->where('id', $id)->first();
            if (! $rule) {
                return ['success' => false, 'error' => 'Rule not found'];
            }

            DB::table('risk_rules')->where('id', $id)->update([
                'is_active' => ! $rule->is_active,
                'updated_at' => now(),
            ]);

            Cache::forget('risk_rules_active');

            return ['success' => true, 'is_active' => ! $rule->is_active];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test a rule against sample data
     */
    public function testRule(array $rule, array $testData): array
    {
        $matched = $this->evaluateRule($rule, $testData);

        $results = [
            'score_adjustments' => [],
            'flags' => [],
            'decisions' => [],
            'term_adjustments' => [],
            'required_verifications' => [],
            'blocked' => false,
            'block_reason' => null,
            'matched_rules' => [],
        ];

        if ($matched) {
            $this->applyRuleAction($rule, $testData, $results);
        }

        return [
            'matched' => $matched,
            'results' => $results,
            'evaluated_conditions' => $this->getConditionResults($rule, $testData),
        ];
    }

    /**
     * Get individual condition evaluation results
     */
    protected function getConditionResults(array $rule, array $data): array
    {
        $results = [];

        foreach ($rule['conditions'] ?? [] as $condition) {
            $field = $condition['field'] ?? '';
            $fieldValue = $this->getNestedValue($data, $field);
            $matched = $this->evaluateCondition($condition, $data);

            $results[] = [
                'field' => $field,
                'operator' => $condition['operator'] ?? 'eq',
                'expected' => $condition['value'] ?? null,
                'actual' => $fieldValue,
                'matched' => $matched,
            ];
        }

        return $results;
    }

    /**
     * Get default rules (fallback when database not available)
     */
    public function getDefaultRules(): array
    {
        return [
            // Credit score rules
            [
                'id' => 'default_1',
                'name' => 'Very Low Credit Score',
                'conditions' => [
                    ['field' => 'credit.credit_score', 'operator' => 'lt', 'value' => 500],
                ],
                'logic' => 'AND',
                'action' => 'adjust_score',
                'action_value' => -30,
                'severity' => 'high',
                'priority' => 100,
            ],
            [
                'id' => 'default_2',
                'name' => 'Excellent Credit Score',
                'conditions' => [
                    ['field' => 'credit.credit_score', 'operator' => 'gte', 'value' => 750],
                ],
                'logic' => 'AND',
                'action' => 'adjust_score',
                'action_value' => 15,
                'severity' => 'low',
                'priority' => 90,
            ],

            // Stacking rules
            [
                'id' => 'default_3',
                'name' => 'High Stacking - 4+ Positions',
                'conditions' => [
                    ['field' => 'stacking.active_mcas', 'operator' => 'gte', 'value' => 4],
                ],
                'logic' => 'AND',
                'action' => 'set_decision',
                'action_value' => 'decline',
                'severity' => 'high',
                'priority' => 200,
            ],
            [
                'id' => 'default_4',
                'name' => 'Multiple Active MCAs',
                'conditions' => [
                    ['field' => 'stacking.active_mcas', 'operator' => 'between', 'value' => [2, 3]],
                ],
                'logic' => 'AND',
                'action' => 'add_flag',
                'action_value' => 'Multiple active MCA positions detected',
                'severity' => 'medium',
                'priority' => 80,
            ],

            // NSF rules
            [
                'id' => 'default_5',
                'name' => 'High NSF Frequency',
                'conditions' => [
                    ['field' => 'bank_analysis.risk_indicators.nsf_frequency.total_count', 'operator' => 'gte', 'value' => 5],
                ],
                'logic' => 'AND',
                'action' => 'adjust_score',
                'action_value' => -20,
                'severity' => 'high',
                'priority' => 85,
            ],

            // Revenue decline
            [
                'id' => 'default_6',
                'name' => 'Severe Revenue Decline',
                'conditions' => [
                    ['field' => 'bank_analysis.trend_analysis.revenue_trend.percentage', 'operator' => 'lt', 'value' => -30],
                ],
                'logic' => 'AND',
                'action' => 'add_flag',
                'action_value' => 'Severe revenue decline detected (>30%)',
                'severity' => 'high',
                'priority' => 90,
            ],

            // Industry risk
            [
                'id' => 'default_7',
                'name' => 'High Risk Industry',
                'conditions' => [
                    ['field' => 'industry', 'operator' => 'in', 'value' => ['gambling', 'cannabis', 'adult entertainment', 'cryptocurrency']],
                ],
                'logic' => 'AND',
                'action' => 'require_verification',
                'action_value' => 'enhanced_due_diligence',
                'severity' => 'high',
                'priority' => 95,
            ],

            // Fraud detection
            [
                'id' => 'default_8',
                'name' => 'High Fraud Risk',
                'conditions' => [
                    ['field' => 'fraud_analysis.fraud_score', 'operator' => 'lt', 'value' => 50],
                ],
                'logic' => 'AND',
                'action' => 'set_decision',
                'action_value' => 'decline',
                'severity' => 'high',
                'priority' => 250,
            ],

            // Time in business
            [
                'id' => 'default_9',
                'name' => 'New Business - Less than 6 months',
                'conditions' => [
                    ['field' => 'time_in_business_months', 'operator' => 'lt', 'value' => 6],
                ],
                'logic' => 'AND',
                'action' => 'adjust_score',
                'action_value' => -15,
                'severity' => 'medium',
                'priority' => 70,
            ],

            // Low revenue
            [
                'id' => 'default_10',
                'name' => 'Very Low Monthly Revenue',
                'conditions' => [
                    ['field' => 'monthly_revenue', 'operator' => 'lt', 'value' => 10000],
                ],
                'logic' => 'AND',
                'action' => 'adjust_terms',
                'action_value' => ['max_term_months' => 6, 'factor_rate_adjustment' => 0.05],
                'severity' => 'medium',
                'priority' => 60,
            ],
        ];
    }

    /**
     * Get available fields for rule building
     */
    public function getAvailableFields(): array
    {
        return [
            'Application Data' => [
                'monthly_revenue' => 'Monthly Revenue',
                'requested_amount' => 'Requested Funding Amount',
                'time_in_business_months' => 'Time in Business (months)',
                'industry' => 'Industry',
                'state' => 'Business State',
                'business_type' => 'Business Type',
            ],
            'Credit Data' => [
                'credit.credit_score' => 'Credit Score',
                'credit.bankruptcies' => 'Bankruptcy Count',
                'credit.delinquencies' => 'Delinquency Count',
                'credit.collections' => 'Collections Count',
            ],
            'Bank Analysis' => [
                'bank_analysis.scoring.score' => 'Bank Analysis Score',
                'bank_analysis.revenue_analysis.average_monthly' => 'Average Monthly Revenue',
                'bank_analysis.revenue_analysis.consistency_score' => 'Revenue Consistency Score',
                'bank_analysis.cash_flow_analysis.negative_months' => 'Negative Cash Flow Months',
                'bank_analysis.balance_analysis.average_daily_balance' => 'Average Daily Balance',
                'bank_analysis.risk_indicators.nsf_frequency.total_count' => 'NSF Count',
                'bank_analysis.trend_analysis.revenue_trend.percentage' => 'Revenue Trend %',
                'bank_analysis.mca_exposure.estimated_active_funders' => 'Estimated Active Funders',
            ],
            'Stacking Data' => [
                'stacking.active_mcas' => 'Active MCA Count',
                'stacking.total_exposure' => 'Total MCA Exposure',
                'stacking.has_defaults' => 'Has MCA Defaults',
            ],
            'Identity Verification' => [
                'identity.score' => 'Identity Verification Score',
                'identity.status' => 'Identity Status',
            ],
            'Fraud Analysis' => [
                'fraud_analysis.fraud_score' => 'Fraud Score',
                'fraud_analysis.flag_count.high' => 'High Severity Fraud Flags',
            ],
            'UCC Data' => [
                'ucc.active_filings' => 'Active UCC Filings',
                'ucc.mca_filings' => 'MCA-Related Filings',
                'ucc.has_blanket_lien' => 'Has Blanket Lien',
            ],
        ];
    }

    /**
     * Get available operators
     */
    public function getAvailableOperators(): array
    {
        return [
            'eq' => 'Equals',
            'neq' => 'Not Equals',
            'gt' => 'Greater Than',
            'gte' => 'Greater Than or Equal',
            'lt' => 'Less Than',
            'lte' => 'Less Than or Equal',
            'in' => 'Is One Of',
            'not_in' => 'Is Not One Of',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'between' => 'Between',
            'is_null' => 'Is Empty',
            'is_not_null' => 'Is Not Empty',
        ];
    }

    /**
     * Get available actions
     */
    public function getAvailableActions(): array
    {
        return $this->actions;
    }

    /**
     * Validate rule structure
     */
    public function validateRule(array $rule): array
    {
        $errors = [];

        if (empty($rule['name'])) {
            $errors[] = 'Rule name is required';
        }

        if (empty($rule['conditions']) || ! is_array($rule['conditions'])) {
            $errors[] = 'At least one condition is required';
        } else {
            foreach ($rule['conditions'] as $i => $condition) {
                if (empty($condition['field'])) {
                    $errors[] = 'Condition '.($i + 1).': Field is required';
                }
                if (empty($condition['operator'])) {
                    $errors[] = 'Condition '.($i + 1).': Operator is required';
                }
                if (! isset($condition['value']) && ! in_array($condition['operator'] ?? '', ['is_null', 'is_not_null'])) {
                    $errors[] = 'Condition '.($i + 1).': Value is required';
                }
            }
        }

        if (empty($rule['action'])) {
            $errors[] = 'Action is required';
        } elseif (! array_key_exists($rule['action'], $this->actions)) {
            $errors[] = 'Invalid action type';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
