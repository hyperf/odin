<?php

namespace Hyperf\Odin\Message;


enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';

}