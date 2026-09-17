<?php

namespace App\Enums;

enum PhotoKind: string
{
    case Before = 'before';
    case After = 'after';
    case Other = 'other';
}
