<?php

namespace Database\Seeders;

use App\Modules\Localization\Infrastructure\Models\Locale;
use Illuminate\Database\Seeder;

class LocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            [
                'code' => 'pl',
                'name' => 'Polish',
                'native_name' => 'Polski',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($locales as $locale) {
            Locale::updateOrCreate(
                ['code' => $locale['code']],
                $locale
            );
        }
    }
}
