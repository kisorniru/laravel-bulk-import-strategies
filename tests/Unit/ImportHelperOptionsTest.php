<?php

use App\ImportHelper;

function importHelperWithChunkSize(?string $chunkSize): object
{
    return new class($chunkSize) {
        use ImportHelper;

        public function __construct(private readonly ?string $chunkSize)
        {
        }

        public function option(string $key): ?string
        {
            return $key === 'chunk-size' ? $this->chunkSize : null;
        }

        public function resolvedChunkSize(int $default = 1000): int
        {
            return $this->chunkSize($default);
        }
    };
}

test('it uses the default chunk size when no option is provided', function () {
    expect(importHelperWithChunkSize(null)->resolvedChunkSize())->toBe(1000);
});

test('it accepts a positive chunk size option', function () {
    expect(importHelperWithChunkSize('250')->resolvedChunkSize())->toBe(250);
});

test('it trims whitespace around chunk size options', function () {
    expect(importHelperWithChunkSize(' 500 ')->resolvedChunkSize())->toBe(500);
});

test('it rejects invalid chunk size options', function () {
    importHelperWithChunkSize('0')->resolvedChunkSize();
})->throws(InvalidArgumentException::class, 'The --chunk-size option must be a positive integer.');
