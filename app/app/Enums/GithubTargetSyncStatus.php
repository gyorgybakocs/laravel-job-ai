<?php

namespace App\Enums;

enum GithubTargetSyncStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';
}
