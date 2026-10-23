<?php

declare(strict_types=1);

namespace MarkupCarve\SymfonyCarve\Tests;

use InvalidArgumentException;
use MarkupCarve\Carve\Extension\WikilinksExtension;
use MarkupCarve\SymfonyCarve\CarveRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RenderCacheTest extends TestCase
{
    public function testRenderIsServedFromThePool(): void
    {
        $pool = new ArrayAdapter();
        $renderer = new CarveRenderer(cache: $pool);

        $first = $renderer->render('# Cached');
        $keys = array_keys($pool->getValues());

        self::assertCount(1, $keys);
        self::assertStringStartsWith('carve.render.', $keys[0]);
        self::assertSame($first, $pool->getItem($keys[0])->get());
        self::assertSame($first, $renderer->render('# Cached'));
        self::assertCount(1, $pool->getValues());
    }

    public function testDifferentOptionsDoNotShareAnEntry(): void
    {
        $pool = new ArrayAdapter();
        $plain = new CarveRenderer(cache: $pool);
        $comment = $plain->withOptions(['profile' => 'comment']);

        self::assertStringContainsString('<h1', $plain->render('# Title'));
        self::assertStringNotContainsString('<h1', $comment->render('# Title'));
        self::assertCount(2, $pool->getValues());
    }

    public function testTheResultObjectRoundTripsThroughThePool(): void
    {
        $pool = new ArrayAdapter();
        $renderer = new CarveRenderer(cache: $pool);

        $renderer->renderResult('# One');
        $cached = $renderer->renderResult('# One');

        self::assertSame(['One'], array_column($cached->toc(), 'text'));
    }

    public function testAClosureExtensionDisablesCachingWithoutAVersion(): void
    {
        $pool = new ArrayAdapter();
        $extension = new WikilinksExtension(urlGenerator: static fn (string $page): string => '/' . $page);

        (new CarveRenderer(extensions: [$extension], cache: $pool))->render('[[a]]');
        self::assertSame([], $pool->getValues());

        (new CarveRenderer(extensions: [$extension], cache: $pool, cacheVersion: '1'))->render('[[a]]');
        self::assertCount(1, $pool->getValues());
    }

    public function testTtlAndPrefixAreApplied(): void
    {
        $pool = new ArrayAdapter();
        (new CarveRenderer(cache: $pool, cacheTtl: 60, cachePrefix: 'docs'))->render('x');

        self::assertStringStartsWith('docs.render.', (string)array_key_first($pool->getValues()));
    }

    public function testAReservedCharacterInThePrefixIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CarveRenderer(cachePrefix: 'a:b');
    }

    public function testAChangedIncludeInvalidatesTheFileEntry(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-cache-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ part.crv }}\n");
        file_put_contents($root . '/part.crv', "First.\n");
        $renderer = new CarveRenderer(includeRoot: $root, cache: new ArrayAdapter());

        try {
            self::assertStringContainsString('First.', $renderer->renderFile($root . '/main.crv'));
            file_put_contents($root . '/part.crv', "Second.\n");
            self::assertStringContainsString('Second.', $renderer->renderFile($root . '/main.crv'));
        } finally {
            unlink($root . '/part.crv');
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }

    public function testCreatingAMissingIncludeInvalidatesTheFileEntry(): void
    {
        $root = sys_get_temp_dir() . '/symfony-carve-cache-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/main.crv', "{{ later.crv }}\n");
        $renderer = new CarveRenderer(includeRoot: $root, cache: new ArrayAdapter());

        try {
            self::assertStringNotContainsString('Arrived.', $renderer->renderFile($root . '/main.crv'));
            file_put_contents($root . '/later.crv', "Arrived.\n");
            self::assertStringContainsString('Arrived.', $renderer->renderFile($root . '/main.crv'));
        } finally {
            unlink($root . '/later.crv');
            unlink($root . '/main.crv');
            rmdir($root);
        }
    }
}
