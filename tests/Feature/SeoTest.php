<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_portada_tiene_las_etiquetas_basicas()
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<title>Gimnasio en San Martín de Porres, Lima | DANJER FITNESS</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical" href="' . url('/') . '">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<html lang="es">', false);
    }

    public function test_la_portada_expone_open_graph_y_twitter()
    {
        $response = $this->get('/');

        foreach (['og:title', 'og:description', 'og:image', 'og:url', 'og:type', 'og:locale'] as $tag) {
            $response->assertSee('property="' . $tag . '"', false);
        }

        $response->assertSee('name="twitter:card"', false);
    }

    public function test_la_portada_lleva_datos_estructurados_validos()
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $data = json_decode(trim($m[1]), true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'El JSON-LD debe ser válido.');
        $this->assertSame('HealthAndBeautyBusiness', $data['@type']);
        $this->assertSame('DANJER FITNESS', $data['name']);
        $this->assertSame('PE', $data['address']['addressCountry']);
        $this->assertCount(3, $data['openingHoursSpecification']);
        $this->assertCount(3, $data['makesOffer']);
        $this->assertCount(5, $data['hasOfferCatalog']['itemListElement']);
    }

    public function test_la_portada_tiene_un_unico_h1()
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/i', $html), 'Debe haber exactamente un <h1>.');
    }

    public function test_login_y_register_no_se_indexan()
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false);
        }
    }

    public function test_los_dashboards_no_se_indexan()
    {
        $this->actingAs(User::where('email', 'cliente@gym.com')->firstOrFail());
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs(User::where('email', 'entrenador@gym.com')->firstOrFail());
        $this->get('/trainer/dashboard')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->actingAs(User::where('email', 'admin@gym.com')->firstOrFail());
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_bloquea_las_areas_privadas_y_apunta_al_sitemap()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Disallow: /admin/')
            ->assertSee('Allow: /css/')
            ->assertSee('Sitemap: ' . url('sitemap.xml'));
    }

    public function test_el_sitemap_es_xml_valido_y_lista_la_portada()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml');

        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml, 'El sitemap debe ser XML válido.');
        $this->assertSame(url('/'), (string) $xml->url[0]->loc);
    }
}
