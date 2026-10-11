<?php

namespace App\Enums;

enum RequirementType: string
{
    case WordCount = 'word_count';
    case AbstractWords = 'abstract_words';
    case KeywordCount = 'keyword_count';
    case FigureCount = 'figure_count';
    case TableCount = 'table_count';
    case ReferenceStyle = 'reference_style';
    case FigureGuidance = 'figure_guidance';
    case TableGuidance = 'table_guidance';
    case FileFormats = 'file_formats';
    case Structure = 'structure';
    case Declaration = 'declaration';
}
