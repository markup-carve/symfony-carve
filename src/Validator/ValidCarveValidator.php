<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Validator;

use MarkupCarve\Carve2\Carve2;
use MarkupCarve\Carve2\CarveException;
use MarkupCarve\SymfonyCarve\CarveTools;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidCarveValidator extends ConstraintValidator
{
    public function __construct(private readonly CarveTools $tools = new CarveTools())
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCarve) {
            throw new UnexpectedTypeException($constraint, ValidCarve::class);
        }
        if ($value === null || $value === '') {
            return;
        }
        if (!is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }
        $value = (string)$value;

        if ($constraint->maxLength !== null && mb_strlen($value) > $constraint->maxLength) {
            $this->fail($constraint, sprintf('must not exceed %d characters', $constraint->maxLength), ValidCarve::TOO_LONG_ERROR);

            return;
        }

        try {
            $result = Carve2::renderWithDiagnostics($value, ['profile' => $constraint->preset ?? 'full']);
        } catch (CarveException $exception) {
            $this->fail($constraint, $exception->getMessage(), ValidCarve::INVALID_ERROR);

            return;
        }
        foreach ($result->diagnostics as $diagnostic) {
            if ($diagnostic->channel === 'render-loss') {
                $this->fail($constraint, sprintf('markup is not allowed by the %s preset', $constraint->preset), ValidCarve::DISALLOWED_ERROR);

                return;
            }
        }
        if ($constraint->strict && $result->diagnostics !== []) {
            $this->fail($constraint, $result->diagnostics[0]->message, ValidCarve::INVALID_ERROR);

            return;
        }
        if ($constraint->lint) {
            $finding = $this->tools->lint($value)[0] ?? null;
            if ($finding !== null) {
                $this->fail($constraint, $finding->message, ValidCarve::INVALID_ERROR);
            }
        }
    }

    private function fail(ValidCarve $constraint, string $error, string $code): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ error }}', $error)
            ->setCode($code)
            ->addViolation();
    }
}
