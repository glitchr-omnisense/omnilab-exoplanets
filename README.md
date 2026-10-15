# omnilab/exoplanets

The NASA Exoplanet Archive for [glitchr/omnilab](https://github.com/glitchr-studio/omnilab): a
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

## Terms of use

The NASA Exoplanet Archive is operated by Caltech/IPAC for NASA, free and without registration;
it publishes no rate limit. It asks to be acknowledged ("This research has made use of the NASA
Exoplanet Archive, which is operated by the California Institute of Technology, under contract
with the National Aeronautics and Space Administration under the Exoplanet Exploration Program"),
cited as Christiansen et al. 2025 (PSJ, [10.3847/PSJ/ade3c2](https://doi.org/10.3847/PSJ/ade3c2)),
with the table's DOI ([10.26133/NEA13](https://doi.org/10.26133/NEA13), Planetary Systems
Composite Parameters) and the literature reference of each value used.

See [docs/](docs/index.md) for what is read, how it maps, and what the archive does not give here.

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
