<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Validator;

use MarkupCarve\SymfonyCarve\Validator\ValidCarve;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidCarveValidatorTest extends TestCase
{
    public function testValidMarkupAndEmptyValuesPass(): void
    {
        self::assertCount(0, $this->validator()->validate("# Title\n\n_text_", new ValidCarve()));
        self::assertCount(0, $this->validator()->validate(null, new ValidCarve()));
        self::assertCount(0, $this->validator()->validate('', new ValidCarve()));
    }

    public function testPresetRejectsDisallowedMarkup(): void
    {
        $violations = $this->validator()->validate('# Title', new ValidCarve(preset: 'comment'));

        self::assertCount(1, $violations);
        self::assertSame(ValidCarve::DISALLOWED_ERROR, $violations->get(0)->getCode());
        self::assertStringContainsString('comment preset', (string)$violations->get(0)->getMessage());
        self::assertCount(0, $this->validator()->validate('_fine_', new ValidCarve(preset: 'comment')));
    }

    public function testMaxLengthCountsCharacters(): void
    {
        self::assertCount(0, $this->validator()->validate('äöü', new ValidCarve(maxLength: 3)));
        $violations = $this->validator()->validate('äöüß', new ValidCarve(maxLength: 3));

        self::assertCount(1, $violations);
        self::assertSame(ValidCarve::TOO_LONG_ERROR, $violations->get(0)->getCode());
    }

    public function testLintFindingsFailOnlyWhenRequested(): void
    {
        self::assertCount(0, $this->validator()->validate("**bold**\n", new ValidCarve()));
        self::assertCount(1, $this->validator()->validate("**bold**\n", new ValidCarve(lint: true)));
    }

    public function testCustomMessageReceivesTheError(): void
    {
        $violations = $this->validator()->validate('# T', new ValidCarve(preset: 'minimal', message: 'Bad: {{ error }}'));

        self::assertStringStartsWith('Bad: markup is not allowed', (string)$violations->get(0)->getMessage());
    }

    public function testAttributeMappingWorks(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CarveComment('# Heading'));

        self::assertCount(1, $violations);
        self::assertSame('body', $violations->get(0)->getPropertyPath());
    }

    public function testAnUnknownPresetIsADefinitionError(): void
    {
        $this->expectException(ConstraintDefinitionException::class);

        new ValidCarve(preset: 'blog');
    }

    public function testANonStringValueIsATypeViolation(): void
    {
        $violations = $this->validator()->validate(['x'], new ValidCarve());

        self::assertCount(1, $violations);
        self::assertStringContainsString('string', (string)$violations->get(0)->getMessage());
    }

    public function testAnEmptyValueIsLeftToNotBlank(): void
    {
        $violations = $this->validator()->validate('', [new NotBlank(), new ValidCarve()]);

        self::assertCount(1, $violations);
        self::assertSame(NotBlank::IS_BLANK_ERROR, $violations->get(0)->getCode());
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidator();
    }
}
