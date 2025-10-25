<?php

declare(strict_types=1);

namespace App\Search\Controller;

use App\Controller\ControllerTrait;
use App\Search\Collection\CollectionsTrait;
use App\Search\Exception\CollectionNotFoundException;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Form\Type\SearchType;
use App\Search\Model\Pagination;
use App\Search\Model\SearchContext;
use App\Search\Typesense\TypesenseService;
use Http\Client\Exception as HttpClientException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;
use Typesense\Exceptions\TypesenseClientError;

use function array_diff;
use function array_filter;
use function is_array;

#[AsController]
final class SearchController extends AbstractController
{
    use CollectionsTrait;
    use ControllerTrait;

    public function __construct(
        private readonly NormalizerInterface $normalizer,
        private readonly TypesenseService $typesenseService,
    ) {}

    /**
     * @throws CollectionNotFoundException
     * @throws HttpClientException
     * @throws InvalidArgumentException
     * @throws TypesenseClientError
     * @throws UnexpectedValueException
     * @throws ValidationFailedException
     */
    #[Route(
        name: 'app_search',
        path: '/search',
        methods: [
            Request::METHOD_GET,
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $searchContext = SearchContext::fromParameterBag($request->query, $this->collections, $this->validator);

        if ($this->doesClientAccept($request, 'application/json')) {
            $searchResult = $this->typesenseService->search($searchContext);

            return $this->jsonValidated([
                'searchResult' => $searchResult,
                'pagination'   => Pagination::fromSearch($searchContext, $searchResult),
            ]);
        }

        $redirectResponse = $this->getCorrectedQueryRedirectResponse($request, $searchContext);

        if ($redirectResponse instanceof Response) {
            return $redirectResponse;
        }

        $searchResult = $this->typesenseService->search($searchContext);
        $pagination   = Pagination::fromSearch($searchContext, $searchResult);
        $formView     = $this->createForm(SearchType::class, $searchContext)->createView();

        $templateParameters = [
            'form'          => $formView,
            'search_result' => $searchResult,
            'pagination'    => $pagination,
        ];

        return $request->headers->get('HX-Request') === null
            ? $this->render('page/search.html.twig', $templateParameters, new Response(headers: ['Vary' => 'HX-Request']))
            : $this->render('page/search/result.html.twig', $templateParameters, new Response(headers: ['Vary' => 'HX-Request']));
    }

    /**
     * @throws BadRequestException
     * @throws InvalidSchemaException
     * @throws UnexpectedValueException
     */
    private function getCorrectedQueryRedirectResponse(Request $request, SearchContext $searchContext): ?RedirectResponse
    {
        try {
            $normalizedSearchContext = $this->normalizer->normalize($searchContext);
        } catch (Throwable) {
            $normalizedSearchContext = [];
        }

        $normalizedSearchContext = is_array($normalizedSearchContext)
            ? $normalizedSearchContext
            : [];

        $requestQueryParameters = array_filter(
            $request->query->all(),
            static fn (mixed $value): bool => !is_array($value),
        );

        return array_diff($requestQueryParameters, $normalizedSearchContext) === []
            ? null
            : $this->redirectToRoute($request->attributes->getString('_route'), $normalizedSearchContext);
    }
}
