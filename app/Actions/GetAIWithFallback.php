<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAIWithFallback {

    use AsAction;

    public function handle($prompt, $model = 'gpt-3.5-turbo'): string|\Exception
    {
        info("Trying query with {$model}");
        $response = $this->requestAICompletion($prompt, $model);

        if (!str($response)->startsWith('unsure')) {
            return $response;
        }

        // Fall back
        if (!$model == 'gpt-4') {

            info('Trying query with GPT-4');
            $response = $this->requestAICompletion($prompt, 'gpt-4'); // Re-run with better model.

            if (!str($response)->startsWith('unsure')) {
                return $response;
            }
        }

        // Then just fail.
    }


    public function requestAICompletion($prompt, string $model = 'gpt-3.5-turbo'): string
    {

        $token = config('app.openai');

        if (! $token) {
            throw new \Exception('No API Key Found');
        }

        if(!$model) {
            throw new \Exception('Model is required');
        }

        $result = Http::withToken($token)
            ->asJson()
            ->acceptJson()
            ->timeout(75)
            ->withBody(json_encode(
                [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ])
            )
            ->post('https://api.openai.com/v1/chat/completions');

        if ($result->status() != 200) {
            throw new \Exception($result->body());
        }

        $object = json_decode($result->body());

        return $object->choices[0]->message->content;
    }

}