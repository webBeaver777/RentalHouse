<?php

namespace App\Modules\Catalog\Domain\Enums;

enum CatalogItemType: string
{
    case ROOM = 'room';
    case ITEM = 'item';
    case DEFECT = 'defect';
    case CONDITION = 'condition';

    public function label(): string
    {
        return match ($this) {
            self::ROOM => 'Pomieszczenie',
            self::ITEM => 'Element',
            self::DEFECT => 'Usterka',
            self::CONDITION => 'Stan',
        };
    }
}
