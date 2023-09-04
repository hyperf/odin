<?php

namespace Hyperf\Odin\Action;


class ActionFactory
{

    public function create(string $name): AbstractAction
    {
        $class = sprintf('Hyperf\\Odin\\Action\\%sAction', ucfirst($name));
        if (class_exists($class)) {
            return new $class();
        }
        throw new \InvalidArgumentException(sprintf('Action %s not found.', $name));
    }

}