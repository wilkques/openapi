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
        $config = config('openapi');
        $filter = $this->option('filter') ?: null;
        $file = $this->option('output') ?: null;

        $generator = GeneratorOpenAPIDoc::format($this->option('format'))
            ->generator(new Generator($config, $filter));

        if ($file)
            $generator->outputDoc($file);
        else
            $this->line($generator->output());
    }
}
