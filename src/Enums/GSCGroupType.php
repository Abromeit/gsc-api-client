<?php

declare(strict_types=1);

namespace Abromeit\GscApiClient\Enums;

enum GSCGroupType: string
{
    case OR = 'or';
    case AND = 'and';
}
