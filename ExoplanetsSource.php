<?php

namespace Omnilab\Exoplanets;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The NASA Exoplanet Archive (Caltech/IPAC), through its TAP service in
 * ADQL: a confirmed planet by its name ("51 Peg b", "Kepler-22b",
 * "Proxima Centauri b", "Dimidium" - the archive's alias service resolves
 * the names its table does not hold), from the Planetary Systems Composite
 * Parameters table (pscomppars: one row a planet, each parameter from the
 * reference the archive chose): its mass, radius, density, orbit and
 * equilibrium temperature with their units and uncertainties, how, when
 * and by which facility it was found, its host star's parameters and the
 * system's position, distance and magnitudes; the host star as a related
 * thing, by every designation the archive gives it (its name, HD, HIP,
 * TIC, Gaia DR3), which SIMBAD and omnilab/gaia read; a search by name,
 * and planets() by simple criteria (outside the contract).
 *
 * The core has no scheme for the archive's names: a planet is named in the
 * simbad scheme, a name SIMBAD resolves for the planets it lists (51 Peg b,
 * Kepler-22b, TOI-700 d...) - see docs/index.md.
 *
 * No key, no account, no published rate: no throttle by default. One call
 * an entity when the name is the archive's, three when its alias service
 * resolves it.
 */
final class ExoplanetsSource extends HttpSource
{
    public const BASE_URI = 'https://exoplanetarchive.ipac.caltech.edu/';

    /** The most planets a page holds. */
    public const MAX_LIMIT = 500;

    /** The archive's discovery methods, as it writes them. */
    public const METHODS = ['Astrometry', 'Disk Kinematics', 'Eclipse Timing Variations', 'Imaging', 'Microlensing', 'Orbital Brightness Modulation', 'Pulsar Timing', 'Pulsation Timing Variations', 'Radial Velocity', 'Transit', 'Transit Timing Variations'];

    /** What planets() filters on => [column, comparison]. */
    public const CRITERIA = [
        'host' => ['hostname', 'name'],
        'method' => ['discoverymethod', 'text'],
        'facility' => ['disc_facility', 'like'],
        'year' => ['disc_year', '='],
        'from_year' => ['disc_year', '>='],
        'to_year' => ['disc_year', '<='],
        'min_mass' => ['pl_bmasse', '>='],
        'max_mass' => ['pl_bmasse', '<='],
        'min_radius' => ['pl_rade', '>='],
        'max_radius' => ['pl_rade', '<='],
        'min_period' => ['pl_orbper', '>='],
        'max_period' => ['pl_orbper', '<='],
        'min_temperature' => ['pl_eqt', '>='],
        'max_temperature' => ['pl_eqt', '<='],
        'max_distance' => ['sy_dist', '<='],
    ];

    /**
     * The measured columns: key => [column, label, unit, has uncertainties, has a limit flag].
     * The units are the archive's (TAP_SCHEMA.columns), "**" written as an exponent.
     */
    private const MEASURES = [
        'mass' => ['pl_bmasse', 'Mass', 'Earth Mass', true, true],
        'mass_jupiter' => ['pl_bmassj', 'Mass', 'Jupiter Mass', true, true],
        'radius' => ['pl_rade', 'Radius', 'Earth Radius', true, true],
        'radius_jupiter' => ['pl_radj', 'Radius', 'Jupiter Radius', true, true],
        'density' => ['pl_dens', 'Density', 'g/cm³', true, true],
        'orbital_period' => ['pl_orbper', 'Orbital period', 'days', true, true],
        'semi_major_axis' => ['pl_orbsmax', 'Semi-major axis', 'au', true, true],
        'eccentricity' => ['pl_orbeccen', 'Eccentricity', null, true, true],
        'inclination' => ['pl_orbincl', 'Inclination', 'deg', true, true],
        'equilibrium_temperature' => ['pl_eqt', 'Equilibrium temperature', 'K', true, true],
        'insolation' => ['pl_insol', 'Insolation flux', 'Earth Flux', true, true],
        'host_temperature' => ['st_teff', 'Host star: effective temperature', 'K', true, true],
        'host_mass' => ['st_mass', 'Host star: mass', 'Solar mass', true, true],
        'host_radius' => ['st_rad', 'Host star: radius', 'Solar Radius', true, true],
        'host_metallicity' => ['st_met', 'Host star: metallicity', 'dex', true, true],
        'host_luminosity' => ['st_lum', 'Host star: luminosity', 'log(Solar)', true, true],
        'host_surface_gravity' => ['st_logg', 'Host star: surface gravity', 'log10(cm/s²)', true, true],
        'host_age' => ['st_age', 'Host star: age', 'Gyr', true, true],
        'distance' => ['sy_dist', 'Distance of the system', 'pc', true, false],
        'parallax' => ['sy_plx', 'Parallax of the system', 'mas', true, false],
        'ra' => ['ra', 'Right ascension of the system', 'deg', false, false],
        'dec' => ['dec', 'Declination of the system', 'deg', false, false],
        'host_magnitude_V' => ['sy_vmag', 'Host star: V magnitude (Johnson)', 'mag', true, false],
        'host_magnitude_K' => ['sy_kmag', 'Host star: Ks magnitude (2MASS)', 'mag', true, false],
        'host_magnitude_G' => ['sy_gaiamag', 'Host star: G magnitude (Gaia)', 'mag', true, false],
    ];

    /** The host star's designations the archive gives, each a name SIMBAD resolves. */
    private const HOST_NAMES = ['hostname', 'hd_name', 'hip_name', 'tic_id', 'gaia_dr3_id'];

    private const OTHER_COLUMNS = ['pl_name', 'pl_letter', 'pl_bmassprov', 'discoverymethod', 'disc_year', 'disc_pubdate', 'disc_facility', 'disc_telescope', 'disc_instrument', 'disc_refname', 'st_spectype', 'rastr', 'decstr', 'sy_snum', 'sy_pnum', 'sy_mnum', 'pl_controv_flag', 'cb_flag'];

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'exoplanets';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH];
    }

    /**
     * A planet by its name: the archive's ("51 Peg b"), spaced otherwise
     * ("Kepler-22b"), SIMBAD's ("* 51 Peg b", an Identifier of the simbad
     * scheme), or another the archive's alias service knows ("Proxima
     * Centauri b", "Dimidium"). An Identifier of the simbad scheme that
     * does not end in a planet's letter (a star: "* 51 Peg", "HD 217014",
     * "Gaia DR3 ...") is refused before any call; null when the archive
     * lists no confirmed planet of that name.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $asked = null;
        if ($id instanceof Identifier) {
            if (Scheme::SIMBAD !== $id->scheme || !self::isPlanetName($id->value)) {
                throw NotSupportedException::identifier('exoplanets', (string) $id);
            }
            $asked = $id;
            $name = $id->value;
        } else {
            $parsed = Identifier::parse($id);
            if (null !== $parsed && Scheme::SIMBAD !== $parsed->scheme) {
                // Another base's identifier (a Wikidata item, a CAS number...): the archive would not know it.
                throw NotSupportedException::identifier('exoplanets', $id);
            }
            $asked = $parsed;
            $name = $parsed?->value ?? trim((string) preg_replace('~\s+~u', ' ', $id));
        }
        $name = self::unprefixed($name);
        if ('' === $name) {
            return null;
        }

        $row = $this->rows('upper(replace(pl_name, \' \', \'\')) = '.self::quote(self::key($name)), 1)[0] ?? null;
        if (null === $row) {
            $resolved = $this->resolve($name);
            if (null === $resolved || self::key($resolved['name']) === self::key($name)) {
                return null;
            }
            $row = $this->rows('pl_name = '.self::quote($resolved['name']), 1)[0] ?? null;
        }

        return null === $row ? null : $this->toEntity($row, $asked);
    }

    /**
     * The planets whose name, or their host star's, holds the text
     * ("trappist", "51 peg", "Kepler-22b": spaces and case aside), in the
     * order of their names; when none does, the name the archive's alias
     * service resolves the text to - a planet, or the planets of a star
     * ("Proxima Centauri": Proxima Cen b and d). Another domain than
     * astronomy answers nothing.
     */
    public function search(Query $query): Page
    {
        if (null !== $query->domain && Domain::ASTRONOMY !== $query->domain) {
            return new Page();
        }
        $text = self::unprefixed(trim((string) preg_replace('~\s+~u', ' ', $query->text)));
        if ('' === $text) {
            return new Page();
        }
        $like = self::quote('%'.str_replace(['%', '_'], '', self::key($text)).'%');
        $page = $this->page("(upper(replace(pl_name, ' ', '')) LIKE $like OR upper(replace(hostname, ' ', '')) LIKE $like)", $query->limit, $query->cursor);
        if (null !== $query->cursor || \count($page) > 0) {
            return $page;
        }

        $resolved = $this->resolve($text);
        if (null === $resolved) {
            return $page;
        }

        return $this->page('(pl_name = '.self::quote($resolved['name']).' OR hostname = '.self::quote($resolved['name']).')', $query->limit);
    }

    /**
     * The planets that meet every criterion, in the order of their names,
     * one page at a time (outside the contract: the core's Query is a
     * text). Unknown keys are refused before any call.
     *
     *   $archive->planets(['method' => 'Transit', 'year' => 2024, 'min_radius' => 0.8, 'max_radius' => 1.25]);
     *
     * Keys: host (the host star's name, spaces and case aside), method (one
     * of METHODS), facility (a part of its name), year, from_year, to_year
     * (of discovery), min_/max_mass (Earth masses, or M sin i),
     * min_/max_radius (Earth radii), min_/max_period (days),
     * min_/max_temperature (equilibrium, K), max_distance (pc). A planet
     * whose value is unknown does not meet a criterion on it.
     *
     * @param array<string, int|float|string> $criteria
     * @param string|null                     $cursor   the Page::$next of the page before
     *
     * @return Page<Entity>
     */
    public function planets(array $criteria = [], int $limit = 25, ?string $cursor = null): Page
    {
        $where = [];
        foreach ($criteria as $key => $value) {
            [$column, $comparison] = self::CRITERIA[$key] ?? throw NotSupportedException::operation('exoplanets', \sprintf('select planets by "%s" (it reads "%s")', $key, implode('", "', array_keys(self::CRITERIA))));
            if (null === $value || '' === $value) {
                continue;
            }
            $where[] = match ($comparison) {
                'name' => "upper(replace($column, ' ', '')) = ".self::quote(self::key((string) $value)),
                'text' => "upper($column) = ".self::quote(mb_strtoupper(trim((string) $value))),
                'like' => "upper($column) LIKE ".self::quote('%'.str_replace(['%', '_'], '', mb_strtoupper(trim((string) $value))).'%'),
                default => is_numeric($value) ? "$column $comparison ".(0 + $value) : throw new \InvalidArgumentException(\sprintf('"%s" takes a number, not "%s".', $key, $value)),
            };
        }

        return $this->page($where ? implode(' AND ', $where) : null, $limit, $cursor);
    }

    /**
     * A page of planets ordered by name, cut by the name of the last one
     * before (ADQL here has no OFFSET: the archive's Oracle refuses it).
     *
     * @return Page<Entity>
     */
    private function page(?string $where, int $limit, ?string $cursor = null): Page
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $conditions = array_filter([$where, null === $cursor || '' === $cursor ? null : 'pl_name > '.self::quote($cursor)]);
        $rows = $this->rows($conditions ? implode(' AND ', $conditions) : null, $limit + 1, 'pl_name');
        $more = \count($rows) > $limit;
        $rows = \array_slice($rows, 0, $limit);
        $items = array_map(fn (array $row) => $this->toEntity($row), $rows);

        return new Page(
            $items,
            $more ? (string) $rows[$limit - 1]['pl_name'] : null,
            !$more && (null === $cursor || '' === $cursor) ? \count($items) : null,
        );
    }

    /**
     * The rows of pscomppars that meet a condition.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(?string $where, int $top, ?string $order = null): array
    {
        $columns = [...self::OTHER_COLUMNS, ...self::HOST_NAMES];
        foreach (self::MEASURES as [$column, , , $errors, $limit]) {
            $columns[] = $column;
            if ($errors) {
                array_push($columns, $column.'err1', $column.'err2');
            }
            if ($limit) {
                $columns[] = $column.'lim';
            }
        }
        $adql = 'SELECT TOP '.$top.' '.implode(', ', array_unique($columns)).' FROM pscomppars'
            .(null === $where ? '' : ' WHERE '.$where)
            .(null === $order ? '' : ' ORDER BY '.$order);
        $rows = $this->getJson('TAP/sync', ['query' => $adql, 'format' => 'json']) ?? [];

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * What the archive's alias service resolves a name to: a planet's or a
     * star's name as the archive writes it, and its system; null when it
     * knows no system of that name.
     *
     * @return array{name: string, system: string|null}|null
     */
    private function resolve(string $name): ?array
    {
        $manifest = ($this->getJson('cgi-bin/Lookup/nph-aliaslookup.py', ['objname' => $name]) ?? [])['manifest'] ?? [];
        $resolved = self::text($manifest['resolved_name'] ?? null);

        return 'OK' === ($manifest['lookup_status'] ?? null) && null !== $resolved
            ? ['name' => $resolved, 'system' => self::text($manifest['system_name'] ?? null)]
            : null;
    }

    /** @param array<string, mixed> $row */
    private function toEntity(array $row, ?Identifier $asked = null): Entity
    {
        $name = (string) $row['pl_name'];
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'exoplanets');
            }
        };

        // What the mass is: measured, a minimum (radial velocity alone), or estimated from the radius (3,032 of the 6,445 planets on 2026-10-15).
        $provenance = match ($row['pl_bmassprov'] ?? null) {
            'Msini' => 'Minimum mass (M sin i)',
            'M-R relationship' => 'Mass (estimated from the radius)',
            default => 'Mass',
        };
        foreach (self::MEASURES as $key => [$column, $label, $unit, $errors, $limit]) {
            $value = $row[$column] ?? null;
            if (!is_numeric($value)) {
                continue;
            }
            if (str_starts_with($key, 'mass')) {
                $label = $provenance;
            }
            $flag = $limit ? (int) ($row[$column.'lim'] ?? 0) : 0;
            $uncertainty = $errors && 0 === $flag ? self::uncertainty($row[$column.'err1'] ?? null, $row[$column.'err2'] ?? null) : null;
            $add($key, $label.match ($flag) {
                1 => ' (upper limit)',
                -1 => ' (lower limit)',
                default => '',
            }, new Quantity(0 + $value, $unit, $uncertainty));
        }
        $add('mass_provenance', 'Mass provenance', self::text($row['pl_bmassprov'] ?? null));
        $add('discovery_method', 'Discovery method', self::text($row['discoverymethod'] ?? null));
        $add('discovery_year', 'Discovery year', is_numeric($row['disc_year'] ?? null) ? (int) $row['disc_year'] : null);
        $add('discovery_published', 'Discovery published', self::text($row['disc_pubdate'] ?? null));
        $add('discovery_facility', 'Discovery facility', self::text($row['disc_facility'] ?? null));
        $add('discovery_telescope', 'Discovery telescope', self::text($row['disc_telescope'] ?? null));
        $add('discovery_instrument', 'Discovery instrument', self::text($row['disc_instrument'] ?? null));
        $reference = (string) ($row['disc_refname'] ?? '');
        $add('discovery_reference', 'Discovery reference', self::text($reference));
        $add('discovery_reference_url', 'Discovery reference (link)', preg_match('~href=["\']?(https?://[^\s"\'>]+)~i', $reference, $m) ? $m[1] : null);
        $add('host_spectral_type', 'Host star: spectral type', self::text($row['st_spectype'] ?? null));
        $add('coordinates', 'Coordinates of the system (sexagesimal)', null === ($row['rastr'] ?? null) ? null : trim($row['rastr'].' '.($row['decstr'] ?? '')));
        $add('stars', 'Stars in the system', is_numeric($row['sy_snum'] ?? null) ? (int) $row['sy_snum'] : null);
        $add('planets', 'Planets in the system', is_numeric($row['sy_pnum'] ?? null) ? (int) $row['sy_pnum'] : null);
        $add('moons', 'Moons in the system', is_numeric($row['sy_mnum'] ?? null) && (int) $row['sy_mnum'] > 0 ? (int) $row['sy_mnum'] : null);
        $add('controversial', 'Controversial', isset($row['pl_controv_flag']) ? 1 === (int) $row['pl_controv_flag'] : null);
        $add('circumbinary', 'Orbits two stars', isset($row['cb_flag']) ? 1 === (int) $row['cb_flag'] : null);

        // The host star: each of its designations a name SIMBAD resolves, its Gaia DR3 one what omnilab/gaia reads.
        $host = new Identifiers(array_map(static fn (string $column) => Identifier::tryOf(Scheme::SIMBAD, self::text($row[$column] ?? null)), self::HOST_NAMES));
        $id = Identifier::of(Scheme::SIMBAD, $name);

        return new Entity(
            id: $id,
            label: $name,
            domain: Domain::ASTRONOMY,
            kind: 'exoplanet',
            properties: $properties,
            // The name SIMBAD's main identifier gives it ("* 51 Peg b"), when that was asked: the same planet.
            identifiers: new Identifiers([null !== $asked && !$asked->equals($id) ? $asked : null]),
            url: self::BASE_URI.'overview/'.rawurlencode($name),
            source: 'exoplanets',
            related: \count($host) > 0 ? ['host' => $host] : [],
        );
    }

    /** The larger of an upper and a lower uncertainty (the archive gives both; a Quantity holds one); null when neither is known. */
    private static function uncertainty(mixed $upper, mixed $lower): ?float
    {
        $values = array_map(static fn ($v) => abs((float) $v), array_filter([$upper, $lower], 'is_numeric'));

        return $values ? max($values) : null;
    }

    /**
     * Whether a name ends as a planet's does: in its letter, "51 Peg b",
     * "Kepler-22b", "NAME Proxima Centauri b", "PSR B1257+12 c" - a
     * lower-case letter after a space, a digit, a capital or a bracket -,
     * or in the number of a TESS or Kepler object of interest the archive
     * keeps as a name ("TOI-4527.01"). A star's ("* 51 Peg", "* alf Ori",
     * "HD 217014", "Gaia DR3 ...") does not.
     */
    public static function isPlanetName(string $name): bool
    {
        return (bool) preg_match('~(?:(?:\s|[0-9A-Z)])[b-z]|\d\.\d{2})$~', trim($name));
    }

    /** A name without the prefixes SIMBAD gives its identifiers: "* 51 Peg b" is 51 Peg b, "NAME Proxima Centauri b" Proxima Centauri b. */
    private static function unprefixed(string $name): string
    {
        return trim((string) preg_replace('~^(?:NAME|V\*|\*\*|\*)\s+~', '', trim($name)));
    }

    /** A name as compared: upper case, without spaces ("KEPLER-22B"). */
    private static function key(string $name): string
    {
        return (string) preg_replace('~\s+~u', '', mb_strtoupper($name));
    }

    /** An ADQL string literal. */
    private static function quote(string $text): string
    {
        return "'".str_replace("'", "''", $text)."'";
    }
}
