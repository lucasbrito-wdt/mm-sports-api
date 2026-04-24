<?php

namespace App\Domains\Reviews\Enums;

enum ReviewModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
