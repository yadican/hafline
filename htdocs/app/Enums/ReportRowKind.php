<?php

namespace App\Enums;

enum ReportRowKind: string
{
    case Data = 'data';
    case Opening = 'opening';
    case Total = 'total';
    case Note = 'note';
}
