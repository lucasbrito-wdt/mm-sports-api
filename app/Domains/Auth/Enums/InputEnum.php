<?php

namespace App\Domains\Auth\Enums;

enum InputEnum: string
{
    case ASK = 'ask';
    case SECRET = 'secret';
    case CONFIRM = 'confirm';
    case ANTICIPATE = 'anticipate';
    case CHOICE = 'choice';
}
