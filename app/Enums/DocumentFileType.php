<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DocumentFileType: string
{
    use HasValues;

    case PDF = 'pdf';
    case DOCX = 'docx';
    case TXT = 'txt';
    case HTML = 'html';
    case MD = 'md';
}
