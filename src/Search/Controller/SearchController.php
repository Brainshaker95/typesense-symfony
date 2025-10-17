<?php

declare(strict_types=1);

namespace App\Search\Controller;

use App\Search\Collection\CollectionsTrait;
use App\Search\Form\Type\SearchType;
use App\Search\Model\SearchContext;
use App\Search\Typesense\TypesenseService;
use Http\Client\Exception as HttpClientException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Typesense\Exceptions\TypesenseClientError;

use function array_map;
use function count;
use function explode;
use function implode;
use function reset;
use function sprintf;
use function Symfony\Component\String\s;

use const JSON_PRETTY_PRINT;
use const PHP_EOL;

#[AsController]
final class SearchController extends AbstractController
{
    use CollectionsTrait;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly FormRendererInterface $formRenderer,
        private readonly SerializerInterface $serializer,
        private readonly TypesenseService $typesenseService,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * @throws ExceptionInterface
     * @throws HttpClientException
     * @throws InvalidArgumentException
     * @throws NotFoundHttpException
     * @throws TypesenseClientError
     */
    #[Route(
        name: 'app_search',
        path: '/search',
        methods: [
            Request::METHOD_GET,
            Request::METHOD_POST,
        ],
    )]
    public function __invoke(Request $request, #[MapQueryString] SearchContext $searchContext): Response
    {
        $searchResult = $this->typesenseService->search($searchContext);

        if ($request->isMethod(Request::METHOD_POST)) {
            return $this->jsonValidated($searchResult);
        }

        $collection = reset($this->collections);

        if ($collection === false) {
            throw $this->createNotFoundException('No collection found.');
        }

        $form = $this->formFactory->createNamed('', SearchType::class, $searchContext);

        $request->query->set('collection', $request->query->get('c'));
        $request->query->remove('c');
        $request->query->set('query', $request->query->get('q'));
        $request->query->remove('q');
        $form->handleRequest($request);

        $formView = $form->createView();

        $renderedForm = $this->formRenderer->renderBlock($formView, 'form_start', [
            'attr' => [
                'novalidate' => '',
            ],
        ])
        . $this->formRenderer->renderBlock($formView, 'form_end');

        $renderedResult = s(sprintf('<strong>Total items: %s</strong>', $searchResult->totalCount));

        if ($searchResult->totalCount > 0) {
            $renderedResult = $renderedResult
                ->append('<pre>')
                ->append('[')
                ->append(PHP_EOL)
            ;

            foreach ($searchResult->items as $item) {
                $json = $this->serializer->serialize($item, JsonEncoder::FORMAT, [
                    JsonEncode::OPTIONS => JSON_PRETTY_PRINT,
                ]);

                $json = implode(PHP_EOL, array_map(
                    static fn (string $line): string => s($line)
                        ->replaceMatches('/^(?: {4})+/', '  ')
                        ->prepend('  ')
                        ->toString(),
                    explode(PHP_EOL, $json),
                ));

                $renderedResult = $renderedResult->append($json);
            }

            $renderedResult = $renderedResult
                ->append(PHP_EOL)
                ->append(']')
                ->append(PHP_EOL)
                ->append('</pre>')
            ;
        }

        return new Response($renderedForm . $renderedResult);
    }

    private function jsonValidated(mixed $value): Response
    {
        $violations = $this->validator->validate($value);

        if (count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'errors' => $messages,
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($value);
    }
}
