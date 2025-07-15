<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Odin\Tool\Definition\Schema;

use Exception;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;

/**
 * JSON Schema 验证器，专门用于验证 Schema 本身是否符合 JSON Schema 标准规范.
 */
class SchemaValidator
{
    /**
     * @var array 验证错误信息
     */
    protected array $errors = [];

    /**
     * @var array 缓存的元Schema
     */
    protected static array $metaSchemaCache = [];

    /**
     * @var array 验证结果缓存
     */
    protected static array $validationCache = [];

    /**
     * @var string 本地缓存目录
     */
    protected string $cacheDir;

    /**
     * @var array 有效的格式值
     */
    protected array $validFormats = ['date', 'time', 'date-time', 'email', 'hostname', 'ipv4', 'ipv6', 'uri', 'uuid'];

    public function __construct(?string $cacheDir = null)
    {
        // 如果未指定缓存目录，则默认使用项目的runtime目录
        $this->cacheDir = $cacheDir ?? dirname(__DIR__, 4) . '/runtime/schema_cache';

        // 确保缓存目录存在
        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * 验证Schema本身是否符合JSON Schema规范.
     *
     * @param array $schema 要验证的Schema
     * @param string $metaSchema 要使用的元Schema版本
     * @return bool 验证是否通过
     */
    public function validate(array $schema, string $metaSchema = 'https://json-schema.org/draft-07/schema#'): bool
    {
        $this->errors = [];

        // 计算缓存键
        $cacheKey = md5(json_encode($schema) . $metaSchema);

        // 检查内存缓存
        if (isset(self::$validationCache[$cacheKey])) {
            $result = self::$validationCache[$cacheKey];
            $this->errors = $result['errors'];
            return $result['valid'];
        }

        try {
            $valid = $this->quickValidate($schema);
            if ($valid) {
                $metaSchemaObject = $this->getMetaSchema($metaSchema);
                $schemaObject = json_decode(json_encode($schema));
                $schemaStorage = new SchemaStorage(new UriRetriever(), new UriResolver());
                $validator = new Validator(new Factory($schemaStorage));
                $validator->validate($schemaObject, $metaSchemaObject);
                $valid = $validator->isValid();
                if (! $valid && $validator->getErrors()) {
                    $this->errors = array_merge($this->errors, $validator->getErrors());
                }
            }

            // 缓存验证结果
            self::$validationCache[$cacheKey] = [
                'valid' => $valid,
                'errors' => $this->errors,
            ];

            return $valid;
        } catch (Exception $e) {
            $this->errors = [
                [
                    'property' => '',
                    'message' => $e->getMessage(),
                    'constraint' => [
                        'name' => 'exception',
                        'params' => [],
                    ],
                ],
            ];

            // 缓存异常结果
            self::$validationCache[$cacheKey] = [
                'valid' => false,
                'errors' => $this->errors,
            ];

            return false;
        }
    }

    /**
     * 获取验证错误信息.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取元Schema（优先使用缓存）.
     */
    protected function getMetaSchema(string $metaSchemaUrl): object
    {
        // 先检查内存缓存
        if (isset(self::$metaSchemaCache[$metaSchemaUrl])) {
            return self::$metaSchemaCache[$metaSchemaUrl];
        }

        // 对于常用的 meta schema，使用内嵌的定义，避免网络请求
        if ($metaSchemaUrl === 'https://json-schema.org/draft-07/schema#') {
            $metaSchemaObject = json_decode(json_encode($this->getDraft07Schema()));
        } else {
            // 生成缓存文件名
            $cacheKey = md5($metaSchemaUrl);
            $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

            // 检查文件缓存，如果本地有直接读取
            if (file_exists($cacheFile)) {
                $metaSchemaObject = json_decode(file_get_contents($cacheFile));
            } else {
                // 本地没有才从远程获取并永久保存
                $retriever = new UriRetriever();
                $metaSchemaObject = $retriever->retrieve($metaSchemaUrl);

                // 保存到文件缓存
                file_put_contents($cacheFile, json_encode($metaSchemaObject));
            }
        }

        // 保存到内存缓存
        self::$metaSchemaCache[$metaSchemaUrl] = $metaSchemaObject;

        return $metaSchemaObject;
    }

    /**
     * 快速验证 - 执行高效的初步检查.
     */
    private function quickValidate(array $schema): bool
    {
        // 检查属性中的错误
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propDef) {
                // 检查类型定义
                if (! isset($propDef['type'])) {
                    $this->errors[] = [
                        'property' => 'properties.' . $propName,
                        'message' => 'Property "' . $propName . '" missing required "type" field',
                        'constraint' => 'required',
                    ];
                    return false;
                }

                // 检查格式
                if (isset($propDef['format']) && ! in_array($propDef['format'], $this->validFormats, true)) {
                    $this->errors[] = [
                        'property' => 'properties.' . $propName . '.format',
                        'message' => 'Format "' . $propDef['format'] . '" is not a valid format',
                        'constraint' => 'enum',
                    ];
                    return false;
                }
            }
        }

        // 检查引用
        if ($this->containsReferences($schema) && $this->hasInvalidReferences($schema)) {
            return false;
        }

        return true;
    }

    /**
     * 快速检查是否包含引用.
     */
    private function containsReferences(array $schema): bool
    {
        return str_contains(json_encode($schema), '$ref');
    }

    /**
     * 检查Schema中是否有无效的引用.
     */
    private function hasInvalidReferences(array $schema): bool
    {
        $hasInvalidRef = false;
        $schemaJson = json_encode($schema);

        // 使用正则表达式查找所有引用
        if (preg_match_all('/"\$ref"\s*:\s*"#\/([^"]+)"/', $schemaJson, $matches)) {
            foreach ($matches[1] as $path) {
                $parts = explode('/', $path);

                // 检查引用路径是否存在
                $current = $schema;
                foreach ($parts as $part) {
                    if (! isset($current[$part])) {
                        $this->errors[] = [
                            'property' => implode('.', ['$ref']),
                            'message' => 'Reference "#/' . $path . '" cannot be resolved',
                            'constraint' => 'reference',
                        ];
                        $hasInvalidRef = true;
                        break;
                    }
                    $current = $current[$part];
                }

                if ($hasInvalidRef) {
                    break; // 找到一个无效引用就可以提前返回
                }
            }
        }

        return $hasInvalidRef;
    }

    /**
     * 获取内嵌的 Draft-07 Schema 定义，避免网络请求
     */
    private function getDraft07Schema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            '$id' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'Core schema meta-schema',
            'definitions' => [
                'schemaArray' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => ['$ref' => '#'],
                ],
                'nonNegativeInteger' => [
                    'type' => 'integer',
                    'minimum' => 0,
                ],
                'nonNegativeIntegerDefault0' => [
                    'allOf' => [
                        ['$ref' => '#/definitions/nonNegativeInteger'],
                        ['default' => 0],
                    ],
                ],
                'simpleTypes' => [
                    'enum' => [
                        'array',
                        'boolean',
                        'integer',
                        'null',
                        'number',
                        'object',
                        'string',
                    ],
                ],
                'stringArray' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'uniqueItems' => true,
                    'default' => [],
                ],
            ],
            'type' => ['object', 'boolean'],
            'properties' => [
                '$id' => [
                    'type' => 'string',
                    'format' => 'uri-reference',
                ],
                '$schema' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                '$ref' => [
                    'type' => 'string',
                    'format' => 'uri-reference',
                ],
                '$comment' => [
                    'type' => 'string',
                ],
                'title' => [
                    'type' => 'string',
                ],
                'description' => [
                    'type' => 'string',
                ],
                'default' => true,
                'readOnly' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'writeOnly' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'examples' => [
                    'type' => 'array',
                    'items' => true,
                ],
                'multipleOf' => [
                    'type' => 'number',
                    'exclusiveMinimum' => 0,
                ],
                'maximum' => [
                    'type' => 'number',
                ],
                'exclusiveMaximum' => [
                    'type' => 'number',
                ],
                'minimum' => [
                    'type' => 'number',
                ],
                'exclusiveMinimum' => [
                    'type' => 'number',
                ],
                'maxLength' => ['$ref' => '#/definitions/nonNegativeInteger'],
                'minLength' => ['$ref' => '#/definitions/nonNegativeIntegerDefault0'],
                'pattern' => [
                    'type' => 'string',
                    'format' => 'regex',
                ],
                'additionalItems' => ['$ref' => '#'],
                'items' => [
                    'anyOf' => [
                        ['$ref' => '#'],
                        ['$ref' => '#/definitions/schemaArray'],
                    ],
                    'default' => true,
                ],
                'maxItems' => ['$ref' => '#/definitions/nonNegativeInteger'],
                'minItems' => ['$ref' => '#/definitions/nonNegativeIntegerDefault0'],
                'uniqueItems' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'contains' => ['$ref' => '#'],
                'maxProperties' => ['$ref' => '#/definitions/nonNegativeInteger'],
                'minProperties' => ['$ref' => '#/definitions/nonNegativeIntegerDefault0'],
                'required' => ['$ref' => '#/definitions/stringArray'],
                'additionalProperties' => ['$ref' => '#'],
                'definitions' => [
                    'type' => 'object',
                    'additionalProperties' => ['$ref' => '#'],
                    'default' => [],
                ],
                'properties' => [
                    'type' => 'object',
                    'additionalProperties' => ['$ref' => '#'],
                    'default' => [],
                ],
                'patternProperties' => [
                    'type' => 'object',
                    'additionalProperties' => ['$ref' => '#'],
                    'propertyNames' => ['format' => 'regex'],
                    'default' => [],
                ],
                'dependencies' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'anyOf' => [
                            ['$ref' => '#'],
                            ['$ref' => '#/definitions/stringArray'],
                        ],
                    ],
                ],
                'propertyNames' => ['$ref' => '#'],
                'const' => true,
                'enum' => [
                    'type' => 'array',
                    'items' => true,
                    'minItems' => 1,
                    'uniqueItems' => true,
                ],
                'type' => [
                    'anyOf' => [
                        ['$ref' => '#/definitions/simpleTypes'],
                        [
                            'type' => 'array',
                            'items' => ['$ref' => '#/definitions/simpleTypes'],
                            'minItems' => 1,
                            'uniqueItems' => true,
                        ],
                    ],
                ],
                'format' => ['type' => 'string'],
                'contentMediaType' => ['type' => 'string'],
                'contentEncoding' => ['type' => 'string'],
                'if' => ['$ref' => '#'],
                'then' => ['$ref' => '#'],
                'else' => ['$ref' => '#'],
                'allOf' => ['$ref' => '#/definitions/schemaArray'],
                'anyOf' => ['$ref' => '#/definitions/schemaArray'],
                'oneOf' => ['$ref' => '#/definitions/schemaArray'],
                'not' => ['$ref' => '#'],
            ],
            'default' => true,
        ];
    }
}
