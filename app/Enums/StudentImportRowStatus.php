<?php

namespace App\Enums;

enum StudentImportRowStatus: string
{
    case Valid = 'valid';
    case Warning = 'warning';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
}
