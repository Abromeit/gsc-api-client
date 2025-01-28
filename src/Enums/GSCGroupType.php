<?php

declare(strict_types=1);

namespace Abromeit\GoogleSearchConsoleClient\Enums;

enum GSCGroupType: string
{
    case OR = 'or';
    case AND = 'and';
}
