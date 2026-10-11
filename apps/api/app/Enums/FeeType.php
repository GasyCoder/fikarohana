<?php

namespace App\Enums;

enum FeeType: string
{
    case Apc = 'apc';
    case Submission = 'submission';
    case Page = 'page';
    case Color = 'color';
    case Other = 'other';
}
