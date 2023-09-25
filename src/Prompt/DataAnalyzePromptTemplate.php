<?php

namespace Hyperf\Odin\Prompt;


class DataAnalyzePromptTemplate
{

    public function build(array $data)
    {
        $dataStr = '';
        foreach ($data as $key => $value) {
            $dataStr .= $key . ' => ' . $value . PHP_EOL;
        }
        return <<<EOF
你是一个专业的数据分析师，你需要根据下面的数据进行分析，然后输出你的分析结果：

数据：
{{$dataStr}}
EOF;
    }

}