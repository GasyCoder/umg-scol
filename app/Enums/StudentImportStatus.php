<?php

namespace App\Enums;

enum StudentImportStatus: string
{
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case Ready = 'ready';
    case Committing = 'committing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
