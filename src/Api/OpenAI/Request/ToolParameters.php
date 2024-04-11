<?php

namespace Hyperf\Odin\Api\OpenAI\Request;


use Hyperf\Contract\Arrayable;

class ToolParameters implements Arrayable
{

    protected string $type;
    protected array $properties = [];
    protected array $required = [];

    public function __construct(array $properties = [], string $type = 'object')
    {
        $this->properties = $properties;
        $this->type = $type;
        foreach ($properties as $property) {
            if (! $property instanceof ToolParameter) {
                continue;
            }
            if ($property->isRequired()) {
                $this->required[] = $property->getName();
            }
        }
    }

    public function toArray(): array
    {
        $properties = [];
        foreach ($this->getProperties() as $property) {
            if (! $property instanceof ToolParameter) {
                continue;
            }
            $item = [
                'type' => $property->getType(),
                'description' => $property->getDescription(),
            ];
            if ($property->getEnum()) {
                $item['enum'] = $property->getEnum();
            }
            $properties[$property->getName()] = $item;
        }
        return [
            'type' => $this->getType(),
            'properties' => $properties,
            'required' => $this->getRequired(),
        ];
    }

    public static function fromArray(array $parameters): ToolParameters
    {
        $properties = [];
        foreach ($parameters as $name => $property) {
            $properties[] = new ToolParameter($name, $property['description'], $property['type'] ?? 'string', $property['required'] ?? false, $property['enum'] ?? null);
        }
        return new ToolParameters($properties);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    public function getRequired(): array
    {
        return $this->required;
    }

    public function setRequired(array $required): void
    {
        $this->required = $required;
    }

}