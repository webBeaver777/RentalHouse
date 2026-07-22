<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Enums\CatalogItemType;
use App\Modules\Catalog\Infrastructure\Models\CatalogItem;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Room types
        $rooms = [
            ['pl' => 'Salon', 'en' => 'Living room'],
            ['pl' => 'Sypialnia', 'en' => 'Bedroom'],
            ['pl' => 'Kuchnia', 'en' => 'Kitchen'],
            ['pl' => 'Łazienka', 'en' => 'Bathroom'],
            ['pl' => 'Toaleta', 'en' => 'Toilet'],
            ['pl' => 'Przedpokój', 'en' => 'Hallway'],
            ['pl' => 'Balkon', 'en' => 'Balcony'],
            ['pl' => 'Taras', 'en' => 'Terrace'],
            ['pl' => 'Garaż', 'en' => 'Garage'],
            ['pl' => 'Piwnica', 'en' => 'Basement'],
            ['pl' => 'Strych', 'en' => 'Attic'],
            ['pl' => 'Korytarz', 'en' => 'Corridor'],
        ];

        foreach ($rooms as $index => $room) {
            CatalogItem::updateOrCreate(
                ['type' => CatalogItemType::ROOM, 'name->pl' => $room['pl']],
                [
                    'type' => CatalogItemType::ROOM,
                    'name' => $room,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        // Common items/elements
        $items = [
            ['pl' => 'Ściany', 'en' => 'Walls'],
            ['pl' => 'Sufit', 'en' => 'Ceiling'],
            ['pl' => 'Podłoga', 'en' => 'Floor'],
            ['pl' => 'Okna', 'en' => 'Windows'],
            ['pl' => 'Drzwi', 'en' => 'Doors'],
            ['pl' => 'Oświetlenie', 'en' => 'Lighting'],
            ['pl' => 'Gniazdka elektryczne', 'en' => 'Electrical outlets'],
            ['pl' => 'Kontakty', 'en' => 'Switches'],
            ['pl' => 'Kaloryfer', 'en' => 'Radiator'],
            ['pl' => 'Klimatyzacja', 'en' => 'Air conditioning'],
            ['pl' => 'Lodówka', 'en' => 'Refrigerator'],
            ['pl' => 'Pralka', 'en' => 'Washing machine'],
            ['pl' => 'Zmywarka', 'en' => 'Dishwasher'],
            ['pl' => 'Kuchenka', 'en' => 'Stove'],
            ['pl' => 'Piekarnik', 'en' => 'Oven'],
            ['pl' => 'Mikrofalówka', 'en' => 'Microwave'],
            ['pl' => 'Wanna', 'en' => 'Bathtub'],
            ['pl' => 'Prysznic', 'en' => 'Shower'],
            ['pl' => 'Umywalka', 'en' => 'Sink'],
            ['pl' => 'Toaleta', 'en' => 'Toilet'],
            ['pl' => 'Szafa', 'en' => 'Wardrobe'],
            ['pl' => 'Łóżko', 'en' => 'Bed'],
            ['pl' => 'Stół', 'en' => 'Table'],
            ['pl' => 'Krzesła', 'en' => 'Chairs'],
            ['pl' => 'Sofa', 'en' => 'Sofa'],
            ['pl' => 'Telewizor', 'en' => 'TV'],
            ['pl' => 'Rolety', 'en' => 'Blinds'],
            ['pl' => 'Zasłony', 'en' => 'Curtains'],
        ];

        foreach ($items as $index => $item) {
            CatalogItem::updateOrCreate(
                ['type' => CatalogItemType::ITEM, 'name->pl' => $item['pl']],
                [
                    'type' => CatalogItemType::ITEM,
                    'name' => $item,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        // Condition states
        $conditions = [
            ['pl' => 'Nowy', 'en' => 'New'],
            ['pl' => 'Bardzo dobry', 'en' => 'Very good'],
            ['pl' => 'Dobry', 'en' => 'Good'],
            ['pl' => 'Używany', 'en' => 'Used'],
            ['pl' => 'Uszkodzony', 'en' => 'Damaged'],
            ['pl' => 'Nie działa', 'en' => 'Not working'],
        ];

        foreach ($conditions as $index => $condition) {
            CatalogItem::updateOrCreate(
                ['type' => CatalogItemType::CONDITION, 'name->pl' => $condition['pl']],
                [
                    'type' => CatalogItemType::CONDITION,
                    'name' => $condition,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        // Common defects
        $defects = [
            ['pl' => 'Zarysowanie', 'en' => 'Scratch'],
            ['pl' => 'Pęknięcie', 'en' => 'Crack'],
            ['pl' => 'Plama', 'en' => 'Stain'],
            ['pl' => 'Wgniecenie', 'en' => 'Dent'],
            ['pl' => 'Brak elementu', 'en' => 'Missing part'],
            ['pl' => 'Zużycie', 'en' => 'Wear'],
            ['pl' => 'Odbarwienie', 'en' => 'Discoloration'],
            ['pl' => 'Wilgoć/pleśń', 'en' => 'Moisture/mold'],
            ['pl' => 'Uszkodzenie mechaniczne', 'en' => 'Mechanical damage'],
            ['pl' => 'Awaria', 'en' => 'Malfunction'],
        ];

        foreach ($defects as $index => $defect) {
            CatalogItem::updateOrCreate(
                ['type' => CatalogItemType::DEFECT, 'name->pl' => $defect['pl']],
                [
                    'type' => CatalogItemType::DEFECT,
                    'name' => $defect,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
