<?php

namespace Hyperf\Odin\Message;


class UserMessage extends AbstractMessage
{

    protected Role $role = Role::User;
}