<?php

namespace App\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Core\Exceptions\UnprocessableEntityException;
use App\Support\JobSettings;
use App\Support\TrustedCas;

/**
 * One Claude request that must come back as JSON matching a schema. Every
 * Claude feature here (job classification, the assistant, FAQ writing,
 * translation) goes through this, so model choice, the CA list, the default
 * refusal fallback and error mapping live in one place.
 */
class ClaudeJson
{
    public function __construct(private readonly ?Client $client = null) {}

    public static function configured(): bool
    {
        return JobSettings::anthropicKey() !== '';
    }

    /**
     * @param  array<int, array{type: string, text: string, cacheControl?: array}>  $system
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws AiUnavailable|AiDeclined
     */
    public function call(array $system, array $messages, array $schema, string $effort = 'low', int $maxTokens = 8000): array
    {
        $client = $this->client ?? self::defaultClient();

        try {
            $message = $client->beta->messages->create(
                model: JobSettings::model(),
                maxTokens: $maxTokens,
                system: $system,
                messages: $messages,
                outputConfig: ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiUnavailable('The Anthropic API key was refused: '.$e->getMessage(), previous: $e);
        } catch (BadRequestException|NotFoundException|UnprocessableEntityException $e) {
            throw new AiDeclined('The request was rejected: '.$e->getMessage(), previous: $e);
        } catch (APIStatusException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            $category = $message->stopDetails?->category;

            throw new AiDeclined('The model declined'.($category ? ' ('.$category.')' : '').'.');
        }

        if ($message->stopReason === 'max_tokens') {
            throw new AiUnavailable('The model ran out of output tokens.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                if (! is_array($data)) {
                    throw new AiUnavailable('The model did not return JSON.');
                }

                return $data;
            }
        }

        throw new AiUnavailable('The model returned no text.');
    }

    public static function defaultClient(): Client
    {
        return new Client(apiKey: JobSettings::anthropicKey(), requestOptions: [
            'timeout' => 90,
            'maxRetries' => 2,
            'transporter' => new \GuzzleHttp\Client(['verify' => TrustedCas::path(), 'connect_timeout' => 10]),
        ]);
    }
}
