<?php

namespace App\Services\EditorialExtraction;

use DomainException;

class RuleRegistry
{
    public const VERSION = 'editorial-v5';

    public function __construct(private readonly string $version = self::VERSION) {}

    public function version(): string
    {
        if (! preg_match('/^editorial-v[0-9]{1,3}$/D', $this->version)) {
            throw new DomainException('editorial_invalid_version');
        }

        return $this->version;
    }

    public function rules(): array
    {
        return [new FeeRule, new LanguageRule, new ArticleTypeRule, new WordLimitRule, new ReferenceStyleRule, new LinkRule(true), new LinkRule(false)];
    }
}
