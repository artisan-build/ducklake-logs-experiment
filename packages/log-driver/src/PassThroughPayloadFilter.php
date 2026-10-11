<?php

declare(strict_types=1);

namespace ArtisanBuild\DucklakeLogDriver;

use ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter;

final class PassThroughPayloadFilter implements PayloadFilter
{
    public function filter(LogPayload $payload): ?LogPayload
    {
        return $payload;
    }
}
