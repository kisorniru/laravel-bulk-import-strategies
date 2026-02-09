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

## Laravel Sail Benchmark Setup

This repository uses MySQL for the main benchmark path. If `docker-compose.yml` is not present locally, generate the Sail stack with MySQL first:

```bash
composer install
php artisan sail:install --with=mysql
cp .env.example .env
```

For Sail, update `.env` so Laravel connects to the MySQL container:

```dotenv
DB_HOST=mysql
DB_DATABASE=import_million_rows
MYSQL_ATTR_LOCAL_INFILE=true
```

Then start Sail, prepare the app, and run migrations:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

The CSV files are tracked with Git LFS, so pull the real datasets before benchmarking:

```bash
git lfs pull
```

The native MySQL load strategy also needs `local_infile` enabled on the database server. For a local Sail container, enable it before running the benchmark:

```bash
./vendor/bin/sail mysql -e "SET GLOBAL local_infile = 1;"
./vendor/bin/sail artisan import:users-data
```

## Troubleshooting Bulk Imports

- **`LOAD DATA LOCAL INFILE` is rejected:** MySQL may have `local_infile` disabled. Keep `MYSQL_ATTR_LOCAL_INFILE=true` in `.env` and enable the server setting with `SET GLOBAL local_infile = 1;` or the equivalent MySQL configuration.
- **CSV imports only a few strange rows:** The CSV files may still be Git LFS pointer files. Run `git lfs pull` and confirm files in `public/csv_files/` are full datasets, not small pointer text files.
- **`Unknown column 'custom_id'`:** Some experimental helper strategies insert a `custom_id` field, but the default users migration may not include it. Add the column in the schema or use a strategy/query that matches the current table.
- **Rows are missing after import:** The active load-file path skips malformed CSV rows that do not contain the expected columns. Check the source CSV for short or broken lines before comparing row totals.
- **`Allowed memory size exhausted`:** Avoid strategies that preload the whole CSV for large datasets. Use streaming, chunked PDO, or the native MySQL load strategy, and start with the smallest dataset to validate setup.

## Usage

1. Place your CSV file in a directory accessible by the Laravel application.
2. Run the import command using Artisan. Pass the path to the CSV file as a parameter:

   ```bash
   php artisan import:users-data /path/to/your/csvfile.csv
   ```

   For chunk-based strategies, tune the batch size without editing source code:

   ```bash
   php artisan import:users-data --chunk-size=500
   ```

3. The command will read the CSV file, process the data, and insert the user records into the `users` table.

## Benchmark Output

After each import, the command prints a compact benchmark summary with elapsed time, memory usage, SQL query count, and inserted row count.

Sample output only:

```text
TIME: 1.42s MEM: 0.23MB SQL: 104 ROWS: 100,000
```

Use these numbers as the output format, not as a performance guarantee. Actual results depend on the selected strategy, CSV size, database configuration, and local machine.

Each run also appends a JSON summary line to `storage/logs/benchmark.log` so previous results can be reviewed later. The persisted entry includes the timestamp, strategy name, dataset/file path, execution time, memory usage, SQL query count, and inserted row count.

Use `--benchmark-log=storage/logs/my-benchmark.log` to write the history elsewhere, or `--benchmark-log=false` to run without writing a benchmark history file.

## Import Strategies

### Strategy Comparison Table

Below is a detailed comparison of the 12 import strategies implemented in the benchmarking engine:

| Strategy / Method | Memory Behavior | SQL Query Behavior | Best Use Case |
| :--- | :--- | :--- | :--- |
| **01. Basic One-by-One** | Extremely high memory overhead due to entire file mapping and Eloquent model instantiations. | Runs $N$ separate SQL write queries. | Small datasets ($<100$ rows) where data validation observers are required. |
| **02. Collect and Insert** | High memory overhead; loads complete dataset into PHP array. | Single batch SQL insert query. | Small datasets ($<10,000$ rows) to minimize network roundtrips. |
| **03. Collect and Chunk** | Medium-to-high memory overhead; preloads entire file into memory before chunking. | Executed in batches of 1,000 rows. | Medium datasets ($<100,000$ rows) requiring fast insertion speeds. |
| **04. Lazy Collection** | Extremely low memory overhead (~0MB delta) utilizing PHP Generator. | Runs $N$ separate row-by-row write queries. | Low-memory environments processing huge files without batching capabilities. |
| **05. Lazy Chunking** | Low memory overhead; chunks data on-the-fly via generators. | Executes Eloquent inserts in chunks of 1,000. | Standard large imports where Eloquent overhead is tolerable. |
| **06. Lazy Chunking + PDO** | Exceptionally low memory overhead (~0.23MB delta) with raw connection. | Raw SQL statements executed in chunks of 1,000. | Standard high-volume parsing where Eloquent overhead must be bypassed. |
| **07. Manual Streaming** | Traditional low-memory profile using stream resource bounds. | Eloquent chunk batch inserts. | Legacy PHP systems not supporting modern Laravel LazyCollections. |
| **08. Manual Stream + PDO** | Extremely low memory usage with native buffer reading. | Raw SQL queries parsed and executed in chunks of 1,000. | Standard manual streaming when maximum raw performance is desired. |
| **09. Row-by-Row PDO** | Stable low-memory footprint using a single database statement. | Row-by-row raw SQL writes. | Simple stream processing with prepared raw SQL statements. |
| **10. PDO Prepared Chunked** | Extremely high performance and minimal memory footprint (~0.74MB delta). | Reuses static sized prepared statement chunk templates. | Maximum throughput raw SQL ingestion ($>1,000,000$ rows). |
| **11. Concurrent (Fibers)** | High CPU utilization; concurrent PHP child processes. | Parallelized batch inserts. | Multi-core servers requiring lightning-fast parsing via parallel execution. |
| **12. MySQL Load Infile** | 0MB PHP overhead; bypasses the application layer completely. | Single native database engine ingestion command. | Ultra-large scale CSV ingestion ($>2,000,000$ rows) with configured server permissions. |

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
