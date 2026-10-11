<?php

namespace App\Enums;

enum FactAvailability: string
{
    case Known = 'known';
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';
}
