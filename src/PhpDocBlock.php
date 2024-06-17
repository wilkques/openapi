<?php

namespace Wilkques\OpenAPI;

use phpDocumentor\Reflection\DocBlockFactory;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\DocBlock;
use Wilkques\OpenAPI\Exceptions\JsonFormatException;

class PhpDocBlock
{
    /** @var DocBlockFactory */
    protected $docParser;

    public function __construct(DocBlockFactory $docParser)
    {
        $this->docParser = $docParser;
    }

    /**
     * @param string|bool $parsedComment
     * @param \Closure|null $callback
     * 
     * @return mixed|null|\phpDocumentor\Reflection\DocBlock
     */
    public function parseDocBlock($parsedComment, \Closure $callback = null)
    {
        if (!$parsedComment) {
            return null;
        }

        if (!$callback) {
            return $this->docParser->create($parsedComment);
        }

        return $callback($this->docParser->create($parsedComment));
    }

    /**
     * @param \phpDocumentor\Reflection\DocBlock\Tags\Generic|phpDocumentor\Reflection\DocBlock\Tags\Deprecated $docs
     * 
     * @return array
     */
    public function docsBody($docs)
    {
        // if description is null
        if (!$description = $docs->getDescription()) {
            return [];
        }

        return $this->phpDoc($description->getBodyTemplate());
    }

    /**
     * @param \Illuminate\Support\Str|string $yamlString
     * 
     * @return array
     */
    public function yaml($yamlString)
    {
        if (!extension_loaded('yaml')) {
            throw new \RuntimeException('YAML extension must be loaded to use the yaml output format');
        }

        return \yaml_parse($yamlString);
    }

    /**
     * @param string $jsonString
     * 
     * @return string
     */
    private function errorJsonString($jsonString)
    {
        $jsonString = trim(preg_replace('/\s+/', ' ', $jsonString));

        return stripslashes($jsonString);
    }

    /**
     * @param \Illuminate\Support\Str|string $jsonString
     * 
     * @return array
     */
    public function json($jsonString)
    {
        $data = json_decode($jsonString, true);

        ['code' => $code, 'message' => $message] = json_error_check();

        if ($code) {
            throw new JsonFormatException(json_encode([
                'ErrorMessage' => $message,
                'JsonString' => $this->errorJsonString($jsonString->__toString())
            ]), 403);
        }

        return $data;
    }

    /**
     * @param string $docs
     * 
     * @throws OpenAPIException
     * 
     * @return array
     */
    public function phpDoc(string $docParser)
    {
        $docString = Str::of($docParser)
            ->replaceMatches('/\(|\)/', '');

        if (
            ($docString->startsWith('[') and $docString->endsWith(']'))
            or ($docString->startsWith('{') and $docString->endsWith('}'))
        ) {
            $data = $this->json($docString);
        } else {
            $data = $this->yaml($docString);
        }

        return $data;
    }

    /**
     * @param DocBlock $docBlock
     * @param string $tagName
     * 
     * @return \phpDocumentor\Reflection\DocBlock\Tag[]
     */
    public function getDocsTagsByName(DocBlock $docBlock, string $tagName)
    {
        return $docBlock->getTagsByName($tagName) ?:
            $docBlock->getTagsByName(ucfirst($tagName));
    }
}
