<?php

use App\Console\Commands\CleanKnowledgeBase;
use App\Ai\Agents\ChatAgent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('knowledge:clean', function () {
    return app(CleanKnowledgeBase::class)->handle();
})->purpose('Trim oversized knowledge rows to keep Ollama prompts safe');

Artisan::command('ai:compare {prompt?}', function (?string $prompt = null) {
    $prompt ??= 'Jelaskan dalam tiga poin singkat mengapa pelayanan publik perlu memiliki informasi yang akurat.';

    $providers = [
        'Gemini' => ['provider' => 'gemini', 'model' => config('ai.providers.gemini.models.text.default')],
        'Ollama' => ['provider' => 'ollama', 'model' => config('ai.providers.ollama.models.text.default')],
        'OpenRouter' => ['provider' => 'openrouter', 'model' => config('ai.providers.openrouter.models.text.default')],
    ];

    $this->line("Prompt: {$prompt}");

    foreach ($providers as $name => $settings) {
        $this->newLine();
        $this->info("[{$name}] {$settings['provider']} / {$settings['model']}");
        $startedAt = microtime(true);

        try {
            $response = app(ChatAgent::class)->prompt(
                $prompt,
                [],
                $settings['provider'],
                $settings['model'],
                90,
            );

            $duration = number_format(microtime(true) - $startedAt, 2);
            $this->line("Waktu: {$duration} detik");
            $this->line((string) $response);
        } catch (Throwable $exception) {
            $duration = number_format(microtime(true) - $startedAt, 2);
            $this->error("Gagal setelah {$duration} detik: {$exception->getMessage()}");
        }
    }
})->purpose('Compare Gemini, Ollama, and OpenRouter with the same prompt');
