<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Les dates sont enregistrées en heure de Paris : on ne dépend pas du fuseau du serveur
        // (o2switch est en UTC, ce qui décalait de 2 h les publications programmées et les liens expirants)
        date_default_timezone_set('Europe/Paris');

        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
