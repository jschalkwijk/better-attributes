<?php

declare(strict_types=1);

namespace JornSchalkwijk\BetterAttributes\Enums;

enum ReflectionClassMethod: string
{
    case PROPERTIES = 'getProperties';
    case METHODS = 'getMethods';
}
