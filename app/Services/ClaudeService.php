<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $apiVersion;

    protected string $defaultModel;

    protected int $maxTokens;

    protected int $timeout;

    protected bool $enabled = false;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load configuration from database with fallback to config files
     */
    protected function loadConfiguration(): void
    {
        $dbSetting = IntegrationSetting::where('integration', 'claude')->first();

        if ($dbSetting && $dbSetting->isConfigured()) {
            $this->enabled = $dbSetting->enabled;
            $this->apiKey = $dbSetting->getCredential('api_key', '');
            $this->baseUrl = $dbSetting->getSetting('base_url', 'https://api.anthropic.com');
            $this->apiVersion = $dbSetting->getSetting('api_version', '2023-06-01');
            $this->defaultModel = $dbSetting->getSetting('default_model', 'claude-sonnet-4-20250514');
            $this->maxTokens = (int) $dbSetting->getSetting('max_tokens', 4096);
            $this->timeout = (int) $dbSetting->getSetting('timeout', 60);
        } else {
            $this->apiKey = config('claude.api_key', '');
            $this->enabled = ! empty($this->apiKey) && $this->apiKey !== 'your_anthropic_api_key_here';
            $this->baseUrl = config('claude.base_url', 'https://api.anthropic.com');
            $this->apiVersion = config('claude.api_version', '2023-06-01');
            $this->defaultModel = config('claude.default_model', 'claude-sonnet-4-20250514');
            $this->maxTokens = (int) config('claude.max_tokens', 4096);
            $this->timeout = (int) config('claude.timeout', 60);
        }
    }

    /**
     * Check if the service is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send a message to Claude and get a response
     *
     * @param  string|array  $messages  Single message string or array of message objects
     * @param  array  $options  Additional options (model, max_tokens, temperature, system)
     * @return array Response data
     *
     * @throws Exception
     */
    public function chat(string|array $messages, array $options = []): array
    {
        if (! $this->isEnabled()) {
            throw new Exception('Claude API is not configured. Please set ANTHROPIC_API_KEY in your .env file.');
        }

        // Convert simple string to messages array
        if (is_string($messages)) {
            $messages = [
                ['role' => 'user', 'content' => $messages],
            ];
        }

        $model = $options['model'] ?? $this->defaultModel;
        $maxTokens = $options['max_tokens'] ?? $this->maxTokens;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        // Add optional parameters
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['stop_sequences'])) {
            $payload['stop_sequences'] = $options['stop_sequences'];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
            ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/v1/messages", $payload);

            if ($response->failed()) {
                $error = $response->json();
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);
                throw new Exception($error['error']['message'] ?? 'Claude API request failed');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Claude API exception', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Simple helper to get just the text response
     *
     * @param  string  $prompt  The user's prompt
     * @param  array  $options  Additional options
     * @return string The assistant's response text
     */
    public function ask(string $prompt, array $options = []): string
    {
        $response = $this->chat($prompt, $options);

        return $this->extractText($response);
    }

    /**
     * Chat with a system prompt
     *
     * @param  string  $systemPrompt  Instructions for Claude's behavior
     * @param  string  $userMessage  The user's message
     * @param  array  $options  Additional options
     * @return string The assistant's response text
     */
    public function askWithSystem(string $systemPrompt, string $userMessage, array $options = []): string
    {
        $options['system'] = $systemPrompt;

        return $this->ask($userMessage, $options);
    }

    /**
     * Analyze text content (useful for bank statements, documents, etc.)
     *
     * @param  string  $content  The content to analyze
     * @param  string  $instruction  What to do with the content
     * @param  array  $options  Additional options
     * @return string Analysis result
     */
    public function analyze(string $content, string $instruction, array $options = []): string
    {
        $prompt = "{$instruction}\n\nContent:\n{$content}";

        return $this->ask($prompt, $options);
    }

    /**
     * Extract structured data from text (returns JSON)
     *
     * @param  string  $content  The content to extract from
     * @param  string  $schema  Description of what to extract
     * @param  array  $options  Additional options
     * @return array Parsed JSON response
     */
    public function extractJson(string $content, string $schema, array $options = []): array
    {
        $systemPrompt = 'You are a data extraction assistant. Always respond with valid JSON only, no additional text or markdown formatting.';

        $prompt = "Extract the following information from the content and return as JSON:\n\nSchema: {$schema}\n\nContent:\n{$content}";

        $options['system'] = $systemPrompt;
        $response = $this->ask($prompt, $options);

        // Clean up response if it has markdown code blocks
        $response = preg_replace('/^```json\s*/', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);

        return json_decode($response, true) ?? [];
    }

    /**
     * Extract text content from Claude's response
     */
    protected function extractText(array $response): string
    {
        if (! isset($response['content']) || empty($response['content'])) {
            return '';
        }

        $text = '';
        foreach ($response['content'] as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    /**
     * Get available models
     */
    public function getModels(): array
    {
        return config('claude.models', []);
    }

    /**
     * Get usage information from the last response
     */
    public function getUsage(array $response): array
    {
        return $response['usage'] ?? [
            'input_tokens' => 0,
            'output_tokens' => 0,
        ];
    }
}
