Here’s the README content that you can copy and use for your GitHub project:

# Customers Data Import Command

This Laravel Artisan command is designed to efficiently import large datasets (e.g., millions of user records) from a CSV file into the database. Several techniques are applied to handle large amounts of data, including chunking, memory management, and database prepared statements.

## Overview

This command processes CSV files containing user data and inserts the information into the `users` table in a MySQL database. The command includes various approaches to handle memory issues, improve performance, and manage large datasets efficiently.

## Features

- Handles large CSV files with millions of rows.
- Uses chunking to minimize memory usage.
- Prepared statements for efficient insertion into the database.
- Multiple techniques applied for handling different types of errors like memory exhaustion and query limitations.
- Customizable based on your data structure.

## Installation

1. Clone the repository to your local machine:

   ```bash
   git clone https://github.com/yourusername/repository-name.git
   ```

2. Install the required dependencies using Composer:

   ```bash
   composer install
   ```

3. Add your `.env` database credentials to connect to your MySQL database.

4. Register the command in your `App\Console\Kernel.php` file:

   ```php
   protected $commands = [
       \App\Console\Commands\CustomersImportCommand::class,
   ];
   ```

## Usage

1. Place your CSV file in a directory accessible by the Laravel application.
2. Run the import command using Artisan. Pass the path to the CSV file as a parameter:

   ```bash
   php artisan import:users-data /path/to/your/csvfile.csv
   ```

3. The command will read the CSV file, process the data, and insert the user records into the `users` table.

## Import Strategies

### Basic Approach
This method reads the entire CSV file into memory and attempts to insert all records at once. While this approach is simple, it can be inefficient for large files and may result in memory exhaustion errors.

```php
collect(file($filepath))
    ->skip(1)
    ->map(fn($line) => str_getcsv($line))
    ->map(fn($userData) => [
        'name' => $userData[1],
        'email' => $userData[2],
        'company' => $userData[3],
        'city' => $userData[4],
        'country' => $userData[5],
        'birthday' => $userData[6],
        'password' => bcrypt('password'),
    ])
    ->each(fn($userData) => User::create($userData));
```

### Optimized Insert Using `insert()`
A more memory-efficient approach that reduces the number of queries by inserting multiple rows at once. However, it may hit query limit errors for extremely large datasets.

```php
$userData = collect(file($filepath))
    ->skip(1)
    ->map(fn($line) => str_getcsv($line))
    ->map(fn($userData) => [
        'name' => $userData[1],
        'email' => $userData[2],
        'company' => $userData[3],
        'city' => $userData[4],
        'country' => $userData[5],
        'birthday' => $userData[6],
        'password' => bcrypt('password'),
    ]);

User::insert($userData->all());
```

### Chunked Insertion
This approach reads the CSV file in smaller chunks to prevent memory exhaustion. It processes a fixed number of rows at a time and inserts them into the database in batches.

```php
$chunks = collect(file($filepath))
    ->skip(1)
    ->map(fn($line) => str_getcsv($line))
    ->map(fn($userData) => [
        'name' => $userData[1],
        'email' => $userData[2],
        'company' => $userData[3],
        'city' => $userData[4],
        'country' => $userData[5],
        'birthday' => $userData[6],
        'password' => bcrypt('password'),
    ])
    ->chunk(1000);

$chunks->each(fn($chunk) => User::insert($chunk->all()));
```

### Efficient Insertion with PDO Prepared Statements
For handling even larger datasets (millions of rows), prepared statements are used to insert data more efficiently. This method prepares SQL statements and executes them in batches.

```php
$userDataChunk = [];

$handle = fopen($filepath, 'r');
fgetcsv($handle); // Skip the first line (header)

$pdo = DB::connection()->getPdo();
$statement = $pdo->prepare('INSERT INTO users (name, email, company, city, country, birthday, password) VALUES (?, ?, ?, ?, ?, ?, ?)');

while (($line = fgetcsv($handle)) !== false) {
    $statement->execute([
        $line[1],
        $line[2],
        $line[3],
        $line[4],
        $line[5],
        $line[6],
        bcrypt('password'),
    ]);
}

fclose($handle);
```

### Final Version with Chunked PDO Prepared Statements
This approach optimizes both time and memory by using chunks while also minimizing the number of queries executed.

```php
$chunks = [];
$handle = fopen($filepath, 'r');
fgetcsv($handle); // Skip the first line (header)

$pdo = DB::connection()->getPdo();
$chunkSize = 1000;
$rowPlaceholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
$placeholders = implode(',', array_fill(0, $chunkSize, $rowPlaceholders));
$statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");

while (($line = fgetcsv($handle)) !== false) {
    $chunks = array_merge($chunks, [
        $line[0],
        $line[1],
        $line[2],
        $line[3],
        $line[4],
        $line[5],
        $line[6],
    ]);

    if (count($chunks) >= $chunkSize * 7) {
        $statement->execute($chunks);
        $chunks = [];
    }
}

if (!empty($chunks)) {
    $remainingChunks = count($chunks) / 7;
    $placeholders = implode(',', array_fill(0, $remainingChunks, $rowPlaceholders));
    $statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");
    $statement->execute($chunks);
}

fclose($handle);
```

## Best Practices

- **Chunking:** Use chunking to break the CSV file into smaller parts, reducing memory consumption.
- **Prepared Statements:** Using prepared statements helps improve performance and reduces the risk of SQL injection.
- **Database Indexing:** Ensure that your database tables are properly indexed to speed up insertions.

## Conclusion

This command provides an efficient way to import large datasets into the database with minimal memory usage. By using chunking and prepared statements, it ensures that even millions of records can be inserted without running into memory exhaustion issues.
