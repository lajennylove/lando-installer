<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Unknown = 'unknown';
    case Creating = 'creating';
    case Error = 'error';
}
