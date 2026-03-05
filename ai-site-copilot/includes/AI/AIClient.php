<?php
namespace AISC\AI;

use WP_Error;

if (!defined('ABSPATH')) exit;

class AIClient {

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model) {
        $this->apiKey = trim($apiKey);
        $this->model  = $model ?: 'gpt-4.1-mini';
    }

    public function is_ready(): bool {
        return strlen($this->apiKey) > 20;
    }

    /**
     * Calls OpenAI Responses-like endpoint.
     * Returns:
     * [
     *   'ok' => bool,
     *   'text' => string,
     *   'tokens' => int,
     *   'raw' => array,
     *   'error' => string
     * ]
     */
    public function respond(string $input, array $opts = []): array {
        if (!$this->is_ready()) {
            return ['ok' => false, 'text' => '', 'tokens' => 0, 'raw' => [], 'error' => 'API key missing'];
        }

        $endpoint = $opts['endpoint'] ?? 'https://api.openai.com/v1/responses';
        $timeout  = (int) ($opts['timeout'] ?? 25);
        $retries  = (int) ($opts['retries'] ?? 2);

        $payload = [
            'model' => $this->model,
            'input' => $input,
        ];

        // Optional controls
        if (isset($opts['temperature'])) $payload['temperature'] = (float) $opts['temperature'];
        if (isset($opts['max_output_tokens'])) $payload['max_output_tokens'] = (int) $opts['max_output_tokens'];

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];

        $lastErr = '';
        for ($i = 0; $i <= $retries; $i++) {

            $res = wp_remote_post($endpoint, [
                'headers' => $headers,
                'timeout' => $timeout,
                'body'    => wp_json_encode($payload),
            ]);

            if (is_wp_error($res)) {
                /** @var WP_Error $res */
                $lastErr = $res->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($res);
            $body = (string) wp_remote_retrieve_body($res);
            $json = json_decode($body, true);

            if ($code < 200 || $code >= 300) {
                $msg = '';
                if (is_array($json) && isset($json['error']['message'])) $msg = (string) $json['error']['message'];
                $lastErr = $msg ?: ('HTTP ' . $code);
                continue;
            }

            $text = $this->extract_text($json);
            $tokens = $this->extract_tokens($json);

            return [
                'ok' => true,
                'text' => $text,
                'tokens' => $tokens,
                'raw' => is_array($json) ? $json : [],
                'error' => '',
            ];
        }

        return ['ok' => false, 'text' => '', 'tokens' => 0, 'raw' => [], 'error' => $lastErr ?: 'Unknown error'];
    }

    private function extract_text($json): string {
        if (!is_array($json)) return '';

        // Common Responses API shapes
        if (!empty($json['output_text']) && is_string($json['output_text'])) {
            return trim($json['output_text']);
        }

        // Fallback: try to locate text in output array
        if (!empty($json['output']) && is_array($json['output'])) {
            foreach ($json['output'] as $item) {
                if (!is_array($item)) continue;
                if (!empty($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $c) {
                        if (is_array($c) && ($c['type'] ?? '') === 'output_text' && !empty($c['text'])) {
                            return trim((string)$c['text']);
                        }
                    }
                }
            }
        }

        // Chat-completions style fallback
        if (!empty($json['choices'][0]['message']['content'])) {
            return trim((string) $json['choices'][0]['message']['content']);
        }

        return '';
    }

    private function extract_tokens($json): int {
        if (!is_array($json)) return 0;

        if (isset($json['usage']['total_tokens'])) return (int) $json['usage']['total_tokens'];
        if (isset($json['usage']['input_tokens']) || isset($json['usage']['output_tokens'])) {
            return (int) (($json['usage']['input_tokens'] ?? 0) + ($json['usage']['output_tokens'] ?? 0));
        }
        return 0;
    }
}