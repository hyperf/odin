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

use Hyperf\Odin\Message\ToolMessage;

/**
 * Merged tool message class.
 *
 * Used to represent multiple tool results that need to be combined
 * into a single user message for Claude's Converse API.
 */
class MergedToolMessage extends ToolMessage
{
    /**
     * @var array<ToolMessage>
     */
    private array $toolMessages;

    /**
     * @param array<ToolMessage> $toolMessages Array of ToolMessage instances
     */
    public function __construct(array $toolMessages)
    {
        $this->toolMessages = $toolMessages;
        // Use the first tool message's data as base
        $firstMessage = $toolMessages[0];
        parent::__construct(
            $firstMessage->getContent(),
            $firstMessage->getToolCallId(),
            $firstMessage->getName(),
            $firstMessage->getArguments()
        );

        // Check all tool messages for cache points
        foreach ($toolMessages as $toolMessage) {
            if ($toolMessage->getCachePoint()) {
                $this->setCachePoint($toolMessage->getCachePoint());
                break; // Found cache point, no need to continue
            }
        }
    }

    /**
     * Get all tool messages.
     *
     * @return array<ToolMessage>
     */
    public function getToolMessages(): array
    {
        return $this->toolMessages;
    }

    /**
     * Check if this is a merged tool message.
     */
    public function isMerged(): bool
    {
        return true;
    }
}
