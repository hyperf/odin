<?php

namespace Hyperf\Odin\Interpreter;

class CodeRunner
{

    protected array $config
        = [
            'php' => [
                'bin' => '/Users/huangzh/.box/php8.1',
            ],
            'python' => [
                'bin' => 'python3',
            ],
            'shell' => [
                'bin' => 'sh',
            ],
            'golang' => [
                'bin' => 'go run',
            ],
        ];

    public function runCode(string $language, string $code)
    {
        [$language, $code] = $this->prehandle($language, $code);
        echo sprintf('[DEBUG] Language: %s Code: %s' . PHP_EOL, $language, $code);
        switch ($language) {
            case 'php':
                return $this->runPHP($code);
            case 'python':
                return $this->runPython($code);
            case 'shell':
                return $this->runShell($code);
            case 'golang':
                return $this->runGolang($code);
            default:
                throw new \Exception('Language not supported');
        }
    }

    protected function runPHP(string $code)
    {
        $code = trim($code);
        // 判断是否以 <?php 开头，如果不是则加上
        if (! str_starts_with($code, '<?php')) {
            $code = "<?php\n" . $code;
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'odin-interpreter-php-execution-');
        file_put_contents($tmpFile, $code);
        $bin = $this->config['php']['bin'];
        $output = shell_exec("$bin $tmpFile");
        unlink($tmpFile);
        echo sprintf('[DEBUG] PHP Code execution result: %s' . PHP_EOL, trim($output ?? ''));
        return $output;
    }

    protected function runPython(string $code)
    {
        $code = trim($code);
        $tmpFile = tempnam(sys_get_temp_dir(), 'odin-interpreter-python-execution-');
        file_put_contents($tmpFile, $code);
        $bin = $this->config['python']['bin'];
        $output = shell_exec("$bin $tmpFile");
        unlink($tmpFile);
        echo sprintf('[DEBUG] Python Code execution result: %s' . PHP_EOL, trim($output ?? ''));
        return $output;
    }

    protected function runShell(string $code): bool|string|null
    {
        $code = trim($code);
        $tmpFile = tempnam(sys_get_temp_dir(), 'odin-interpreter-shell-execution-');
        file_put_contents($tmpFile, $code);
        $bin = $this->config['shell']['bin'];
        $output = shell_exec("$bin $tmpFile");
        unlink($tmpFile);
        echo sprintf('[DEBUG] Shell Code execution result: %s' . PHP_EOL, trim($output ?? ''));
        return $output;
    }

    protected function runGolang(string $code)
    {
        $code = trim($code);
        $tmpFile = tempnam(sys_get_temp_dir(), 'odin-interpreter-golang-execution-');
        file_put_contents($tmpFile, $code);
        $bin = $this->config['golang']['bin'];
        $output = shell_exec("$bin $tmpFile");
        unlink($tmpFile);
        echo sprintf('[DEBUG] Golang Code execution result: %s' . PHP_EOL, trim($output ?? ''));
        return $output;
    }

    protected function prehandle(string $language, string $code): array
    {
        // 如果 language 为 bash，则修改 language 为 shell
        if ($language === 'bash') {
            $language = 'shell';
        }
        // 如果 language 为 go，则修改 language 为 golang
        if ($language === 'go') {
            $language = 'golang';
        }
        // 如果 language 为 golang 且 code 以 go get 开头，则修改 language 为 shell
        if ($language === 'golang' && str_starts_with($code, 'go get')) {
            $language = 'shell';
        }
        // 如果 language 为 php 且 code 以 composer 开头，则修改 language 为 shell
        if ($language === 'php' && str_starts_with($code, 'composer')) {
            $language = 'shell';
        }
        // 如果 language 为 python 且 code 以 pip 开头，则修改 language 为 shell
        if ($language === 'python' && str_starts_with($code, 'pip')) {
            $language = 'shell';
        }
        return [$language, $code];
    }

}