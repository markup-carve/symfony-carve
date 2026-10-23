<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests\Validator;

use MarkupCarve\SymfonyCarve\Validator\ValidCarve;

final class CarveComment
{
    public function __construct(
        #[ValidCarve(preset: 'comment')]
        public string $body,
    ) {
    }
}
