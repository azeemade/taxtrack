<?php

namespace App\Enums;

enum JournalEntryStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PUBLISHED = 'published';
}
