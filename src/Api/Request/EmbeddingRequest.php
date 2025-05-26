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

namespace Hyperf\Odin\Api\Request;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Contract\Api\Request\RequestInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;

class EmbeddingRequest implements RequestInterface
{
    private array $businessParams = [];

    private bool $includeBusinessParams = false;

    /**
     * @param string|string[] $input 需要嵌入的文本，可以是字符串或字符串数组
     * @param string $model 使用的嵌入模型ID
     * @param null|string $encoding_format 嵌入向量的编码格式，默认为 'float'
     * @param null|string $user 代表最终用户的唯一标识符
     * @param array $dimensions 嵌入向量的维度数组（可选）
     */
    public function __construct(
        protected array|string $input,
        protected string $model,
        protected ?string $encoding_format = 'float',
        protected ?string $user = null,
        protected ?array $dimensions = null,
    ) {}

    public function validate(): void
    {
        if (empty($this->model)) {
            throw new InvalidArgumentException('Model is required.');
        }

        if (empty($this->input)) {
            throw new InvalidArgumentException('Input is required.');
        }

        // 验证 encoding_format 是否为有效值
        if ($this->encoding_format !== 'float' && $this->encoding_format !== 'base64') {
            throw new InvalidArgumentException('Encoding format must be either float or base64.');
        }
    }

    public function createOptions(): array
    {
        $this->validate();

        $body = [
            'model' => $this->model,
            'input' => $this->input,
            'encoding_format' => $this->encoding_format,
        ];
        if ($this->includeBusinessParams && ! empty($this->businessParams)) {
            $body['business_params'] = $this->businessParams;
        }

        $options = [
            RequestOptions::JSON => $body,
        ];

        if ($this->user !== null) {
            $options[RequestOptions::JSON]['user'] = $this->user;
        }

        if ($this->dimensions !== null) {
            $options[RequestOptions::JSON]['dimensions'] = $this->dimensions;
        }

        return $options;
    }

    /**
     * Get the input text.
     */
    public function getInput(): array|string
    {
        return $this->input;
    }

    /**
     * Get the model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the encoding format.
     */
    public function getEncodingFormat(): ?string
    {
        return $this->encoding_format;
    }

    /**
     * Get the user.
     */
    public function getUser(): ?string
    {
        return $this->user;
    }

    /**
     * Get the dimensions.
     */
    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function getBusinessParams(): array
    {
        return $this->businessParams;
    }

    public function setBusinessParams(array $businessParams): void
    {
        $this->businessParams = $businessParams;
    }

    public function isIncludeBusinessParams(): bool
    {
        return $this->includeBusinessParams;
    }

    public function setIncludeBusinessParams(bool $includeBusinessParams): void
    {
        $this->includeBusinessParams = $includeBusinessParams;
    }
}
