<?php

use App\Console\Commands\CustomersImportCommand;

test('it skips malformed rows when preparing load data file', function () {
    $sourcePath = tempnam(sys_get_temp_dir(), 'import-source-');
    $loadPath = null;

    file_put_contents($sourcePath, implode("\n", [
        'name,email,company,city,country,birthday',
        'Jane Doe,jane@example.com,Acme,Dhaka,Bangladesh,1992-05-15',
        'Missing Columns,missing@example.com,Acme',
        'John Doe,john@example.com,Beta,Chittagong,Bangladesh,1990-01-01',
        'Trailing Bad Row,trailing@example.com',
    ])."\n");

    try {
        $method = new ReflectionMethod(CustomersImportCommand::class, 'prepareLoadDataFile');
        $method->setAccessible(true);

        $loadPath = $method->invoke(new CustomersImportCommand, $sourcePath);

        $rows = [];
        $handle = fopen($loadPath, 'rb');

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        expect($rows)->toBe([
            ['name', 'email', 'company', 'city', 'country', 'birthday'],
            ['Jane Doe', 'jane@example.com', 'Acme', 'Dhaka', 'Bangladesh', '1992-05-15'],
            ['John Doe', 'john@example.com', 'Beta', 'Chittagong', 'Bangladesh', '1990-01-01'],
        ]);
    } finally {
        if (file_exists($sourcePath)) {
            unlink($sourcePath);
        }

        if ($loadPath !== null && file_exists($loadPath)) {
            unlink($loadPath);
        }
    }
});
