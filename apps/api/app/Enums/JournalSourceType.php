<?php

namespace App\Enums;

enum JournalSourceType: string
{
    case OfficialJournal = 'official_journal';
    case OfficialPublisher = 'official_publisher';
    case Doaj = 'doaj';
    case Openalex = 'openalex';
    case Crossref = 'crossref';
    case IndexRegistry = 'index_registry';
    case Other = 'other';
}
