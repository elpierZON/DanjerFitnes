{{-- Ficha del gimnasio para resultados enriquecidos de Google y Google Maps.
     Los datos deben coincidir exactamente con los de la sección Contacto. --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'HealthAndBeautyBusiness',
    '@id' => url('/') . '#gimnasio',
    'name' => 'DANJER FITNESS',
    'description' => 'Gimnasio en San Martín de Porres, Lima. Clases de aeróbicos, HIIT, fitness de combate, funcional y musculación con reserva online.',
    'url' => url('/'),
    'logo' => asset('images/logoDanjer.png'),
    'image' => asset('images/logoDanjer.png'),
    'telephone' => '+51987654321',
    'email' => 'info@danjerfitness.com',
    'priceRange' => '$29 - $79',
    'currenciesAccepted' => 'USD',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Av. Francisco Luna Pizarro 108-A',
        'addressLocality' => 'San Martín de Porres',
        'addressRegion' => 'Lima',
        'addressCountry' => 'PE',
    ],
    'openingHoursSpecification' => [
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => '06:00',
            'closes' => '22:00',
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Saturday',
            'opens' => '08:00',
            'closes' => '20:00',
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Sunday',
            'opens' => '09:00',
            'closes' => '14:00',
        ],
    ],
    // Precios tal como aparecen en la sección Planes de la página.
    'makesOffer' => collect([
        ['name' => 'Básico', 'price' => '29'],
        ['name' => 'Premium', 'price' => '49'],
        ['name' => 'Elite', 'price' => '79'],
    ])->map(fn($plan) => [
        '@type' => 'Offer',
        'name' => "Membresía {$plan['name']}",
        'price' => $plan['price'],
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
        'url' => url('/') . '#planes',
    ])->all(),
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name' => 'Clases',
        'itemListElement' => collect([
            'Aeróbicos', 'Fitness de Combate', 'HIIT', 'Funcional', 'Musculación',
        ])->map(fn($name) => [
            '@type' => 'Offer',
            'itemOffered' => ['@type' => 'Service', 'name' => $name, 'serviceType' => 'Clase dirigida'],
        ])->all(),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
