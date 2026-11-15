<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI-compatible chat client for free-tier providers.
 * Tries each Groq model in order, then Hugging Face Inference (if a token is set).
 */
class LlmClient
{
    /**
     * @return array{ok: bool, message?: array, provider?: string, model?: string, error?: string}
     */
    public function chat(array $messages, array $tools = [], float $temperature = 0.7): array
    {
        $attempts = [];
        $groq = config('services.groq');
        if (! empty($groq['key'])) {
            foreach ($groq['models'] as $model) {
                $attempts[] = ['groq', $groq['url'], $groq['key'], $model];
            }
        }
        $hf = config('services.huggingface');
        if (! empty($hf['token'])) {
            $attempts[] = ['huggingface', $hf['url'], $hf['token'], $hf['model']];
        }
        if (! $attempts) {
            return ['ok' => false, 'error' => 'No AI provider is configured. Set GROQ_API_KEY (or HF_TOKEN) in the .env file.'];
        }

        $lastError = 'Unknown error';
        $skipProvider = null;
        foreach ($attempts as [$provider, $url, $key, $model]) {
            if ($provider === $skipProvider) {
                continue;
            }
            $body = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 450,
            ];
            if ($tools) {
                $body['tools'] = $tools;
                $body['tool_choice'] = 'auto';
            }
            if ($provider === 'groq' && str_starts_with($model, 'openai/gpt-oss')) {
                $body['reasoning_effort'] = 'low'; // fewer tokens, faster replies, stays inside free-tier limits
            }

            try {
                $response = Http::withToken($key)
                    ->withOptions(['verify' => $this->caBundle()])
                    ->connectTimeout(10)
                    ->timeout(45)
                    ->post($url, $body);
            } catch (\Throwable $e) {
                $lastError = "Could not reach $provider: " . $e->getMessage();
                Log::warning('[Athena] ' . $lastError);
                $skipProvider = $provider; // network problem: other models of this provider won't help
                continue;
            }

            // Free tier: "Please try again in 5.5s" -> wait once and retry the same model
            if ($response->status() === 429 && ! ($waited ?? false)) {
                $wait = (float) ($response->header('retry-after') ?: 0);
                if (! $wait && preg_match('/try again in ([\d.]+)s/', (string) $response->json('error.message'), $m)) {
                    $wait = (float) $m[1];
                }
                if ($wait > 0 && $wait <= 20) {
                    $waited = true;
                    usleep((int) (($wait + 0.5) * 1_000_000));
                    try {
                        $response = Http::withToken($key)->withOptions(['verify' => $this->caBundle()])
                            ->connectTimeout(10)->timeout(45)->post($url, $body);
                    } catch (\Throwable $e) {
                        $lastError = "Could not reach $provider: " . $e->getMessage();
                        continue;
                    }
                }
            }

            if ($response->successful() && is_array($response->json('choices.0.message'))) {
                return ['ok' => true, 'message' => $response->json('choices.0.message'), 'provider' => $provider, 'model' => $model];
            }

            $status = $response->status();
            $lastError = "$provider/$model: " . ($response->json('error.message') ?? "HTTP $status");
            Log::warning('[Athena] ' . $lastError);
            if (in_array($status, [401, 403], true)) {
                $skipProvider = $provider; // bad key: skip the rest of this provider
            }
            // 400 (bad tool call), 404 (model retired), 413, 429 (rate limit), 5xx: try the next model
        }

        return ['ok' => false, 'error' => $lastError];
    }

    /** XAMPP on Windows often has no CA bundle configured, so ship one. */
    private function caBundle(): string|bool
    {
        $path = resource_path('certs/cacert.pem');
        return is_file($path) ? $path : true;
    }
}
