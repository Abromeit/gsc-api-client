<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Enums;

enum GSCOperator: string
{
    case EQUALS = 'equals';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'notContains';
    case INCLUDING_REGEX = 'includingRegex';
    case EXCLUDING_REGEX = 'excludingRegex';
}
