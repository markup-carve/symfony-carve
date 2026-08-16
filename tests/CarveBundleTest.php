<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use MarkupCarve\SymfonyCarve\CarveBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class CarveBundleTest extends TestCase
{
    public function testInvalidProfileIsRejectedByConfiguration(): void
    {
        $configuration = new Configuration(new CarveBundle(), null, 'carve');

        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration($configuration, [['profile' => 'unknown']]);
    }
}
