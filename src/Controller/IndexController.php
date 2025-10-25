<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class IndexController extends AbstractController
{
    #[Route(
        name: 'app_index',
        path: '/',
        methods: [
            Request::METHOD_GET,
        ],
    )]
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('app_search');
    }
}
