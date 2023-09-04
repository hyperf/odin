<?php

namespace Hyperf\Odin\Action;


use Hyperf\Odin\Apis\ClientInterface;

abstract class AbstractAction
{

    public string $name;
    public string $desc;
    protected ClientInterface $client;


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

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function setClient(ClientInterface $client): static
    {
        $this->client = $client;
        return $this;
    }

}