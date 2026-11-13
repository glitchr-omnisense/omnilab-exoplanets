# omnilab/exoplanets

The NASA Exoplanet Archive for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab): a
confirmed planet beyond the solar system by its name - `51 Peg b`, `Kepler-22b`, `Proxima
Centauri b`, `Dimidium`, the archive's alias service resolves the names its table does not hold -
with its mass, radius, density, orbit, equilibrium temperature and insolation, their units and
uncertainties, how, when and by which facility it was found, its host star's parameters and the
system's position, distance and magnitudes; the host star as a related thing, by each designation
the archive gives it (its name, HD, HIP, TIC, Gaia DR3); a search by name, and a selection by
simple criteria. Through the archive's TAP service, in ADQL, no key.

```php
$archive = (new ExoplanetsSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$planet = $archive->entity('51 Peg b');
$planet->id;                                   // exoplanet:51 Peg b (and simbad:51 Peg b among its identifiers)
$planet->property('mass');                     // 193.87532827 ± 19.06970442 Earth Mass
$planet->property('orbital_period');           // 4.2307966 ± 0.0000027 days
$planet->property('equilibrium_temperature');  // 1328.93 K
$planet->value('discovery_method');            // Radial Velocity
$planet->value('discovery_year');              // 1995
$planet->related('host')->keys();              // simbad:51 Peg, simbad:HD 217014, simbad:HIP 113357, simbad:TIC 139298196, simbad:Gaia DR3 2835207319109249920, gaia:2835207319109249920
$archive->search(new Query('trappist', limit: 5));   // TRAPPIST-1 b, c, d, e, f; the next page from "TRAPPIST-1 f"
$archive->planets(['method' => 'Transit', 'year' => 2024, 'min_radius' => 0.8, 'max_radius' => 1.25]);   // outside the contract
```

```yaml
omnilab:
    sources:
        exoplanets:
            factory: exoplanets
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
                throttle: 0                    # seconds between two calls: the archive publishes no rate
```

A planet is named in the core's `exoplanet` scheme by the archive's name (Wikidata's P5667), and
in the `simbad` scheme too, a name SIMBAD resolves for the planets it lists. With Wikidata and SIMBAD configured too, the
`Collector` follows a planet from Wikidata's SIMBAD identifier (P3083, `* 51 Peg b`) into SIMBAD
and the archive at once; the host star's Gaia DR3 designation leads to omnilab/gaia.

## Install and a first call

```sh
composer require omnilab/exoplanets
```

A real call, with no account and no key: 51 Pegasi b in the NASA Exoplanet Archive. `mailto` is your
address, sent in the User-Agent so that the service can reach you before it blocks a caller; the
source keeps to the limits its [`policy.json`](policy.json) declares.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Exoplanets\ExoplanetsSourceFactory;

$archive = (new ExoplanetsSourceFactory())->create(['mailto' => 'you@example.org']);   // your address, in the User-Agent
$planet = $archive->entity('51 Peg b');
echo $planet->label, ': ', $planet->property('orbital_period'), ', discovered ', $planet->value('discovery_year'), "\n";
```

Its output on 2026-11-13:

```
51 Peg b: 4.2307966 ± 0.0000027 days, discovered 1995
```

## Terms of use

The NASA Exoplanet Archive is operated by Caltech/IPAC for NASA, free and without registration;
it publishes no rate limit. It asks to be acknowledged ("This research has made use of the NASA
Exoplanet Archive, which is operated by the California Institute of Technology, under contract
with the National Aeronautics and Space Administration under the Exoplanet Exploration Program"),
cited as Christiansen et al. 2025 (PSJ, [10.3847/PSJ/ade3c2](https://doi.org/10.3847/PSJ/ade3c2)),
with the table's DOI ([10.26133/NEA13](https://doi.org/10.26133/NEA13), Planetary Systems
Composite Parameters) and the literature reference of each value used.

See [docs/](docs/index.md) for what is read, how it maps, and what the archive does not give here.

## Usage policy

Declared as data in [`policy.json`](policy.json) (its pages could not be read on 2026-11-10 (https://exoplanetarchive.ipac.caltech.edu/docs/TAP/usingTAP.html): see the notes in the file), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnilab/blob/1.x/docs/policies.md)).

- Limits: not published (unknown).
- Licence: not stated; commercial use: unknown.
- Credit: not required: This research has made use of the NASA Exoplanet Archive, which is operated by the California Institute of Technology, under contract with the National Aeronautics and Space Administration under the Exoplanet Exploration Program. (https://exoplanetarchive.ipac.caltech.edu/).
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the NASA Exoplanet Archive (Caltech/IPAC). It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
