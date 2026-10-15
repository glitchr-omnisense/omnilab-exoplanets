<?php

namespace Omnilab\Exoplanets\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\RateLimitedException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Exoplanets\ExoplanetsSource;
use Omnilab\Exoplanets\ExoplanetsSourceFactory;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from exoplanetarchive.ipac.caltech.edu on 2026-10-15:
 * the TAP answers (pscomppars) for 51 Peg b, Kepler-22 b, Kepler-62 f,
 * Proxima Cen b, OGLE-2014-BLG-0319L b, a name with no row, the search
 * for "trappist" (two pages) and the planets of Proxima Cen, the planets
 * found by transit in 2024 between 0.8 and 1.25 Earth radii, an ADQL
 * error (400, a VOTable); the alias service's answers for "Proxima
 * Centauri b", "Proxima Centauri", "Dimidium" and an unknown name.
 */
final class ExoplanetsSourceTest extends TestCase
{
    /** @var list<string> the ADQL queries asked, or "alias <name>" */
    private array $calls = [];

    private function source(?MockResponse $response = null, array $options = []): ExoplanetsSource
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $path = parse_url($url, \PHP_URL_PATH);
            if ('/cgi-bin/Lookup/nph-aliaslookup.py' === $path) {
                $this->calls[] = 'alias '.$query['objname'];
                $fixture = match ($query['objname']) {
                    'Proxima Centauri b' => 'alias-proxima-centauri-b.json',
                    'Proxima Centauri' => 'alias-proxima-centauri.json',
                    'Dimidium' => 'alias-dimidium.json',
                    default => 'alias-notfound.json',
                };

                return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture));
            }
            self::assertSame('/TAP/sync', $path);
            self::assertSame('json', $query['format']);
            $this->calls[] = $adql = $query['query'];
            if ($response) {
                return $response;
            }
            $fixture = match (true) {
                str_contains($adql, "= '51PEGB'"), str_contains($adql, "pl_name = '51 Peg b'") => 'tap-51-peg-b.json',
                str_contains($adql, "= 'KEPLER-22B'") => 'tap-kepler-22-b.json',
                str_contains($adql, "= 'KEPLER-62F'") => 'tap-kepler-62-f.json',
                str_contains($adql, "= 'OGLE-2014-BLG-0319LB'") => 'tap-ogle-2014-blg-0319l-b.json',
                str_contains($adql, "pl_name = 'Proxima Cen b'") => 'tap-proxima-cen-b.json',
                str_contains($adql, "LIKE '%TRAPPIST%'") => str_contains($adql, "pl_name > 'TRAPPIST-1 f'") ? 'tap-search-trappist-2.json' : 'tap-search-trappist-1.json',
                str_contains($adql, "(pl_name = 'Proxima Cen' OR hostname = 'Proxima Cen')") => 'tap-host-proxima-cen.json',
                str_contains($adql, "upper(discoverymethod) = 'TRANSIT'") => 'tap-criteria.json',
                default => 'tap-none.json',
            };

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture));
        });

        $source = (new ExoplanetsSourceFactory($http))->create($options);
        self::assertInstanceOf(ExoplanetsSource::class, $source);

        return $source;
    }

    public function testFiftyOnePegasiBByItsNameInOneQuery(): void
    {
        $planet = $this->source()->entity('51 Peg b');

        self::assertSame('simbad:51 Peg b', $planet->id->key(), 'the archive\'s name, in the simbad scheme (the core has no scheme of the archive)');
        self::assertSame('51 Peg b', $planet->label);
        self::assertSame(Domain::ASTRONOMY, $planet->domain);
        self::assertSame('exoplanet', $planet->kind);
        self::assertSame('https://exoplanetarchive.ipac.caltech.edu/overview/51%20Peg%20b', $planet->url);
        self::assertEquals(new Quantity(193.87532827, 'Earth Mass', 19.06970442), $planet->value('mass'), 'the larger of +19.07 and -15.89');
        self::assertSame('Mass', $planet->property('mass')->label);
        self::assertEquals(new Quantity(0.61, 'Jupiter Mass', 0.06), $planet->value('mass_jupiter'));
        self::assertEquals(new Quantity(14.1, 'Earth Radius'), $planet->value('radius'), 'no uncertainty given');
        self::assertSame('4.2307966 ± 0.0000027 days', (string) $planet->property('orbital_period'));
        self::assertEquals(new Quantity(0.052, 'au'), $planet->value('semi_major_axis'));
        self::assertEquals(new Quantity(1328.93, 'K'), $planet->value('equilibrium_temperature'));
        self::assertSame('Eccentricity (upper limit)', $planet->property('eccentricity')->label);
        self::assertSame('Radial Velocity', $planet->value('discovery_method'));
        self::assertSame(1995, $planet->value('discovery_year'));
        self::assertSame('1995-11', $planet->value('discovery_published'));
        self::assertSame('Haute-Provence Observatory', $planet->value('discovery_facility'));
        self::assertSame('ELODIE Spectrograph', $planet->value('discovery_instrument'));
        self::assertSame('Mayor & Queloz 1995', $planet->value('discovery_reference'));
        self::assertSame('https://ui.adsabs.harvard.edu/abs/1995Natur.378..355M/abstract', $planet->value('discovery_reference_url'));
        self::assertSame('G5V', $planet->value('host_spectral_type'));
        self::assertSame('5761 ± 95 K', (string) $planet->property('host_temperature'));
        self::assertSame('15.4614 ± 0.0297 pc', (string) $planet->property('distance'));
        self::assertEquals(new Quantity(344.3675399, 'deg'), $planet->value('ra'));
        self::assertSame('22h57m28.21s +20d46m08.74s', $planet->value('coordinates'));
        self::assertSame(1, $planet->value('planets'));
        self::assertFalse($planet->value('controversial'));
        self::assertSame('exoplanets', $planet->property('mass')->source);
        self::assertNull($planet->place, 'a position on the sky is not a place on the Earth');
        self::assertSame(['simbad:51 Peg b'], $planet->identifiers()->keys());
        self::assertSame(['simbad:51 Peg', 'simbad:HD 217014', 'simbad:HIP 113357', 'simbad:TIC 139298196', 'simbad:Gaia DR3 2835207319109249920'], $planet->related('host')->keys(), 'the host star by each of its designations');
        self::assertCount(1, $this->calls);
        self::assertStringStartsWith('SELECT TOP 1 pl_name, ', $this->calls[0]);
        self::assertStringEndsWith("FROM pscomppars WHERE upper(replace(pl_name, ' ', '')) = '51PEGB'", $this->calls[0]);
    }

    public function testSimbadsNameOfThePlanet(): void
    {
        $planet = $this->source()->entity(Identifier::of(Scheme::SIMBAD, '* 51 Peg b'));

        self::assertSame('simbad:51 Peg b', $planet->id->key());
        self::assertSame(['simbad:51 Peg b', 'simbad:* 51 Peg b'], $planet->identifiers()->keys(), 'SIMBAD\'s main identifier kept: the same planet');
        self::assertSame('51 Peg b', $this->source()->entity('simbad:* 51 Peg b')?->label);
    }

    public function testAStarsDesignationOrAnotherBaseIsRefusedBeforeAnyCall(): void
    {
        foreach ([Identifier::of(Scheme::SIMBAD, '* 51 Peg'), Identifier::of(Scheme::SIMBAD, 'HD 217014'), Identifier::of(Scheme::SIMBAD, 'Gaia DR3 2835207319109249920'), Identifier::of(Scheme::SIMBAD, '* alf Ori'), Identifier::of(Scheme::WIKIDATA, 'Q242309'), 'Q242309', 'jpl.sbdb:433'] as $id) {
            try {
                $this->source()->entity($id);
                self::fail(\sprintf('%s should be refused', $id));
            } catch (NotSupportedException) {
            }
        }
        self::assertSame([], $this->calls);
        self::assertTrue(ExoplanetsSource::isPlanetName('NAME Proxima Centauri b'));
        self::assertTrue(ExoplanetsSource::isPlanetName('Kepler-22b'));
        self::assertTrue(ExoplanetsSource::isPlanetName('TOI-4527.01'));
        self::assertTrue(ExoplanetsSource::isPlanetName('Kepler-1647 (AB) b'));
        self::assertFalse(ExoplanetsSource::isPlanetName('TIC 139298196'));
    }

    public function testANameSpacedOtherwise(): void
    {
        $planet = $this->source()->entity('Kepler-22b');

        self::assertSame('Kepler-22 b', $planet->label);
        self::assertSame('Mass (upper limit)', $planet->property('mass')->label);
        self::assertEquals(new Quantity(9.1, 'Earth Mass'), $planet->value('mass'), 'a limit has no uncertainty');
        self::assertSame('2.1 ± 0.12 Earth Radius', (string) $planet->property('radius'));
        self::assertSame(['simbad:Kepler-22', 'simbad:TIC 158984573', 'simbad:Gaia DR3 2127941757262806656'], $planet->related('host')->keys());
        self::assertCount(1, $this->calls);
    }

    public function testNamesTheAliasServiceResolves(): void
    {
        $proxima = $this->source()->entity('Proxima Centauri b');
        self::assertSame('Proxima Cen b', $proxima->label);
        self::assertSame('Minimum mass (M sin i)', $proxima->property('mass')->label);
        self::assertSame('Msini', $proxima->value('mass_provenance'));
        self::assertEquals(new Quantity(1.055, 'Earth Mass', 0.055), $proxima->value('mass'));
        self::assertSame(2016, $proxima->value('discovery_year'));
        self::assertContains('simbad:Gaia DR3 5853498713190525696', $proxima->related('host')->keys());
        self::assertSame('alias Proxima Centauri b', $this->calls[1]);
        self::assertStringEndsWith("WHERE pl_name = 'Proxima Cen b'", $this->calls[2]);
        self::assertCount(3, $this->calls);

        $this->calls = [];
        self::assertSame('51 Peg b', $this->source()->entity('Dimidium')?->label, 'its IAU name');
        self::assertCount(3, $this->calls);
    }

    public function testAnUnknownNameIsNull(): void
    {
        self::assertNull($this->source()->entity('Nothing Such 12 z'));
        self::assertSame('alias Nothing Such 12 z', $this->calls[1]);
        self::assertCount(2, $this->calls);
    }

    public function testAMicrolensingPlanetHasOnlyItsHostsName(): void
    {
        $planet = $this->source()->entity('OGLE-2014-BLG-0319L b');

        self::assertSame('Microlensing', $planet->value('discovery_method'));
        self::assertSame(['simbad:OGLE-2014-BLG-0319L'], $planet->related('host')->keys());
        self::assertNull($planet->property('orbital_period'));
        self::assertEquals(7730, $planet->property('distance')->number());
    }

    public function testASearchByNameInPages(): void
    {
        $source = $this->source();
        $first = $source->search(new Query('trappist', limit: 5));
        self::assertSame(['TRAPPIST-1 b', 'TRAPPIST-1 c', 'TRAPPIST-1 d', 'TRAPPIST-1 e', 'TRAPPIST-1 f'], array_map(static fn ($p) => $p->label, $first->items));
        self::assertSame('TRAPPIST-1 f', $first->next);
        self::assertNull($first->total);
        self::assertStringContainsString("SELECT TOP 6 ", $this->calls[0], 'one more than the page, to know there is a next');
        self::assertStringEndsWith("WHERE (upper(replace(pl_name, ' ', '')) LIKE '%TRAPPIST%' OR upper(replace(hostname, ' ', '')) LIKE '%TRAPPIST%') ORDER BY pl_name", $this->calls[0]);

        $second = $source->search(new Query('trappist', limit: 5, cursor: $first->next));
        self::assertSame(['TRAPPIST-1 g', 'TRAPPIST-1 h'], array_map(static fn ($p) => $p->label, $second->items));
        self::assertNull($second->next);
        self::assertStringContainsString("AND pl_name > 'TRAPPIST-1 f' ORDER BY pl_name", $this->calls[1]);
    }

    public function testASearchFallsBackOnTheAliasService(): void
    {
        $page = $this->source()->search(new Query('Proxima Centauri'));

        self::assertSame(['Proxima Cen b', 'Proxima Cen d'], array_map(static fn ($p) => $p->label, $page->items), 'the planets of the star the name resolves to');
        self::assertSame(2, $page->total);
        self::assertSame('alias Proxima Centauri', $this->calls[1]);
        self::assertCount(3, $this->calls);

        $this->calls = [];
        $none = $this->source()->search(new Query('nothing such'));
        self::assertCount(0, $none);
        self::assertSame(0, $none->total);

        $this->calls = [];
        self::assertCount(0, $this->source()->search(new Query('51 Peg b', Domain::CHEMISTRY)));
        self::assertCount(0, $this->source()->search(new Query('  ')));
        self::assertSame([], $this->calls);
    }

    public function testPlanetsBySimpleCriteria(): void
    {
        $page = $this->source()->planets(['method' => 'Transit', 'year' => 2024, 'min_radius' => 0.8, 'max_radius' => 1.25], 10);

        self::assertStringEndsWith("FROM pscomppars WHERE upper(discoverymethod) = 'TRANSIT' AND disc_year = 2024 AND pl_rade >= 0.8 AND pl_rade <= 1.25 ORDER BY pl_name", $this->calls[0]);
        self::assertCount(10, $page);
        self::assertSame('Gliese 12 b', $page->first()->label);
        self::assertSame('TOI-6255 b', $page->next);
        self::assertSame('Mass (estimated from the radius)', $page->items[1]->property('mass')->label, 'SPECULOOS-3 b: its mass from a mass-radius relationship');
        self::assertSame('M-R relationship', $page->items[1]->value('mass_provenance'));
        self::assertSame('Minimum mass (M sin i)', $page->items[3]->property('mass')->label, 'TOI-1450 A c');
        self::assertSame('TOI-4527.01', $page->items[5]->label);
        foreach ($page as $planet) {
            self::assertSame(2024, $planet->value('discovery_year'));
            self::assertGreaterThanOrEqual(0.8, $planet->property('radius')->number());
            self::assertLessThanOrEqual(1.25, $planet->property('radius')->number());
        }
    }

    public function testUnknownCriteriaAreRefusedBeforeAnyCall(): void
    {
        try {
            $this->source()->planets(['colour' => 'blue']);
            self::fail('an unknown key');
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('"colour"', $e->getMessage());
        }
        try {
            $this->source()->planets(['min_radius' => 'large']);
            self::fail('a text for a number');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame([], $this->calls);
    }

    public function testTheArchivesErrors(): void
    {
        try {
            $this->source(new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/tap-error.xml'), ['http_code' => 400]))->entity('51 Peg b');
            self::fail('a 400');
        } catch (ProviderException $e) {
            self::assertStringContainsString('ORA-00933', $e->getMessage());
        }
        $this->expectException(UnavailableException::class);
        $this->source(new MockResponse('', ['http_code' => 503]))->entity('51 Peg b');
    }

    public function testRateLimited(): void
    {
        $this->expectException(RateLimitedException::class);
        $this->source(new MockResponse('', ['http_code' => 429]))->entity('51 Peg b');
    }

    public function testTheFactory(): void
    {
        $sent = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = $options['normalized_headers']['user-agent'][0] ?? '';

            return new MockResponse('[]');
        });
        $factory = new ExoplanetsSourceFactory($http);
        $source = $factory->create(['mailto' => 'support@glitchr.io']);

        self::assertSame('exoplanets', $factory->getName());
        self::assertSame('exoplanets', $source->getName());
        self::assertSame([Capability::ENTITY, Capability::SEARCH], $source->capabilities());
        $source->entity('51 Peg b');
        self::assertStringContainsString('mailto:support@glitchr.io', $sent);
    }
}
