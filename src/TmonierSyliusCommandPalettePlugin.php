<?php

declare(strict_types=1);

namespace Tmonier\SyliusCommandPalettePlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class TmonierSyliusCommandPalettePlugin extends Bundle
{
    use SyliusPluginTrait;

    /** Appended to the URLs of public/ assets: bump it when they change. */
    public const ASSETS_VERSION = '1.0.0';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
