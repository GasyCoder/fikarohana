<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Stale = 'stale';
    case Conflicting = 'conflicting';
    case Rejected = 'rejected';
}
