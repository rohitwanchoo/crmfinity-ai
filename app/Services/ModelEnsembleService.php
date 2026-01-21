<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Model Ensemble Service for transaction classification
 * Uses multiple AI models (Claude + GPT-4) with voting for higher accuracy
 * Part of PR3: Advanced Features - Model Ensemble System
 */
class ModelEnsembleService
{
    private string $claudeApiKey;
    private string $openaiApiKey;

    public function __construct()
    {
        $this->claudeApiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        $this->openaiApiKey = config('services.openai.api_key', env('OPENAI_API_KEY', ''));
    }

    /**
     * Classify transactions using ensemble voting
     *
     * @param array $transactions Transactions to classify
     * @param string $bankContext Bank context information
     * @return array Transactions with ensemble-verified classifications
     */
    public function classifyWithEnsemble(array $transactions, string $bankContext = ''): array
    {
        if (empty($transactions)) {
            return [];
        }

        // Only use ensemble for low/medium confidence transactions
        $needsVerification = [];
        $highConfidence = [];

        foreach ($transactions as $idx => $txn) {
            $confidence = $txn['confidence'] ?? 'medium';
            if ($confidence === 'high' && ($txn['verified'] ?? false)) {
                $highConfidence[$idx] = $txn;
            } else {
                $needsVerification[$idx] = $txn;
            }
        }

        if (empty($needsVerification)) {
            return $transactions;
        }

        Log::info("ModelEnsemble: Verifying " . count($needsVerification) . " transactions");

        // Get classifications from both models
        $claudeClassifications = $this->getClaudeClassifications($needsVerification, $bankContext);
        $gptClassifications = $this->getGPTClassifications($needsVerification, $bankContext);

        // Combine with voting
        $verified = $this->voteOnClassifications($needsVerification, $claudeClassifications, $gptClassifications);

        // Merge back
        $result = [];
        foreach ($transactions as $idx => $txn) {
            if (isset($verified[$idx])) {
                $result[] = $verified[$idx];
            } else {
                $result[] = $txn;
            }
        }

        return $result;
    }

    /**
     * Get classifications from Claude
     */
    private function getClaudeClassifications(array $transactions, string $bankContext): array
    {
        if (empty($this->claudeApiKey)) {
            return [];
        }

        $txnList = [];
        foreach ($transactions as $idx => $txn) {
            $txnList[] = [
                'id' => $idx,
                'description' => $txn['description'] ?? '',
                'amount' => $txn['amount'] ?? 0,
                'date' => $txn['date'] ?? '',
            ];
        }

        $prompt = $this->buildClassificationPrompt($txnList, $bankContext);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $this->claudeApiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-sonnet-latest',
                    'max_tokens' => 4096,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]);

            if ($response->successful()) {
                $content = $response->json()['content'][0]['text'] ?? '';
                return $this->parseClassificationResponse($content);
            }
        } catch (\Exception $e) {
            Log::warning("ModelEnsemble: Claude classification failed - " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get classifications from GPT-4
     */
    private function getGPTClassifications(array $transactions, string $bankContext): array
    {
        if (empty($this->openaiApiKey)) {
            return [];
        }

        $txnList = [];
        foreach ($transactions as $idx => $txn) {
            $txnList[] = [
                'id' => $idx,
                'description' => $txn['description'] ?? '',
                'amount' => $txn['amount'] ?? 0,
                'date' => $txn['date'] ?? '',
            ];
        }

        $prompt = $this->buildClassificationPrompt($txnList, $bankContext);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o',
                    'max_tokens' => 4096,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                return $this->parseClassificationResponse($content);
            }
        } catch (\Exception $e) {
            Log::warning("ModelEnsemble: GPT classification failed - " . $e->getMessage());
        }

        return [];
    }

    /**
     * Build classification prompt for ensemble models
     */
    private function buildClassificationPrompt(array $transactions, string $bankContext): string
    {
        $txnJson = json_encode($transactions, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a bank statement transaction classifier. For each transaction, determine if it is a CREDIT (money IN) or DEBIT (money OUT).

CLASSIFICATION RULES:
- CREDIT: Deposits, refunds, incoming transfers, payments received, "FROM" keyword
- DEBIT: Purchases, withdrawals, payments sent, fees, "TO" keyword, checks

Bank Context: {$bankContext}

TRANSACTIONS TO CLASSIFY:
{$txnJson}

RESPOND WITH ONLY JSON in this exact format (no other text):
{
  "classifications": [
    {"id": 0, "type": "credit", "confidence": "high", "reason": "direct deposit"},
    {"id": 1, "type": "debit", "confidence": "high", "reason": "purchase at store"}
  ]
}
PROMPT;
    }

    /**
     * Parse classification response from AI model
     */
    private function parseClassificationResponse(string $content): array
    {
        // Extract JSON from response
        $content = trim($content);

        // Try to find JSON in response
        if (preg_match('/\{[\s\S]*"classifications"[\s\S]*\}/m', $content, $matches)) {
            $content = $matches[0];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['classifications'])) {
            return [];
        }

        $result = [];
        foreach ($data['classifications'] as $item) {
            if (isset($item['id'], $item['type'])) {
                $result[$item['id']] = [
                    'type' => strtolower($item['type']),
                    'confidence' => $item['confidence'] ?? 'medium',
                    'reason' => $item['reason'] ?? '',
                ];
            }
        }

        return $result;
    }

    /**
     * Vote on classifications from multiple models
     */
    private function voteOnClassifications(array $transactions, array $claude, array $gpt): array
    {
        $result = [];

        foreach ($transactions as $idx => $txn) {
            $originalType = $txn['type'] ?? 'debit';
            $claudeType = $claude[$idx]['type'] ?? null;
            $gptType = $gpt[$idx]['type'] ?? null;

            // Count votes
            $votes = ['credit' => 0, 'debit' => 0];
            $votes[$originalType]++;

            if ($claudeType) {
                $votes[$claudeType]++;
            }
            if ($gptType) {
                $votes[$gptType]++;
            }

            // Determine winner
            $finalType = $votes['credit'] > $votes['debit'] ? 'credit' : 'debit';

            // Calculate confidence based on agreement
            $totalVotes = ($claudeType ? 1 : 0) + ($gptType ? 1 : 0) + 1;
            $winnerVotes = max($votes['credit'], $votes['debit']);

            if ($winnerVotes === $totalVotes) {
                $confidence = 'high';
            } elseif ($winnerVotes >= 2) {
                $confidence = 'medium';
            } else {
                $confidence = 'low';
            }

            $result[$idx] = array_merge($txn, [
                'type' => $finalType,
                'confidence' => $confidence,
                'ensemble_verified' => true,
                'vote_details' => [
                    'original' => $originalType,
                    'claude' => $claudeType,
                    'gpt' => $gptType,
                    'final' => $finalType,
                ],
            ]);

            // Log disagreements for analysis
            if ($finalType !== $originalType) {
                Log::info("ModelEnsemble: Classification changed for '{$txn['description']}': {$originalType} → {$finalType}");
            }
        }

        return $result;
    }

    /**
     * Quick classification check for a single transaction
     * Used for real-time verification during user corrections
     */
    public function verifySingleTransaction(array $transaction, string $bankContext = ''): array
    {
        $result = $this->classifyWithEnsemble([$transaction], $bankContext);
        return $result[0] ?? $transaction;
    }
}
