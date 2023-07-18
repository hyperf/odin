<?php

namespace Hyperf\Odin\Action;


class SearchAction extends AbstractAction
{

    public string $name = 'Search';
    public string $desc = 'If user want to search something on Internet, use this action, action input format: Search(keyword: string)';

    public function handle(string $keyword): string
    {
        echo 'Enter search action' . PHP_EOL;
        $webContent = file_get_contents('http://www.baidu.com/s?wd=' . $keyword);
        return '搜索结果：' . $webContent;
    }

}