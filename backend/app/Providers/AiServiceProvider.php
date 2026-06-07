<?php

namespace App\Providers;

use App\Services\AI\AiAssistant;
use App\Services\AI\MockAiAssistant;
use App\Services\AI\OpenAiAssistant;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiAssistant::class, function () {
            if (config('services.ai.provider') === 'openai' && config('services.ai.openai_api_key')) {
                return new OpenAiAssistant(new MockAiAssistant());
            }

            return new MockAiAssistant();
        });
    }
}
