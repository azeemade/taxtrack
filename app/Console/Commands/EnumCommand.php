<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;

class EnumCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:enum {name} {--enums=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Enum file command e.g. php artisan make:enum RegistrationEnum --enums=pending,approved,declined';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $enumWithNamespace = $this->argument('name');
        $options = $this->options('enums');

        $namespace = $this->getModelNamespace($enumWithNamespace)['namespace'];
        $enum = $this->getModelNamespace($enumWithNamespace)['enum'];

        $this->createEnum($namespace, $enum, $options['enums']);
    }

    protected function createEnum(string $namespace, string $enum, string $options): void
    {
        $this->createFolder('Enums', $namespace);

        $path = app_path('Enums/' . $namespace . '/' . Str::studly($enum) . '.php');
        $this->createFile($path, $this->buildEnumStub($namespace, $enum, $options));
    }

    protected function createFolder(string $folderName, string $namespace): void
    {
        $path = app_path($folderName . '/' . $namespace);

        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
        $this->info("Folder created: {$folderName}");
    }

    protected function createFile(string $path, string $content): void
    {
        if (file_exists($path)) {
            $this->error("File already exists at {$path}");
            return;
        }

        file_put_contents($path, $content);
        $this->info("File created: {$path}");
    }

    protected function buildEnumStub(string $namespace, string $enum, string $options): string
    {
        $options = explode(',', $options);

        $optionValues = array_map(function ($value, $index) {
            return $index == 0 ?  'case ' . strtoupper(trim($value)) . " = '" . trim($value) . "';" : '    case ' . strtoupper($value) . " = '" . $value . "';";
        }, $options, array_keys($options));

        $optionString = implode(PHP_EOL, $optionValues);

        $stub = file_get_contents(__DIR__ . '/stubs/enum.stub');
        return str_replace(['{{ enum }}', '{{ folder }}', '{{ enumOptions }}'], [Str::studly($enum), $namespace, $optionString], $stub);
    }

    protected function getModelNamespace(string $enumWithNamespace): array
    {
        $formattedNamespace = str_replace('/', '\\', $enumWithNamespace);

        $parts = explode('\\', $formattedNamespace);

        $enum = array_pop($parts);

        $namespace = implode('\\', $parts);

        return [
            'enum' => $enum,
            'namespace' => $namespace
        ];
    }
}
