<?php

declare(strict_types=1);

namespace App\Controller;

use App\Search\Collection\CollectionsTrait;
use App\Search\Form\Type\SearchType;
use App\Search\Model\SearchContext;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function reset;

#[AsController]
final class IndexController extends AbstractController
{
    use CollectionsTrait;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FormRendererInterface $formRenderer,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws NotFoundHttpException
     */
    #[Route(
        name: 'app_index',
        path: '/',
        methods: [
            Request::METHOD_GET,
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $collection = reset($this->collections);

        if ($collection === false) {
            throw $this->createNotFoundException('No collection found.');
        }

        $form = $this->formFactory->createNamed('', SearchType::class, new SearchContext($collection));

        $form->handleRequest($request);

        $formView = $form->createView();

        $renderedForm = $this->formRenderer->renderBlock($formView, 'form_start', [
            'attr' => [
                'novalidate' => '',
            ],
        ])
        . $this->formRenderer->renderBlock($formView, 'form_end');

        return new Response($renderedForm);
    }
}
