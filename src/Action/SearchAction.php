<?php

namespace Hyperf\Odin\Action;


use GuzzleHttp\Client;

class SearchAction extends AbstractAction
{

    public string $name = 'Search';
    public string $desc = '如果需要从互联网上搜索内容可以使用，格式: Search(keyword: string)';

    public function handle(string $keyword): string
    {
        return false;
        echo 'Enter search action' . PHP_EOL;
        $client = new Client();
        $path = 'https://serpapi.com/search';
        $response = $client->get($path, [
            'query' => [
                'engine' => 'baidu',
                'q' => $keyword,
            ],
        ]);
        $webContent = $response->getBody()->getContents();
        var_dump($webContent);
        exit();
        return '搜索结果：' . $webContent;
    }

}