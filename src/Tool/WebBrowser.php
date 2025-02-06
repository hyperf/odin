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

namespace Hyperf\Odin\Tool;

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\ModelInterface;
use Throwable;

class WebBrowser extends AbstractTool
{
    public string $name = 'web_browser';

    public string $description
        = <<<'EOF'
This tool is used to open a web browser. The input should be a URL, and the output will be the result of the web page.
EOF;

    public array $parameters
        = [
            'url' => [
                'type' => 'string',
                'description' => 'The URL to open.',
                'required' => true,
            ],
        ];

    protected ?ModelInterface $llm = null;

    public function invoke(string $url): string
    {
        // 如果不是以 https:// 或 http:// 开头，则添加 https://
        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
            $url = 'https://' . $url;
        }
        try {
            $this->observer?->debug(sprintf("Web Browser:\n%s", $url));
            // 使用 selenium 打开浏览器访问 $url，并返回结果
            $result = shell_exec("python3 -c 'from selenium import webdriver; options = webdriver.ChromeOptions(); driver = webdriver.Chrome(options=options); driver.get(\"{$url}\"); print(driver.page_source); driver.quit();'");
            $result = $this->cleanHtmlCode($result);
            $result = trim($result);
            $this->observer?->debug(sprintf("Python Code Result:\n%s", $result));
            return $result;
        } catch (Throwable $e) {
            $this->observer?->error($e->getMessage());
        }
    }

    public function getLlm(): ModelInterface
    {
        return $this->llm;
    }

    public function setLlm(ModelInterface $llm): static
    {
        $this->llm = $llm;
        return $this;
    }

    protected function cleanHtmlCode(null|bool|string $result): string
    {
        // 清理 HTML 代码，包括 CSS、JS、注释等
        $result = preg_replace('/<style[^>]*?>.*?<\/style>/si', '', $result);
        $result = preg_replace('/<script[^>]*?>.*?<\/script>/si', '', $result);
        $result = preg_replace('/<!--.*?-->/', '', $result);
        $result = preg_replace('/<[^>]+>/', '', $result);
        $result = preg_replace('/\s+/', ' ', $result);
        if ($this->llm) {
            try {
                $result = $this->llm->chat([
                    new SystemMessage('According to the code of the web page, organize the information'),
                    new UserMessage($result),
                ]);
                $result = $result->getContent();
            } catch (Throwable $throwable) {
                $this->observer?->error($throwable->getMessage());
            }
        }
        return $result;
    }
}
