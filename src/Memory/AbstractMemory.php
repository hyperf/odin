<?php

namespace Hyperf\Odin\Memory;


abstract class AbstractMemory
{

    abstract public function addHumanMessage(string $input, ?string $conversationId): static;

    abstract public function addAIMessage(string $output, ?string $conversationId): static;

    abstract public function buildPrompt(string $input, ?string $conversationId): string;

}