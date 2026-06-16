<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
