<?php

use Hyperf\Odin\Utils\TokenCounter;
use Yethee\Tiktoken\EncoderProvider;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

$text = file_get_contents('../data/keewood_lowcode_template_1.json');

$provider = new EncoderProvider();

$encoder = $provider->getForModel('gpt-3.5-turbo-0301');
$tokens = $encoder->encode($text);
echo count($tokens);
