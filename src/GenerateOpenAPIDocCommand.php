<?php

namespace Wilkques\OpenAPI;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateOpenAPIDocCommand extends Command
{
    /** @var \Illuminate\Config\Repository */
    protected $config;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate
                            {--format=json : The format of the output, current options are json and yaml}
                            {--f|filter= : Filter to a specific route prefix, such as /api or /v2/api}
                            {--e|except= : Except to a specific route prefix, such as /api or /v2/api}
                            {--o|output= : Output file to write the contents to, defaults to stdout}
                            {--s|show=false : Output contents}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically generates a swagger documentation file for this application';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->registerApp();

            $this->config = $config = config();
            $filter = $this->option('filter') ?: null;
            $format = $this->extension();
            $file = $this->file($format);
            $except = $this->option('except') ?: null;
            $show = $this->show();

            // builder router
            /** @var \Wilkques\OpenAPI\DataObjects\Routes */
            $routes = app(\Wilkques\OpenAPI\DataObjects\Routes::class);

            // Generate openapi
            $generator = new Generator(
                $routes->setFilterRoute($filter)->setExcludeRoute($except),
                $config
            );

            $generator = GeneratorOpenAPIDoc::format($format)->generator($generator->handle());

            if (!$show) {
                $generator->outputDoc($file);
                $this->info('Generate Complete');
            } else {
                $this->line($generator->output());
            }
        } catch (\Wilkques\OpenAPI\Exceptions\JsonFormatException $e) {
            $message = json_decode($e->getMessage(), true);

            $this->error('Error Message');
            $this->line($message['ErrorMessage']);

            $this->error('Json String');
            $this->line($message['JsonString']);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line($e->getMessage());
        }
    }

    public function registerApp()
    {
        app()->scoped(\phpDocumentor\Reflection\DocBlockFactory::class, function () {
            return \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        });
    }

    /**
     * @param string $extension
     * 
     * @return string
     */
    protected function file($extension)
    {
        $config = $this->config;

        $output = $this->option('output');

        [
            'dirname'   => $dir,
            'extension' => $extension,
            'basename'  => $basename,
            'filename'  => $filename,
        ] = array_merge([
            'dirname'   => $config->get('storage', storage_path('api-docs')),
            'filename'  => $config->get('filename', 'apidoc'),
            'extension' => $config->get('extension', 'json'),
            'basename'  => null,
        ], pathinfo($output));

        if (!Str::contains($dir, getcwd())) {
            $dir = getcwd() . DIRECTORY_SEPARATOR . $dir;
        }

        if (!$basename or Str::endsWith($basename, ['json', 'yaml', 'yml'])) {
            // ex: /var/www/project/storage/api-docs/apidoc.json
            return $dir . DIRECTORY_SEPARATOR . $filename . '.' . $extension;
        }

        // ex: /var/www/project/storage/api-docs/apidoc.json
        return $dir . DIRECTORY_SEPARATOR . $basename . DIRECTORY_SEPARATOR . $filename . '.' . $extension;
    }

    /**
     * @return string
     */
    protected function extension()
    {
        return $this->option('format', $this->config->get('extension', 'json'));
    }

    /**
     * @return bool
     */
    protected function show()
    {
        $show = $this->option('show');

        if (!in_array($show, ['false', 'true'])) {
            throw new \InvalidArgumentException("flag show can only be false or true.");
        }

        switch ($show) {
            case 'true':
                return true;
                break;
            case 'false':
            default:
                return false;
                break;
        }
    }
}
