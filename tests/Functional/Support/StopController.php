<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\EventLoading\EventLoader;

final class StopController
{
    private ?EventLoader $loader = null;

    public function setLoader(EventLoader $loader): void
    {
        $this->loader = $loader;
    }

    public function stop(): void
    {
        if ($this->loader !== null) {
            $this->loader->stop();
        }
    }
}
