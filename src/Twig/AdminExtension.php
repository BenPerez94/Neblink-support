<?php

namespace App\Twig;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nouveaux_messages_count', [$this, 'getNouveauxMessagesCount']),
        ];
    }

    public function getNouveauxMessagesCount(): int
    {
        return $this->em->getRepository(Contact::class)->count(['statut' => 'nouveau']);
    }
}