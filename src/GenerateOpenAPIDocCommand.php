<?php

namespace Wilkques\OpenAPI;

use Illuminate\Console\Command;

class GenerateOpenAPIDocCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate
                            {--format=json : The format of the output, current options are json and yaml}
                            {--f|filter= : Filter to a specific route prefix, such as /api or /v2/api}
                            {--e|except= : Except to a specific route prefix, such as /api or /v2/api}
                            {--o|output= : Output file to write the contents to, defaults to stdout}';

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
            $config = config('openapi');
            $filter = $this->option('filter') ?: null;
            $file = $this->option('output') ?: null;
            $except = $this->option('except') ?: null;

            $generator = GeneratorOpenAPIDoc::format($this->option('format'))
                ->generator(
                    (new Generator($config, $filter))->setExceptRoute($except)
                );

            if ($file) {
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
}
