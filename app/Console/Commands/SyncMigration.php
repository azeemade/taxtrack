<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use ReflectionClass;
use ReflectionMethod;

class SyncMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:migration {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare columns in the DB table with migration file and alter the DB table by adding missing columns';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');

        // Check if table exists
        if (!Schema::hasTable($table)) {
            $this->error("The table '{$table}' does not exist.");
            return;
        }

        // Get migration columns dynamically
        $migrationColumns = $this->getMigrationColumns($table);
        if (empty($migrationColumns)) {
            $this->error("Could not find migration for the table '{$table}' or no columns were found.");
            return;
        }

        // Get current columns from the database
        $dbColumns = Schema::getColumnListing($table);

        // Compare and add missing columns
        foreach ($migrationColumns as $column => $type) {
            if (!in_array($column, $dbColumns)) {
                Schema::table($table, function (Blueprint $table) use ($column, $type) {
                    $this->addColumn($table, $column, $type);
                });

                $this->info("Added missing column '{$column}' to the '{$table}' table.");
            }
        }

        $this->info('Table columns synchronized successfully.');
    }

    /**
     * Parse migration file to get the columns and types.
     */
    protected function getMigrationColumns($table)
    {
        $migrationPath = database_path('migrations');
        $migrationFiles = scandir($migrationPath);

        $columns = [];

        foreach ($migrationFiles as $file) {
            if (strpos($file, 'create_' . $table) !== false) {
                $migrationClass = $this->getMigrationClass($migrationPath . '/' . $file);

                if ($migrationClass && method_exists($migrationClass, 'up')) {
                    $columns = $this->getColumnsFromMigration($migrationClass);
                }
            }
        }

        return $columns;
    }

    /**
     * Get the migration class from a file.
     */
    protected function getMigrationClass($file)
    {
        require_once $file;

        $classesBefore = get_declared_classes();
        include_once($file);
        $classesAfter = get_declared_classes();

        $migrationClass = array_diff($classesAfter, $classesBefore);

        return reset($migrationClass);  // Return first added class
    }

    /**
     * Get columns from the up() method of the migration.
     */
    protected function getColumnsFromMigration($migrationClass)
    {
        $columns = [];

        $reflection = new ReflectionClass($migrationClass);
        $method = $reflection->getMethod('up');
        $params = $method->getParameters();

        $blueprintParam = null;

        foreach ($params as $param) {
            if ($param->getClass() && $param->getClass()->getName() === Blueprint::class) {
                $blueprintParam = $param;
                break;
            }
        }

        // Parse the "up" method for column definitions
        $upMethodBody = $method->getBody();

        // This part can get complex depending on how the migration is written,
        // so it may involve using regular expressions or tokenization to capture the method body and analyze it.

        // Return the parsed columns
        return $columns;
    }

    /**
     * Add column to the table using the type.
     */
    protected function addColumn(Blueprint $table, $column, $type)
    {
        switch ($type) {
            case 'integer':
                $table->integer($column);
                break;
            case 'timestamp':
                $table->timestamp($column);
                break;
            case 'date':
                $table->date($column);
                break;
            case 'string':
            default:
                $table->string($column);
                break;
        }
    }
}
