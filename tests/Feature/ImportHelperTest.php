<?php

use App\ImportHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Create a dummy class to host the ImportHelper trait for testing
$testImporterClass = new class {
    use ImportHelper;

    public function executeStrategy(string $strategy, string $filePath): void
    {
        $this->$strategy($filePath);
    }
};

beforeEach(function () {
    // Allow password to be nullable so strategies without password mappings can run successfully
    Schema::table('users', function ($table) {
        $table->string('password')->nullable()->change();
    });

    // Generate a temporary CSV file with mock users
    $this->tempCsv = tempnam(sys_get_temp_dir(), 'mock_users_') . '.csv';

    $header = "id,name,email,company,city,country,birthday\n";
    $row1 = "1001,John Doe,john@example.com,Acme Corp,New York,USA,1990-01-01\n";
    $row2 = "1002,Jane Smith,jane@example.com,Beta LLC,Los Angeles,USA,1992-05-15\n";

    file_put_contents($this->tempCsv, $header . $row1 . $row2);
});

afterEach(function () {
    if (file_exists($this->tempCsv)) {
        unlink($this->tempCsv);
    }
});

test('it successfully imports users using collect and chunk strategy', function () use ($testImporterClass) {
    expect(User::count())->toBe(0);

    $testImporterClass->executeStrategy('import03CollectAndChunk', $this->tempCsv);

    expect(User::count())->toBe(2);

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('John Doe');
    expect($user->custom_id)->toBe(1001);
});

test('it successfully imports users using lazy collection with chunking strategy', function () use ($testImporterClass) {
    expect(User::count())->toBe(0);

    $testImporterClass->executeStrategy('import05LazyCollectionWithChunking', $this->tempCsv);

    expect(User::count())->toBe(2);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Jane Smith');
    expect($user->custom_id)->toBe(1002);
});

test('it successfully imports users using manual streaming strategy', function () use ($testImporterClass) {
    expect(User::count())->toBe(0);

    $testImporterClass->executeStrategy('import07ManualStreaming', $this->tempCsv);

    expect(User::count())->toBe(2);

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('John Doe');
    expect($user->custom_id)->toBe(1001);
});
