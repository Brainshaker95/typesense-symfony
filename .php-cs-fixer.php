<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use PhpCsFixerCustomFixers\Fixer;
use PhpCsFixerCustomFixers\Fixers;
use Symfony\Component\Finder\Finder;

$finder = new Finder()
    ->files()
    ->in('.')
    ->name('/\.php$/')
    ->ignoreDotFiles(false)
    ->exclude([
        '.cache',
        'var',
        'vendor',
    ])
;

return new Config()
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->registerCustomFixers(new Fixers())
    ->setRules([
        '@PhpCsFixer'                                           => true,
        '@PhpCsFixer:risky'                                     => true,
        '@PHP82Migration:risky'                                 => true,
        '@PHP84Migration'                                       => true,
        '@PHPUnit100Migration:risky'                            => true,
        'binary_operator_spaces'                                => ['operators' => \array_fill_keys(['=', '=>', '??=', '.=', '+=', '-=', '*=', '/=', '%=', '**=', '&=', '|=', '^=', '<<=', '>>='], 'align_single_space_minimal')],
        'blank_line_before_statement'                           => ['statements' => ['break', 'case', 'continue', 'declare', 'default', 'do', 'exit', 'for', 'foreach', 'goto', 'if', 'include', 'include_once', 'phpdoc', 'require', 'require_once', 'return', 'switch', 'throw', 'try', 'while', 'yield', 'yield_from']],
        'class_attributes_separation'                           => ['elements' => ['case' => 'none', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none']],
        'class_definition'                                      => ['multi_line_extends_each_single_line' => true, 'single_item_single_line' => true],
        'comment_to_phpdoc'                                     => ['ignored_tags' => ['phpstan-ignore']],
        'concat_space'                                          => ['spacing' => 'one'],
        'date_time_create_from_format_call'                     => true,
        'date_time_immutable'                                   => true,
        'declare_strict_types'                                  => true,
        'final_class'                                           => true,
        'final_public_method_for_abstract_class'                => true,
        'fully_qualified_strict_types'                          => ['leading_backslash_in_global_namespace' => true],
        'get_class_to_class_keyword'                            => true,
        'global_namespace_import'                               => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'increment_style'                                       => ['style' => 'post'],
        'mb_str_functions'                                      => true,
        'modernize_strpos'                                      => true,
        'native_constant_invocation'                            => true,
        'native_function_invocation'                            => ['include' => ['@all']],
        'ordered_interfaces'                                    => true,
        'ordered_types'                                         => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'phpdoc_align'                                          => ['align' => 'left'],
        'phpdoc_array_type'                                     => true,
        'phpdoc_line_span'                                      => true,
        'phpdoc_list_type'                                      => true,
        'phpdoc_order_by_value'                                 => ['annotations' => ['author', 'covers', 'coversNothing', 'dataProvider', 'depends', 'group', 'internal', 'method', 'mixin', 'property', 'property-read', 'property-write', 'requires', 'throws', 'uses']],
        'phpdoc_separation'                                     => ['groups' => [['deprecated', 'link', 'see', 'since'], ['author', 'copyright', 'license'], ['category', 'package', 'subpackage'], ['property', 'phpstan-property', 'property-read', 'phpstan-property-read', 'property-write', 'phpstan-property-write'], ['param', 'phpstan-param'], ['return', 'phpstan-return']]],
        'phpdoc_to_comment'                                     => ['allow_before_return_statement' => true, 'ignored_tags' => ['throws']],
        'phpdoc_types_order'                                    => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        'php_unit_internal_class'                               => ['types' => ['abstract', 'final', 'normal']],
        'psr_autoloading'                                       => ['dir' => './src'],
        'regular_callable_call'                                 => true,
        'self_static_accessor'                                  => true,
        'simplified_if_return'                                  => true,
        'single_quote'                                          => ['strings_containing_single_quote_chars' => true],
        'standardize_increment'                                 => false,
        'static_lambda'                                         => true,
        'strict_comparison'                                     => true,
        'strict_param'                                          => true,
        'trailing_comma_in_multiline'                           => ['elements' => ['arrays', 'array_destructuring', 'arguments', 'match', 'parameters']],
        'unary_operator_spaces'                                 => ['only_dec_inc' => false],
        'yoda_style'                                            => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        Fixer\ForeachUseValueFixer::name()                      => true,
        Fixer\MultilineCommentOpeningClosingAloneFixer::name()  => true,
        Fixer\MultilinePromotedPropertiesFixer::name()          => true,
        Fixer\NoDoctrineMigrationsGeneratedCommentFixer::name() => true,
        Fixer\NoDuplicatedArrayKeyFixer::name()                 => true,
        Fixer\NoDuplicatedImportsFixer::name()                  => true,
        Fixer\NoUselessCommentFixer::name()                     => true,
        Fixer\NoUselessDirnameCallFixer::name()                 => true,
        Fixer\NoUselessDoctrineRepositoryCommentFixer::name()   => true,
        Fixer\NoUselessWriteVisibilityFixer::name()             => true,
        Fixer\NoUselessStrlenFixer::name()                      => true,
        Fixer\PhpdocNoIncorrectVarAnnotationFixer::name()       => true,
        Fixer\PhpdocSelfAccessorFixer::name()                   => true,
        Fixer\PhpdocTypesCommaSpacesFixer::name()               => true,
        Fixer\PhpUnitAssertArgumentsOrderFixer::name()          => true,
        Fixer\PhpUnitDedicatedAssertFixer::name()               => true,
        Fixer\PhpUnitNoUselessReturnFixer::name()               => true,
        Fixer\PromotedConstructorPropertyFixer::name()          => true,
        Fixer\StringableInterfaceFixer::name()                  => true,
        Fixer\TrimKeyFixer::name()                              => true,
        Fixer\TypedClassConstantFixer::name()                   => true,
    ])
;
