<?php

namespace Omnilab\Exoplanets;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The NASA Exoplanet Archive: confirmed planets by name, their mass,
 * radius, orbit and temperature with uncertainties, their discovery, their
 * host star - no key needed.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent: whom the archive writes to before it blocks a caller
 *     throttle: 0                      # seconds between two calls: the archive publishes no rate
 *     base_uri: 'https://exoplanetarchive.ipac.caltech.edu/'
 */
final class ExoplanetsSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'exoplanets',
            'omnilab.required_options' => [],
            'throttle' => 0.0,
            'base_uri' => ExoplanetsSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new ExoplanetsSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', ExoplanetsSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
