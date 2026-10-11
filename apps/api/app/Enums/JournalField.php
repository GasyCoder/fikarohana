<?php

namespace App\Enums;

enum JournalField: string
{
    case Title = 'title';
    case Publisher = 'publisher';
    case Country = 'country';
    case Website = 'website';
    case SubmissionUrl = 'submission_url';
    case Scope = 'scope';
    case Identifier = 'identifier';
    case Subject = 'subject';
    case Language = 'language';
    case ArticleType = 'article_type';
    case Indexation = 'indexation';
    case Fee = 'fee';
    case Requirement = 'requirement';
    case Template = 'template';
}
