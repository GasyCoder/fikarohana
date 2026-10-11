<?php

namespace App\Enums;

enum TemplateFormat: string
{
    case Word = 'word';
    case Latex = 'latex';
    case PdfGuide = 'pdf_guide';
    case Other = 'other';
}
