<?php

namespace App\Enums;

enum GithubTargetType: string
{
    case User = 'user';
    case Organization = 'org';
}
