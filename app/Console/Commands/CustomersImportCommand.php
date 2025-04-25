<?php

namespace App\Console\Commands;

use PDO;
use App\Models\User;
use App\ImportHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;

class CustomersImportCommand extends Command
{
    /**
     * ImportHelper Trait
     */
    use ImportHelper;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Rules:
     * 1. Use MySQL
     * 2. 256MB Memory Limit
     * 3. No Queue`
     * Execute the console command.
     */
    public function handleImport($filepath): void
    {

        /**
         * Basic Approach [Not very efficiant, took lot memory & time, ran a lot of queries]
         * Not able to insert 1 million rows
         * Generate 'Allowed Memory Size Exhaused' error
         * 1. Read the CSV file line by line
         * 2. Skip the first line (header)
         * 3. Convert each line to an array
         * 4. Map the data to the desired structure
         * 5. Insert each user into the database
         * 6. Use the User model to create the user
         */
        /*
            collect(file($filepath))
            ->skip(1)
            ->map(fn($line) => str_getcsv($line)) // Convert each line to an array
            ->map(fn($userData) => [
                'name' => $userData[1],
                'email' => $userData[2],
                'company' => $userData[3],
                'city' => $userData[4],
                'country' => $userData[5],
                'birthday' => $userData[6],
                'password' => bcrypt('password'),
            ]) // Map to the desired structure
            ->each(fn($userData) => User::create($userData)); // Insert each user into the database
        */

        /**
         * Resolved avobe error and it's little more efficient approach than above [took less memory & time, ran a single query]
         * Not able to insert 1 million rows
         * Generate 'Prepared Statement contains too many placeholders' error
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userData = collect(file($filepath))
            ->skip(1)
            ->map(fn($line) => str_getcsv($line)) // Convert each line to an array
            ->map(fn($userData) => [
                'name' => $userData[1],
                'email' => $userData[2],
                'company' => $userData[3],
                'city' => $userData[4],
                'country' => $userData[5],
                'birthday' => $userData[6],
                'password' => bcrypt('password'),
            ]); // Map to the desired structure

            User::insert($userData->all());
        */

        /**
         * Resolved above 'placeholders' issue and more efficient approach than above [took less memory & time, queries]
         * Chunk increase number of querys but decrease memory usage
         * Not working with 1 million rows
         * Generate 'Allowed Memory Size Exhaused' error
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userData = collect(file($filepath))
                ->skip(1)
                ->map(fn($line) => str_getcsv($line)) // Convert each line to an array
                ->map(fn($userData) => [
                    'name' => $userData[1],
                    'email' => $userData[2],
                    'company' => $userData[3],
                    'city' => $userData[4],
                    'country' => $userData[5],
                    'birthday' => $userData[6],
                    'password' => bcrypt('password'),
                ]) // Map to the desired structure
                ->chunk(1000) // Chunk the data into smaller pieces
                ->each(fn($chunk) => User::insert($chunk->all())); // Insert each chunk into the database
        */

        /**
         * More efficient approach than above [took less memory & time, queries]
         * Chunk increase number of querys but decrease memory usage
         * Not working with 1 million rows
         * Chunk is really helpful when you insert a large amount of data, it insert data really fast
         * On the other hand, it take lot of memory to prepare the data
         * Generate 'Allowed Memory Size Exhaused' error
         * We first open the file and read it using php fopen() function
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userDataChunk = [];
            
            // fopen() function to open the file
            // fgetcsv() function to read the file line by line
            // Convert each line to an array
            // Map the data to the desired structure
            $handle = fopen($filepath, 'r');
            fgetcsv($handle); // Skip the first line (header)
            while (($row = fgetcsv($handle)) !== false) {
                $userDataChunk[] = [
                    'name' => $row[1],
                    'email' => $row[2],
                    'company' => $row[3],
                    'city' => $row[4],
                    'country' => $row[5],
                    'birthday' => $row[6],
                    'password' => bcrypt('password'),
                ];
            }
            fclose($handle);

            $chunks = array_chunk($userDataChunk, 1000); // Chunk the data into smaller pieces
            dd($chunks);
            foreach ($chunks as $chunk) {
                User::insert($chunk); // Insert each chunk into the database
            }
        */

        /**
         * Resolved 'Allowed Memory Size' issue but and extreamly high memory effecency (0 Memory) but [took huge time, huge queries]
         * Working with 2 million rows
         * We first open the file and read it using php fopen() function
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userDataChunk = [];
            
            // fopen() function to open the file
            // fgetcsv() function to read the file line by line
            // DB::connection()->getPdo() to get the PDO instance
            // DB::prepare() to prepare the statement
            // Execute the each statement using while loop

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
        /*

        /**
         * Resolved 'Allowed Memory Size' issue and extreamly low memory uses (0 Memory) but [took huge time, huge queries]
         * Working with 2 million rows
         * To resolve time and query issue, we can use chunking
         * We first open the file and read it using php fopen() function
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userDataChunk = [];
            
            // fopen() function to open the file
            // fgetcsv() function to read the file line by line
            // DB::connection()->getPdo() to get the PDO instance
            // DB::prepare() to prepare the statement
            // Execute the each statement using while loop
            $handle = fopen($filepath, 'r');
            fgetcsv($handle); // Skip the first line (header)

            // Chunk size
            $chunkSize = 1000;
            // Array to hold the chunks
            $chunks = [];
            
            try {
                // Get the PDO instance
                $pdo = DB::connection()->getPdo();

                // Prepare the statement for chunk inserting
                $rowPlaceholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                // Create the placeholders for the chunk
                $placeholders = implode(',', array_fill(0, $chunkSize, $rowPlaceholders));
                // Prepare the final statement for chunk inserting
                $statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");
                // Make this is a private method and call it in the handle method

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

                    // If the chunk size is reached, execute the statement
                    if (count($chunks) >= $chunkSize * 7) {
                        $statement->execute($chunks);
                        // Reset the chunks array
                        $chunks = [];
                    }
                }

                if(! empty($chunks)) {
                    // If there are remaining rows, execute the statement for the remaining rows
                    $remainingChunks = count($chunks) / 7;
                    
                    // Prepare the statement for chunk inserting
                    $rowPlaceholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    // Create the placeholders for the chunk
                    $placeholders = implode(',', array_fill(0, $remainingChunks, $rowPlaceholders));
                    // Prepare the final statement for chunk inserting
                    $statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");
                    // Make this is a private method and call it in the handle method

                    // Execute the statement for the remaining rows
                    $statement->execute($chunks);
                }
            } finally {
                // Close the file handle
                fclose($handle);
            }
        /*

        /**
         * Resolved 'Allowed Memory Size' issue and extreamly low memory uses (0 Memory) but [took moderate time, moderate queries]
         * Working with 2 million rows
         * To resolve time and query issue, we can use chunking
         * We first open the file and read it using php fopen() function
         * 1. Skip the first line (header)
         * 2. Convert each line to an array
         * 3. Map the data to the desired structure
         * 4. Insert whole user into the database
         */
        /*
            $userDataChunk = [];
            
            // fopen() function to open the file
            // fgetcsv() function to read the file line by line
            // DB::connection()->getPdo() to get the PDO instance
            // DB::prepare() to prepare the statement
            // Execute the each statement using while loop
            $handle = fopen($filepath, 'r');
            fgetcsv($handle); // Skip the first line (header)

            // Chunk size
            $chunkSize = 1000;
            // Array to hold the chunks
            $chunks = [];
            
            try {
                // Get the PDO instance
                $pdo = DB::connection()->getPdo();

                // Prepare the statement for chunk inserting
                $rowPlaceholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                // Create the placeholders for the chunk
                $placeholders = implode(',', array_fill(0, $chunkSize, $rowPlaceholders));
                // Prepare the final statement for chunk inserting
                $statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");
                // Make this is a private method and call it in the handle method

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

                    // If the chunk size is reached, execute the statement
                    if (count($chunks) >= $chunkSize * 7) {
                        $statement->execute($chunks);
                        // Reset the chunks array
                        $chunks = [];
                    }
                }

                if(! empty($chunks)) {
                    // If there are remaining rows, execute the statement for the remaining rows
                    $remainingChunks = count($chunks) / 7;
                    
                    // Prepare the statement for chunk inserting
                    $rowPlaceholders = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    // Create the placeholders for the chunk
                    $placeholders = implode(',', array_fill(0, $remainingChunks, $rowPlaceholders));
                    // Prepare the final statement for chunk inserting
                    $statement = $pdo->prepare("INSERT INTO users (custom_id, name, email, company, city, country, birthday, created_at, updated_at) VALUES {$placeholders}");
                    // Make this is a private method and call it in the handle method

                    // Execute the statement for the remaining rows
                    $statement->execute($chunks);
                }
            } finally {
                // Close the file handle
                fclose($handle);
            }
        /*

        /**
         * Resolved moderate time and querries issuees along with previously resolved issues
         * Working with 2 million rows
         * More efficient approach than above [took minimum time, memories & queries]
         * It's uses Laravel 11.23.2's new feature called 'Concurrency'
         * This new feature allows to run tasks concurrently in Laravel, enabling asynchronous execution of operations.
         */
        /*
            $tasks = [];
            $numberOfProcesses = 8; // Number of my current machine's processes

            for ($i=0; $i < $numberOfProcesses; $i++) { 
                
                // Create a closure for each process
                // Each closure will handle a portion of the CSV file
                // This code is NOT run yet!
                // You're just defining the closures (anonymous functions). 
                // Each of those functions knows what to do when it's called, but they’re not being executed during the loop. 
                // They're just sitting in the $tasks array like this:
                // [
                //     function() { do process 0 work },
                //     function() { do process 1 work },
                //     function() { do process 2 work },
                //     ...
                // ]
                // It will be run in the Concurrency::run() method
                $tasks[] = function() use ($filepath, $i, $numberOfProcesses) {
                    
                    DB::reconnect(); // Reconnect to the database for each process
                    $handle = fopen($filepath, 'r');
                    fgets($handle); // Skip the first line (header)
                    $currentLine = 0;
                    $userData = [];

                    while (($line = fgets($handle)) !== false) {

                        if ($currentLine++ % $numberOfProcesses === $i) {
                            continue;
                        }
                        // Example with 20 lines total, 8 processes
                        // Process 0: takes lines 0, 8, 16, ...  (when line % 8 == 0)
                        // Process 1: takes lines 1, 9, 17, ...  (when line % 8 == 1)
                        // Process 2: takes lines 2, 10, 18, ... (when line % 8 == 2)
                        // Process 3: takes lines 3, 11, 19, ... (when line % 8 == 3)
                        // Process 4: takes lines 4, 12, 20, ... (when line % 8 == 4)
                        // Process 5: takes lines 5, 13, 21, ... (when line % 8 == 5)
                        // Process 6: takes lines 6, 14, 22, ... (when line % 8 == 6)
                        // Process 7: takes lines 7, 15, 23, ... (when line % 8 == 7)

                        $row = str_getcsv($line);
                        $userData[] = [
                            'custom_id' => $row[0],
                            'name' => $row[1],
                            'email' => $row[2],
                            'company' => $row[3],
                            'city' => $row[4],
                            'country' => $row[5],
                            'birthday' => $row[6],
                        ];

                        if(count($userData) >= 1000) {
                            // Insert the chunk into the database
                            DB::table('users')->insert($userData);
                            $userData = []; // Reset the userData array
                        }
                    }

                    if(!empty($userData)) {
                        // Insert the remaining data
                        DB::table('users')->insert($userData);
                    }

                    // Close the file handle
                    fclose($handle);
                    DB::disconnect(); // Close the connection
                };

            }

            // Run the tasks concurrently
            // This will execute all the closures in the $tasks array at the same time
            // Each closure will run in its own process, allowing for concurrent execution
            // This is where the magic happens!
            // The Concurrency::run() method will take care of executing each closure in parallel
            // It will manage the processes, handle any errors, and ensure that all tasks are completed
            // The Concurrency::run() method will return when all tasks are completed
            // This is the final step where all the closures are executed concurrently
            Concurrency::run($tasks);
        /*

        /**
         * Resolved moderate time and querries issuees along with previously resolved issues
         * Working with 2 million rows
         * More efficient approach than above [took minimum time, memories & queries]
         * It's uses Laravel 11.23.2's new feature called 'Concurrency'
         * This new feature allows to run tasks concurrently in Laravel, enabling asynchronous execution of operations.
         */
        /**/
            // Resolve the whole problem using MySQL's LOAD DATA INFILE
            // This is the most efficient way to import large CSV files into MySQL directly
            // It uses the MySQL's LOAD DATA INFILE command to load data from a CSV file into a MySQL table
            // By default, MySQL does not allow loading data from local files for security reasons
            // You need to enable it in your MySQL configuration
            // You can do this by adding the following line to your MySQL configuration file (my.cnf or my.ini)
            // [mysqld]
            // local-infile=1
            // Or you can enable it in your MySQL command line by running the following command
            // SET GLOBAL local_infile = 1;
            // You can also enable it in your MySQL connection string by adding the following option
            // ?local_infile=1
        /**/

        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_LOCAL_INFILE, true);
        $filepath = str_replace('\\', '/', $filepath);

        $query = <<<SQL
            LOAD DATA LOCAL INFILE '{$filepath}'
            INTO TABLE users
            FIELDS TERMINATED BY ','
            ENCLOSED BY '"'
            LINES TERMINATED BY '\n'
            IGNORE 1 LINES
            (@col1, @col2, @col3, @col4, @col5, @col6)
            SET
                name = @col1,
                email = @col2,
                company = @col3,
                city = @col4,
                country = @col5,
                birthday = @col6,
                password = 'default_hashed_password',
        SQL;

        $pdo->exec($query);

    }
}