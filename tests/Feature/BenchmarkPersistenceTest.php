<?php

use App\ImportHelper;

test('it appends benchmark summaries as json lines', function () {
    $logPath = storage_path('logs/benchmark.log');

    if (file_exists($logPath)) {
        unlink($logPath);
    }

    $importer = new class {
        use ImportHelper;

        public function persist(array $summary): void
        {
            $this->persistBenchmarkSummary($summary);
        }
    };

    $summary = [
        'timestamp' => '2026-02-05T19:14:00+06:00',
        'strategy' => 'mysql_load_data_local_infile',
        'dataset' => 'users-100.csv',
        'file' => '/tmp/users-100.csv',
        'execution_time_seconds' => 0.123456,
        'formatted_time' => '123ms',
        'memory_usage_mb' => 0.25,
        'sql_queries' => 4,
        'inserted_rows' => 100,
    ];

    try {
        $importer->persist($summary);

        $lines = file($logPath, FILE_IGNORE_NEW_LINES);

        expect($lines)->toHaveCount(1)
            ->and(json_decode($lines[0], true))->toBe($summary);
    } finally {
        if (file_exists($logPath)) {
            unlink($logPath);
        }
    }
});
