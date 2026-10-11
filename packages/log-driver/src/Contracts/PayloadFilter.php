<?php

declare(strict_types=1);

namespace ArtisanBuild\DucklakeLogDriver\Contracts;

use ArtisanBuild\DucklakeLogDriver\LogPayload;

interface PayloadFilter
{
    public function filter(LogPayload $payload): ?LogPayload;
}
