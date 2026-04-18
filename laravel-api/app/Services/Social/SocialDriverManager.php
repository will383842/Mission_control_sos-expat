<?php

namespace App\Services\Social;

use App\Services\Social\Contracts\SocialPublishingServiceInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Registry + factory for social publishing drivers.
 *
 * Reads config('social.drivers') and resolves each entry through the container.
 * Concrete driver classes are bound in AppServiceProvider (or auto-resolved if
 * they have no constructor dependencies).
 */
class SocialDriverManager
{
    /** @var array<string, SocialPublishingServiceInterface> */
    private array $instances = [];

    public function __construct(private Container $container) {}

    /**
     * Return the driver for a given platform slug.
     *
     * @param bool $skipEnabledCheck   When true, returns the driver even if
     *                                 the platform is disabled in config.
     *                                 Used for OAuth flows : a user must be
     *                                 able to connect a platform for the
     *                                 first time even before it is officially
     *                                 enabled in production.
     */
    public function driver(string $platform, bool $skipEnabledCheck = false): SocialPublishingServiceInterface
    {
        if (isset($this->instances[$platform])) {
            return $this->instances[$platform];
        }

        $map = $this->driverMap();
        if (!isset($map[$platform])) {
            throw new \InvalidArgumentException("Unknown social platform: {$platform}");
        }

        if (!$skipEnabledCheck && !($map[$platform]['enabled'] ?? true)) {
            throw new \RuntimeException("Social platform '{$platform}' is disabled in config/social.php");
        }

        $class = $map[$platform]['driver'];
        $driver = $this->container->make($class);

        if (!$driver instanceof SocialPublishingServiceInterface) {
            throw new \RuntimeException("{$class} must implement SocialPublishingServiceInterface");
        }

        return $this->instances[$platform] = $driver;
    }

    /** Slugs of platforms enabled in config/social.php. */
    public function availablePlatforms(): array
    {
        return array_keys(array_filter(
            $this->driverMap(),
            fn($cfg) => $cfg['enabled'] ?? true,
        ));
    }

    /** All platforms declared, even disabled (for admin UI). */
    public function allPlatforms(): array
    {
        return array_keys($this->driverMap());
    }

    public function isEnabled(string $platform): bool
    {
        $map = $this->driverMap();
        return isset($map[$platform]) && ($map[$platform]['enabled'] ?? true);
    }

    private function driverMap(): array
    {
        return config('social.drivers', []);
    }
}
