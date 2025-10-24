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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

class AwsType
{
    /**
     * Converse API with AWS SDK.
     */
    public const CONVERSE = 'converse';

    /**
     * Converse API without AWS SDK (custom Guzzle implementation).
     */
    public const CONVERSE_CUSTOM = 'converse_custom';

    /**
     * InvokeModel API with AWS SDK.
     */
    public const INVOKE = 'invoke';
}
