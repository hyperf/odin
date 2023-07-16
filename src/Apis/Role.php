<?php

namespace Hyperf\Odin\Apis;


enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';

}