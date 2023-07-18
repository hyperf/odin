<?php

namespace Hyperf\Odin\Action;


abstract class AbstractAction
{

    public string $name;
    public string $desc;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDesc(): string
    {
        return $this->desc;
    }

    public function setDesc(string $desc): static
    {
        $this->desc = $desc;
        return $this;
    }

}