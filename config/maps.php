<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Map Provider URLs
    |--------------------------------------------------------------------------
    */

    'default_zoom' => 8,

    'nominatim_url' => 'https://nominatim.openstreetmap.org',
    'overpass_url' => 'https://overpass-api.de/api/interpreter',
    'photon_url' => 'https://photon.komoot.io',

    /*
    |--------------------------------------------------------------------------
    | Google Place Type → OSM Overpass Tag mapping
    |--------------------------------------------------------------------------
    | Used by the data migration to backfill osm_place_type for existing
    | categories. Values use comma-separated key=value pairs for compound tags
    | (e.g. "amenity=place_of_worship,religion=hindu").
    */

    'google_to_osm' => [
        // Transport
        'gas_station' => 'amenity=fuel',
        'airport' => 'aeroway=aerodrome',
        'bus_station' => 'amenity=bus_station',
        'train_station' => 'railway=station',
        'subway_station' => 'railway=subway_station',
        'light_rail_station' => 'railway=tram_stop',
        'taxi_stand' => 'amenity=taxi',
        'parking' => 'amenity=parking',

        // Food & Drink
        'restaurant' => 'amenity=restaurant',
        'cafe' => 'amenity=cafe',
        'bar' => 'amenity=bar',
        'bakery' => 'shop=bakery',
        'fast_food_restaurant' => 'amenity=fast_food',
        'food_court' => 'amenity=food_court',
        'ice_cream_shop' => 'amenity=ice_cream',

        // Health
        'hospital' => 'amenity=hospital',
        'pharmacy' => 'amenity=pharmacy',
        'dentist' => 'amenity=dentist',
        'doctor' => 'amenity=doctors',
        'emergency_room_hospital' => 'amenity=hospital',

        // Shopping
        'shopping_mall' => 'shop=mall',
        'supermarket' => 'shop=supermarket',
        'convenience_store' => 'shop=convenience',
        'clothing_store' => 'shop=clothes',
        'book_store' => 'shop=books',

        // Entertainment
        'movie_theater' => 'amenity=cinema',
        'museum' => 'tourism=museum',
        'art_gallery' => 'tourism=gallery',
        'zoo' => 'tourism=zoo',
        'amusement_park' => 'tourism=theme_park',
        'tourist_attraction' => 'tourism=attraction',
        'national_park' => 'boundary=national_park',
        'historical_landmark' => 'historic=monument',

        // Religion
        'place_of_worship' => 'amenity=place_of_worship',
        'mosque' => 'amenity=place_of_worship,religion=muslim',
        'hindu_temple' => 'amenity=place_of_worship,religion=hindu',
        'church' => 'amenity=place_of_worship,religion=christian',
        'synagogue' => 'amenity=place_of_worship,religion=jewish',

        // Sports & Wellness
        'park' => 'leisure=park',
        'gym' => 'leisure=fitness_centre',
        'swimming_pool' => 'leisure=swimming_pool',
        'sports_club' => 'leisure=sports_centre',
        'spa' => 'leisure=spa',

        // Services
        'atm' => 'amenity=atm',
        'bank' => 'amenity=bank',
        'post_office' => 'amenity=post_office',
        'police' => 'amenity=police',
        'school' => 'amenity=school',
        'university' => 'amenity=university',
        'library' => 'amenity=library',
    ],

    /*
    |--------------------------------------------------------------------------
    | OSM Place Type options for the admin category form dropdown
    |--------------------------------------------------------------------------
    | Grouped the same way as Google types for intuitive side-by-side setup.
    | Compound tags use comma-separated key=value pairs parsed by
    | OpenStreetMapService when building Overpass queries.
    */

    'osm_types' => [
        'Transport' => [
            'amenity=fuel' => 'Gas Station',
            'aeroway=aerodrome' => 'Airport',
            'amenity=bus_station' => 'Bus Station',
            'railway=station' => 'Train Station',
            'railway=subway_station' => 'Subway Station',
            'railway=tram_stop' => 'Light Rail Station',
            'amenity=taxi' => 'Taxi Stand',
            'amenity=parking' => 'Parking',
        ],
        'Food & Drink' => [
            'amenity=restaurant' => 'Restaurant',
            'amenity=cafe' => 'Cafe',
            'amenity=bar' => 'Bar',
            'shop=bakery' => 'Bakery',
            'amenity=fast_food' => 'Fast Food Restaurant',
            'amenity=food_court' => 'Food Court',
            'amenity=ice_cream' => 'Ice Cream Shop',
        ],
        'Health' => [
            'amenity=hospital' => 'Hospital',
            'amenity=pharmacy' => 'Pharmacy / Medical Shop',
            'amenity=dentist' => 'Dentist',
            'amenity=doctors' => 'Doctor',
        ],
        'Shopping' => [
            'shop=mall' => 'Shopping Mall',
            'shop=supermarket' => 'Supermarket',
            'shop=convenience' => 'Convenience Store',
            'shop=clothes' => 'Clothing Store',
            'shop=books' => 'Book Store',
        ],
        'Entertainment' => [
            'amenity=cinema' => 'Movie Theater',
            'tourism=museum' => 'Museum',
            'tourism=gallery' => 'Art Gallery',
            'tourism=zoo' => 'Zoo',
            'tourism=theme_park' => 'Amusement Park',
            'tourism=attraction' => 'Tourist Attraction',
            'boundary=national_park' => 'National Park',
            'historic=monument' => 'Historical Landmark',
        ],
        'Religion' => [
            'amenity=place_of_worship' => 'Place of Worship (All)',
            'amenity=place_of_worship,religion=muslim' => 'Mosque',
            'amenity=place_of_worship,religion=hindu' => 'Hindu Temple',
            'amenity=place_of_worship,religion=christian' => 'Church',
            'amenity=place_of_worship,religion=jewish' => 'Synagogue',
            'amenity=place_of_worship,religion=buddhist' => 'Buddhist Temple',
        ],
        'Sports & Wellness' => [
            'leisure=park' => 'Park',
            'leisure=fitness_centre' => 'Gym',
            'leisure=swimming_pool' => 'Swimming Pool',
            'leisure=sports_centre' => 'Sports Club',
            'leisure=spa' => 'Spa',
        ],
        'Services' => [
            'amenity=atm' => 'ATM',
            'amenity=bank' => 'Bank',
            'amenity=post_office' => 'Post Office',
            'amenity=police' => 'Police',
            'amenity=school' => 'School',
            'amenity=university' => 'University',
            'amenity=library' => 'Library',
        ],
    ],

];
