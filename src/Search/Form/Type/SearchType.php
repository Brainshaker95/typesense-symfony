<?php

declare(strict_types=1);

namespace App\Search\Form\Type;

use App\Search\Collection\CollectionInterface;
use App\Search\Collection\CollectionsTrait;
use App\Search\Exception\InvalidSchemaException;
use App\Search\Model\SearchContext;
use InvalidArgumentException;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use ValueError;

use function array_combine;
use function array_find;
use function array_map;

/**
 * @extends AbstractType<SearchContext>
 */
final class SearchType extends AbstractType
{
    use CollectionsTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws InvalidParameterException
     * @throws InvalidSchemaException
     * @throws MissingMandatoryParametersException
     * @throws RouteNotFoundException
     * @throws ValueError
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $collectionChoices = array_map(
            static fn (CollectionInterface $collection): string => $collection::getSchema()->name,
            $this->collections,
        );

        $collectionTransformer = new CallbackTransformer(
            transform: static fn (?CollectionInterface $collection): ?string => $collection instanceof CollectionInterface ? $collection::getSchema()->name : null,
            reverseTransform: fn (?string $collectionName): ?CollectionInterface => array_find(
                $this->collections,
                static fn (CollectionInterface $collection): bool => $collection::getSchema()->name === $collectionName,
            ),
        );

        $builder
            ->setAction($this->urlGenerator->generate('app_search'))
            ->setMethod(Request::METHOD_GET)
            ->add('collection', Type\ChoiceType::class, [
                'label'        => 'form.label.collection',
                'choices'      => $collectionChoices,
                'choice_label' => static fn (string $collectionChoice): string => 'form.label.collection.' . $collectionChoice,
            ])
            ->add('query', Type\SearchType::class, [
                'required' => false,
                'label'    => 'form.label.query',
            ])
            ->add('pageSize', Type\ChoiceType::class, [
                'label'   => 'form.label.page_size',
                'choices' => array_combine(SearchContext::PAGE_SIZES, SearchContext::PAGE_SIZES),
            ])
            ->add('submit', Type\SubmitType::class, [
                'label' => 'form.label.search',
                'attr'  => [
                    'data-form-submit' => '',
                ],
            ])
        ;

        $builder
            ->get('collection')
            ->addModelTransformer($collectionTransformer)
        ;
    }

    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->offsetGet('collection')->vars['full_name'] = SearchContext::SERIALIZED_NAME_COLLECTION;
        $view->offsetGet('query')->vars['full_name']      = SearchContext::SERIALIZED_NAME_QUERY;
        $view->offsetGet('pageSize')->vars['full_name']   = SearchContext::SERIALIZED_NAME_PAGE_SIZE;
        $view->offsetGet('submit')->vars['full_name']     = '';
    }

    /**
     * @throws AccessException
     * @throws UndefinedOptionsException
     */
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('data')
            ->setAllowedValues('data_class', SearchContext::class)
            ->setDefault('data_class', SearchContext::class)
        ;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
