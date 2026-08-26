<?php

namespace App\Enums;

/**
 * Every LLM provider supported by the installed Prism version. Each value
 * maps 1:1 to a native Prism driver, except Nvidia whose OpenAI-compatible
 * NIM endpoint routes through the OpenAI driver with a base-URL override.
 */
enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case DeepSeek = 'deepseek';
    case OpenRouter = 'openrouter';
    case Nvidia = 'nvidia';
    case Gemini = 'gemini';
    case Groq = 'groq';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case Perplexity = 'perplexity';
    case Xai = 'xai';
    case Z = 'z';
    case ElevenLabs = 'elevenlabs';
    case VoyageAi = 'voyageai';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::DeepSeek => 'DeepSeek',
            self::OpenRouter => 'OpenRouter',
            self::Nvidia => 'NVIDIA (NIM)',
            self::Gemini => 'Google Gemini',
            self::Groq => 'Groq',
            self::Mistral => 'Mistral',
            self::Ollama => 'Ollama (self-hosted)',
            self::Perplexity => 'Perplexity',
            self::Xai => 'xAI (Grok)',
            self::Z => 'Z.ai (GLM)',
            self::ElevenLabs => 'ElevenLabs',
            self::VoyageAi => 'Voyage AI',
        };
    }

    /**
     * The Prism driver serving this provider. All values map to their own
     * native Prism driver except NVIDIA, whose OpenAI-compatible NIM
     * endpoint routes through the OpenAI driver with a URL override.
     */
    public function prismDriver(): string
    {
        return $this === self::Nvidia ? 'openai' : $this->value;
    }

    /**
     * Default API endpoint override, or NULL when the Prism driver's own
     * default applies. Callers may still override per credential via
     * ai_settings.base_url (e.g. self-hosted Ollama).
     */
    public function baseUrl(): ?string
    {
        return match ($this) {
            self::Nvidia => 'https://integrate.api.nvidia.com/v1',
            default => null,
        };
    }

    /**
     * Suggested models shown next to the provider dropdown.
     *
     * @return list<string>
     */
    public function suggestedModels(): array
    {
        return match ($this) {
            self::OpenAi => ['gpt-4o', 'gpt-4o-mini'],
            self::Anthropic => ['claude-sonnet-4-5', 'claude-haiku-4-5'],
            self::DeepSeek => ['deepseek-chat', 'deepseek-reasoner'],
            self::OpenRouter => ['anthropic/claude-sonnet-4.5', 'openai/gpt-4o-mini', 'meta-llama/llama-3.1-70b-instruct'],
            self::Nvidia => ['openai/gpt-oss-120b', 'nvidia/nemotron-4-340b-instruct', 'mistralai/mistral-nemotron'],
            self::Gemini => ['gemini-2.5-flash', 'gemini-2.5-pro'],
            self::Groq => ['llama-3.3-70b-versatile', 'mixtral-8x7b-32768'],
            self::Mistral => ['mistral-large-latest', 'mistral-small-latest'],
            self::Ollama => ['llama3.2', 'qwen2.5', 'mistral'],
            self::Perplexity => ['sonar-pro', 'sonar'],
            self::Xai => ['grok-4', 'grok-3-mini'],
            self::Z => ['glm-4-plus', 'glm-4-air'],
            // Audio/embedding-specialised providers.
            self::ElevenLabs => ['eleven_multilingual_v2_5'],
            self::VoyageAi => ['voyage-3-large', 'voyage-3-lite'],
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $provider) => ['value' => $provider->value, 'label' => $provider->label()],
            self::cases(),
        );
    }
}
