# omnilab/exoplanets

The NASA Exoplanet Archive (Caltech/IPAC), through its TAP service on the Planetary Systems
Composite Parameters table (`https://exoplanetarchive.ipac.caltech.edu/TAP/sync`), for
[omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): confirmed planets by name -
mass, radius, orbit, temperature with uncertainties, discovery - with their host star related by
its designations, as entities; a search; outside the contract, the planets that meet criteria. No
key.

## Installation

```sh
composer require omnilab/exoplanets
```

```php
use Omnilab\Exoplanets\ExoplanetsSourceFactory;

$archive = (new ExoplanetsSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional
    'throttle' => 0,                   // optional: seconds between two calls
]);
```

With the Symfony bundle, the factory is registered as `exoplanets`.

## Credentials

None: the NASA Exoplanet Archive is free and without registration. Give `mailto`, the address the
archive writes to before it blocks a caller.

## Options

The factory's options, with the defaults it applies (`ExoplanetsSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `mailto` | `?string` | null | no | a contact address, in the User-Agent: whom the archive writes to before it blocks a caller |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: the archive publishes no rate |
| `base_uri` | `string` | `https://exoplanetarchive.ipac.caltech.edu/` | no | the archive's site: its TAP service (`TAP/sync`) and its alias service |

## Capabilities

`capabilities()` answers `entity`, `search`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | a planet's name (the archive's, spaced otherwise, SIMBAD's, or one the alias service knows); a star's designation and another base refused before any call; one TAP query, three with the alias service |
| `search` | yes | the planets whose name or host star's holds the text, by name, 500 a page at most, the next after the last name; then the alias service; a domain other than astronomy answers an empty page |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no |  |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | the planet's designations in the `exoplanet` and `simbad` schemes; its host star's (SIMBAD, Gaia DR3) are `related` ([The schemes](#the-schemes)) |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

Outside the contract, on `Omnilab\Exoplanets\ExoplanetsSource`: `planets(array $criteria = [], int
$limit = 25, ?string $cursor = null): Page` - the planets that meet every criterion (`host`,
`method`, `facility`, `year`, `from_year`, `to_year`, `min_mass`... [above](#calls)), by name.

## Which API: TAP, on the composite table

The archive answers through its TAP service (`https://exoplanetarchive.ipac.caltech.edu/TAP/sync`,
ADQL over Oracle, `format=json`: a list of rows keyed by column), its older API (`nstedAPI`, being
retired) and its pages. TAP is used, on the **Planetary Systems Composite Parameters** table
(`pscomppars`, 6,445 planets on 2026-10-15): one row a planet, each parameter from the reference the
archive chose, where the Planetary Systems table (`ps`, 40,336 rows) gives every published solution
apart. The archive's alias service (`cgi-bin/Lookup/nph-aliaslookup.py`, JSON) resolves the names
the table does not hold: `Proxima Centauri b` is `Proxima Cen b`, `Dimidium` is `51 Peg b`.

## Calls

| Method | Reads | Calls |
|---|---|---|
| `entity()` | a planet's name - the archive's (`51 Peg b`, `exoplanet:51 Peg b`), spaced otherwise (`Kepler-22b`), SIMBAD's (`* 51 Peg b`, `NAME Proxima Centauri b`, as an `Identifier` of the `simbad` scheme or `simbad:...`), or one the alias service knows (`Dimidium`) | one: `WHERE upper(replace(pl_name, ' ', '')) = '51PEGB'`; when no row, the alias service, then `WHERE pl_name = '...'` - three |
| `search()` | `Query::$text`: the planets whose name or host star's holds it (spaces and case aside), by name; when none, the planet the alias service resolves it to, or the planets of that star; a domain other than astronomy answers nothing | one a page; two more when the alias service is asked |
| `planets()` | outside the contract (the core's `Query` is a text): the planets that meet every criterion, by name | one a page |

`planets()` reads `host` (the host star's name, spaces and case aside), `method` (one of
`ExoplanetsSource::METHODS`: `Transit`, `Radial Velocity`, `Imaging`, `Microlensing`...),
`facility` (a part of its name), `year`, `from_year`, `to_year` (of discovery), `min_mass`,
`max_mass` (Earth masses, or M sin i), `min_radius`, `max_radius` (Earth radii), `min_period`,
`max_period` (days), `min_temperature`, `max_temperature` (equilibrium, K), `max_distance` (pc). A
key it does not read is a `NotSupportedException`, a text where a number is expected an
`InvalidArgumentException`, both before any call; a planet whose value is unknown does not meet a
criterion on it.

Pages: the archive's ADQL has no `OFFSET` (Oracle answers `ORA-00933`), so a page asks `TOP` one
more than its size, ordered by name, and the next starts after the last name (`Page::$next` is that
name). `total` is known when the first page holds every match.

An `Identifier` of the `simbad` scheme that does not end as a planet's name does (a letter after a
space, a digit, a capital or a bracket: `51 Peg b`, `Kepler-22b`, `Kepler-1647 (AB) b`; or the
`.01` of a TESS or Kepler object of interest, `TOI-4527.01`) names a star - `* 51 Peg`, `HD
217014`, `Gaia DR3 ...` - and is refused before any call, so the `Collector` offers the archive a
star's designations for nothing. Another base's identifier (a Wikidata item, `jpl.sbdb:433`) is
refused too; unknown is `null`. A quote in a name is doubled.

## What an entity holds

`id` (the archive's name in the `exoplanet` scheme: `exoplanet:51 Peg b`), `label` (that name), `domain`
astronomy, `kind` `exoplanet`, `url` (its overview page, `/overview/51%20Peg%20b`), no
description, no image, no synonyms, no `place` (a position on the sky is not a place on the Earth:
the core's `Point` is WGS 84), and these properties, in the archive's units (its `TAP_SCHEMA`,
`**` written as an exponent):

| Key | Column | Value |
|---|---|---|
| `mass`, `mass_jupiter` | pl_bmasse, pl_bmassj | Quantity, Earth Mass, Jupiter Mass; labelled "Minimum mass (M sin i)" when `pl_bmassprov` is `Msini`, "Mass (estimated from the radius)" when it is `M-R relationship` (3,032 of the 6,445 planets on 2026-10-15: no measured mass) |
| `radius`, `radius_jupiter` | pl_rade, pl_radj | Quantity, Earth Radius, Jupiter Radius |
| `density` | pl_dens | Quantity, g/cm³ |
| `orbital_period`, `semi_major_axis`, `eccentricity`, `inclination` | pl_orbper, pl_orbsmax, pl_orbeccen, pl_orbincl | Quantity, days, au, -, deg |
| `equilibrium_temperature`, `insolation` | pl_eqt, pl_insol | Quantity, K, Earth Flux |
| `host_temperature`, `host_mass`, `host_radius`, `host_metallicity`, `host_luminosity`, `host_surface_gravity`, `host_age` | st_teff, st_mass, st_rad, st_met, st_lum, st_logg, st_age | Quantity, K, Solar mass, Solar Radius, dex, log(Solar), log10(cm/s²), Gyr |
| `host_spectral_type` | st_spectype | text |
| `distance`, `parallax` | sy_dist, sy_plx | Quantity, pc, mas |
| `ra`, `dec`, `coordinates` | ra, dec; rastr, decstr | Quantity, deg; text `22h57m28.21s +20d46m08.74s` |
| `host_magnitude_V`, `host_magnitude_K`, `host_magnitude_G` | sy_vmag, sy_kmag, sy_gaiamag | Quantity, mag |
| `mass_provenance` | pl_bmassprov | `Mass` (2,472), `M-R relationship` (3,032), `Msini` (926), `Msin(i)/sin(i)` (15) |
| `discovery_method`, `discovery_year`, `discovery_published`, `discovery_facility`, `discovery_telescope`, `discovery_instrument` | discoverymethod, disc_year, disc_pubdate, disc_facility, disc_telescope, disc_instrument | text, number |
| `discovery_reference`, `discovery_reference_url` | disc_refname (an HTML link) | `Mayor & Queloz 1995`; its ADS address |
| `stars`, `planets`, `moons` | sy_snum, sy_pnum, sy_mnum (moons only when there are) | number |
| `controversial`, `circumbinary` | pl_controv_flag, cb_flag | boolean |

**Uncertainties**: the archive gives an upper and a lower one (`err1`, `err2`); a `Quantity` holds
one, the larger of the two (51 Peg b's mass: +19.07 −15.89, kept as ± 19.07). **Limits**: a value
the archive flags as a limit (`lim` 1 or −1) says so in its label ("Mass (upper limit)") and has
no uncertainty.

`identifiers`: its own, the archive's name in the `simbad` scheme (`simbad:51 Peg b`), and SIMBAD's main identifier when it was asked by it (`simbad:* 51 Peg
b`): the same planet. `related('host')`: the host star, by each designation the archive gives it,
in the `simbad` scheme - `hostname`, `hd_name`, `hip_name`, `tic_id`, `gaia_dr3_id` (51 Peg b:
`51 Peg`, `HD 217014`, `HIP 113357`, `TIC 139298196`, `Gaia DR3 2835207319109249920`); a
microlensing host has its name alone (`OGLE-2014-BLG-0319L`). They are names SIMBAD resolves
(`$collector->collect('simbad', $planet->related('host')->get(Scheme::SIMBAD))`), and the Gaia DR3
one is what omnilab/gaia reads.

## The schemes

A planet is named in the core's `exoplanet` scheme by the archive's name (`exoplanet:51 Peg b`;
Wikidata holds the archive's names in P5667, "NASA Exoplanet Archive ID", on 12,976 items on
2026-10-15, planets and systems both: `Proxima Cen` names a system, refused here before any call),
and among its identifiers in the `simbad` scheme too: SIMBAD resolves the archive's name for the
planets it lists (`51 Peg b`, `Kepler-22 b`, `TRAPPIST-1 e`, `TOI-700 d`, `HD 209458 b`, `Proxima
Cen b`: checked on 2026-10-15; SIMBAD writes the spaces as it likes), not for every one (a
microlensing planet SIMBAD has not catalogued is a name SIMBAD answers nothing for). The host
star's Gaia DR3 designation is related in the `gaia` scheme as well (`gaia:2835207319109249920`).
Until the core had these schemes (2026-10-15), the id was the `simbad` one.

## The Collector: 51 Peg b from Wikidata

Wikidata gives a planet's SIMBAD identifier (P3083, `* 51 Peg b` for Q242309), which the Wikidata
source reads into the `simbad` scheme; the archive reads SIMBAD's main identifier (its `* ` or
`NAME ` taken off, spaces aside). Against the real services on 2026-10-15:

```php
$collector->collect('wikidata', 'Q242309', 1, ['simbad', 'exoplanets']);
```

4 calls in 4.6 s - Wikidata's two (the item, the labels of its classes and units), SIMBAD's query
for `* 51 Peg b`, the archive's query - and `sources` = wikidata, simbad, exoplanets:

```
mass                     0.46 ± 0.035 Jupiter mass [wikidata]
mass                     193.87532827 ± 19.06970442 Earth Mass [exoplanets]
radius                   1.9 ± 0.3 Jupiter radius [wikidata]
radius                   14.1 Earth Radius [exoplanets]
orbital_period           4.2307969 ± 0.00000755 d [wikidata]
orbital_period           4.2307966 ± 0.0000027 days [exoplanets]
equilibrium_temperature  1328.93 K [exoplanets]
discovery_year           1995 [exoplanets]
object_type              Extra-solar Planet [simbad]
parallax                 64.4048 ± 0.0771 mas [simbad]
parallax                 64.6488 ± 0.123201 mas [exoplanets]
```

From the archive, `collect('exoplanets', '51 Peg b', 2, ['simbad', 'wikidata'])` reaches SIMBAD
(3 calls; its answer adds `simbad:* 51 Peg b`) but not Wikidata: Wikidata is asked in the same
round by the archive's spelling, `haswbstatement:P3083=51 Peg b`, finds no item (it holds SIMBAD's
`* 51 Peg b`), and the `Collector` does not ask a source twice. Start from Wikidata or SIMBAD to
have all three.

The host star: `$planet->related('host')` holds `simbad:Gaia DR3 2835207319109249920`, which
omnilab/gaia reads (`$collector->collect('gaia', ...)`), and the star's names, which SIMBAD reads.

## What the archive does not give here

The Planetary Systems table's other solutions (one row a published solution, `ps`), each value's
reference (`*_reflink`), the stellar hosts table, the transit and microlensing tables, the
candidates (TESS and Kepler objects of interest not confirmed), the time series, spectra and
light curves, the per-planet aliases of the alias service (asked only to resolve a name), the
asynchronous TAP queries, and the spatial constraints (a cone on the sky) are not read.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Exoplanets\ExoplanetsSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

$archive = (new ExoplanetsSourceFactory(HttpClient::create(['timeout' => 60])))->create(['mailto' => 'you@example.org']);

$planet = $archive->entity('Dimidium');   // a name the alias service knows: 51 Peg b
echo $planet->label, ': ', $planet->property('mass'), ', period ', $planet->property('orbital_period'), ', discovered ', $planet->value('discovery_year'), ' by ', $planet->value('discovery_method'), "\n";

$page = $archive->planets(['method' => 'Imaging', 'from_year' => 2025, 'max_distance' => 50], 3);
printf("%s planets imaged since 2025 within 50 pc; the first %d: %s\n", $page->total ?? 'more than 3', count($page), implode(', ', array_map(static fn ($p) => $p->label, $page->items)));
```

```
51 Peg b: 193.87532827 ± 19.06970442 Earth Mass, period 4.2307966 ± 0.0000027 days, discovered 1995 by Radial Velocity
3 planets imaged since 2025 within 50 pc; the first 3: CWISEP J193518.59-154620.3 b, TWA 7 b, bet Pic d
```

(as answered on 2026-10-27: four calls - TAP, the alias service, TAP again, the criteria; the three are all that match, so `total` is known)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        exoplanets: { factory: exoplanets, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
```

`planets()` is a method of `ExoplanetsSource`, not of the interface the bundle injects: check the instance before calling it.

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: a star's designation or another base's identifier (`NotSupportedException`); in
`planets()`, a criterion not read (`NotSupportedException`) or a text where a number is expected
(`\InvalidArgumentException`). TAP's errors (a VOTable or text naming Oracle's `ORA-` code) are read
into the `ProviderException`'s message. An unknown planet is null.

## Verified, and not

Against the real service on 2026-10-15: 51 Peg b by its name, by SIMBAD's `* 51 Peg b` and by
`Dimidium` (the alias service); Kepler-22 b by `Kepler-22b`; Proxima Cen b by `Proxima Centauri
b` (an M sin i); Kepler-62 f (an upper limit on its mass); OGLE-2014-BLG-0319L b (a microlensing
host, no HD, HIP, TIC or Gaia id); an unknown name (no row, the alias service's "System Not
Found"); the search for "trappist" in two pages of five then two, for "Proxima Centauri" (the
planets of the star it resolves to: b and d) and for an unknown text; the planets found by transit
in 2024 between 0.8 and 1.25 Earth radii (a first page of ten); an ADQL error (400 with a VOTable
saying `ORA-00933`); the Collector's walks above. The fixtures in `Tests/Fixtures/` are its
answers. The alias service took 3.5 to 5 s a call, TAP about 1.3 s.

Again on 2026-10-27: 51 Peg b by its alias Dimidium, the planets imaged since 2025 within 50 pc
(three; the example above).

Not verified: a rate limit (none published, none met); a planet whose name holds a quote (none in
the table); a mirror (`base_uri`).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by NASA, IPAC or Caltech. The archive asks to be acknowledged and cited (Christiansen et
al. 2025, and the table's DOI 10.26133/NEA13), with the literature reference of each value used,
as the README's [Terms of use](https://github.com/glitchr-omnisense/omnilab-exoplanets#terms-of-use)
says. Whoever runs the package is responsible for complying with them and for any use made of what
it returns: see the README's
[Responsibility](https://github.com/glitchr-omnisense/omnilab-exoplanets#responsibility) section.
