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
use Yethee\Tiktoken\EncoderProvider;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

$text = file_get_contents('../data/keewood_lowcode_template_1.json');

$provider = new EncoderProvider();

$encoder = $provider->getForModel('gpt-3.5-turbo-0301');
$tokens = $encoder->encode($text);
echo count($tokens);
