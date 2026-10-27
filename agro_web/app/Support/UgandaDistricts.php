<?php

namespace App\Support;

/**
 * Districts of Uganda grouped by official region. Used to seed the districts
 * table; administrators can add newly gazetted districts from the admin panel.
 */
class UgandaDistricts
{
    public static function all(): array
    {
        return [
            'Central' => [
                'Buikwe', 'Bukomansimbi', 'Butambala', 'Buvuma', 'Gomba', 'Kalangala', 'Kalungu',
                'Kampala', 'Kassanda', 'Kayunga', 'Kiboga', 'Kyankwanzi', 'Kyotera', 'Luwero',
                'Lwengo', 'Lyantonde', 'Masaka', 'Mityana', 'Mpigi', 'Mubende', 'Mukono',
                'Nakaseke', 'Nakasongola', 'Rakai', 'Sembabule', 'Wakiso',
            ],
            'Eastern' => [
                'Amuria', 'Budaka', 'Bududa', 'Bugiri', 'Bugweri', 'Bukedea', 'Bukwo', 'Bulambuli',
                'Busia', 'Butaleja', 'Butebo', 'Buyende', 'Iganga', 'Jinja', 'Kaberamaido', 'Kalaki',
                'Kaliro', 'Kamuli', 'Kapchorwa', 'Kapelebyong', 'Katakwi', 'Kibuku', 'Kumi', 'Kween',
                'Luuka', 'Manafwa', 'Mayuge', 'Mbale', 'Namayingo', 'Namisindwa', 'Namutumba',
                'Ngora', 'Pallisa', 'Serere', 'Sironko', 'Soroti', 'Tororo',
            ],
            'Northern' => [
                'Abim', 'Adjumani', 'Agago', 'Alebtong', 'Amolatar', 'Amudat', 'Amuru', 'Apac', 'Arua',
                'Dokolo', 'Gulu', 'Kaabong', 'Karenga', 'Kitgum', 'Koboko', 'Kole', 'Kotido', 'Kwania',
                'Lamwo', 'Lira', 'Madi-Okollo', 'Maracha', 'Moroto', 'Moyo', 'Nabilatuk', 'Nakapiripirit',
                'Napak', 'Nebbi', 'Nwoya', 'Obongi', 'Omoro', 'Otuke', 'Oyam', 'Pader', 'Pakwach',
                'Terego', 'Yumbe', 'Zombo',
            ],
            'Western' => [
                'Buhweju', 'Buliisa', 'Bundibugyo', 'Bunyangabu', 'Bushenyi', 'Hoima', 'Ibanda',
                'Isingiro', 'Kabale', 'Kabarole', 'Kagadi', 'Kakumiro', 'Kamwenge', 'Kanungu', 'Kasese',
                'Kazo', 'Kibaale', 'Kikuube', 'Kiruhura', 'Kiryandongo', 'Kisoro', 'Kitagwenda',
                'Kyegegwa', 'Kyenjojo', 'Masindi', 'Mbarara', 'Mitooma', 'Ntoroko', 'Ntungamo',
                'Rubanda', 'Rubirizi', 'Rukiga', 'Rukungiri', 'Rwampara', 'Sheema',
            ],
        ];
    }
}
