<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Branding
            ['key' => 'site_name',       'value' => 'Farmmantra Agro Chemicals',                      'group' => 'branding'],
            ['key' => 'site_tagline',    'value' => 'Growing Uganda, One Farm at a Time',              'group' => 'branding'],
            ['key' => 'logo_url',        'value' => null,                                              'group' => 'branding'],
            ['key' => 'favicon_url',     'value' => null,                                              'group' => 'branding'],
            ['key' => 'primary_color',   'value' => '#2E7D32',                                         'group' => 'branding'],
            ['key' => 'secondary_color', 'value' => '#1B5E20',                                         'group' => 'branding'],

            // Contact
            ['key' => 'contact_email',   'value' => 'info@farmmantra.co.ug',                           'group' => 'contact'],
            ['key' => 'contact_phone',   'value' => '+256 700 000001',                                  'group' => 'contact'],
            ['key' => 'address',         'value' => 'Kampala, Uganda',                                 'group' => 'contact'],

            // Finance
            ['key' => 'currency_symbol', 'value' => 'UGX',                                             'group' => 'finance'],
            ['key' => 'currency_code',   'value' => 'UGX',                                             'group' => 'finance'],
        ];

        foreach ($defaults as $s) {
            Setting::firstOrCreate(
                ['key' => $s['key']],
                ['value' => $s['value'], 'group_name' => $s['group']]
            );
        }
    }
}
