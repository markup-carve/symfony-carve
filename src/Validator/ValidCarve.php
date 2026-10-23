<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Validator;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * The value parses as Carve, fits the preset, and optionally has no parse
 * warnings or lint findings.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidCarve extends Constraint
{
    /**
     * @var string
     */
    public const INVALID_ERROR = '0d3a6b8e-6f0c-4b8e-9c2a-6f1f3b2a9c51';

    /**
     * @var string
     */
    public const TOO_LONG_ERROR = '6c1e2f4a-3b5d-4e7f-8a9b-0c1d2e3f4a5b';

    /**
     * @var string
     */
    public const DISALLOWED_ERROR = '9f8e7d6c-5b4a-4392-8170-6f5e4d3c2b1a';

    /**
     * @var array<string, string>
     */
    protected const ERROR_NAMES = [
        self::INVALID_ERROR => 'INVALID_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::DISALLOWED_ERROR => 'DISALLOWED_ERROR',
    ];

    public string $message = 'This value is not valid Carve markup: {{ error }}';

    public bool $strict = false;

    public bool $lint = false;

    public ?string $preset = null;

    public ?int $maxLength = null;

    /**
     * @param bool|null $strict Treat parse warnings as errors.
     * @param bool|null $lint Treat lint findings as errors.
     * @param string|null $preset Profile the markup must fit: full, article, comment or minimal.
     * @param int|null $maxLength Maximum length in characters.
     * @param string|null $message Violation message; `{{ error }}` is replaced with the reason.
     * @param array<string>|null $groups
     * @param mixed $payload
     *
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    #[HasNamedArguments]
    public function __construct(
        ?bool $strict = null,
        ?bool $lint = null,
        ?string $preset = null,
        ?int $maxLength = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->strict = $strict ?? $this->strict;
        $this->lint = $lint ?? $this->lint;
        $this->preset = $preset;
        $this->maxLength = $maxLength;
        $this->message = $message ?? $this->message;

        if ($preset !== null && !in_array($preset, ['full', 'article', 'comment', 'minimal'], true)) {
            throw new ConstraintDefinitionException(sprintf('Unknown Carve preset "%s".', $preset));
        }
        if ($maxLength !== null && $maxLength < 0) {
            throw new ConstraintDefinitionException('The Carve maximum length must not be negative.');
        }
    }
}
