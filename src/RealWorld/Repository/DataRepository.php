<?php

declare(strict_types=1);

namespace App\RealWorld\Repository;

use App\RealWorld\DataToIndex\DefaultPage;
use App\RealWorld\DataToIndex\Image;
use App\RealWorld\DataToIndex\LocalizedPage;
use App\RealWorld\DataToIndex\Video;

final class DataRepository
{
    /**
     * @return list<DefaultPage|LocalizedPage>
     */
    public function getPages(): array
    {
        return [
            new DefaultPage(
                id: 1,
                title: 'Welcome to RealWorld',
                content: 'An introduction to the RealWorld example application and its features.',
            ),
            new DefaultPage(
                id: 2,
                title: 'Getting Started',
                content: 'Step-by-step guide to set up the project locally and run the test suite.',
            ),
            new DefaultPage(
                id: 3,
                title: 'API Overview',
                content: 'High-level overview of the public API endpoints and authentication flow.',
            ),
            new LocalizedPage(
                id: 1,
                title: 'Über RealWorld',
                content: 'Eine kurze Einführung in die RealWorld Beispielanwendung und ihre Funktionen.',
                locale: 'de',
            ),
            new DefaultPage(
                id: 4,
                title: 'Contributing',
                content: 'How to contribute code, write tests and follow the project guidelines.',
            ),
            new DefaultPage(
                id: 5,
                title: 'Changelog',
                content: 'Recent changes, releases and notable bug fixes in the project.',
            ),
            new LocalizedPage(
                id: 2,
                title: 'Getting Started (EN)',
                content: 'Quickstart and common troubleshooting hints in English.',
                locale: 'en',
            ),
            new DefaultPage(
                id: 6,
                title: 'Architecture',
                content: 'Description of the system architecture, modules and data flow.',
            ),
            new LocalizedPage(
                id: 3,
                title: 'Erste Schritte (DE)',
                content: 'Kurzinfo zum schnellen Einstieg und zur Fehlerbehebung auf Deutsch.',
                locale: 'de',
            ),
        ];
    }

    /**
     * @return list<Image>
     */
    public function getImages(): array
    {
        return [
            new Image(
                title: 'Sunset over the Bay',
                author: 'A. Rivera',
                description: 'Golden hour over the city bay with reflections on the water.',
                caption: 'Sunset skyline',
            ),
            new Image(
                title: 'Mountain Trail',
                author: 'J. Kim',
                description: 'A winding trail through alpine meadows in summer.',
                caption: 'Hiking route',
            ),
            new Image(
                title: 'City Night Lights',
                author: 'L. Müller',
                description: 'Long exposure capturing car trails and illuminated skyscrapers.',
                caption: 'Urban night',
            ),
            new Image(
                title: 'Autumn Leaves',
                author: 'S. Patel',
                description: 'Close-up of colorful maples with morning dew.',
                caption: 'Fall foliage',
            ),
            new Image(
                title: 'Product Flatlay',
                author: 'M. Rossi',
                description: 'Minimal product composition used for marketing mockups.',
                caption: 'E-commerce mockup',
            ),
            new Image(
                title: 'Portrait: The Gardener',
                author: 'D. Lopez',
                description: 'Environmental portrait of a gardener arranging plants.',
                caption: 'Portrait session',
            ),
            new Image(
                title: 'Desert Dunes',
                author: 'E. Okoro',
                description: 'Windswept patterns on sand dunes under a clear sky.',
            ),
            new Image(
                title: 'Coffee & Code',
                author: 'R. Singh',
                caption: 'Workspace vibes',
            ),
            new Image(
                title: ' Harbor Morning',
                author: 'N. Ivanov',
                description: 'Fisherboats anchored at sunrise with mist on the water.',
                caption: 'Morning calm',
            ),
            new Image(
                title: 'Macro: Bee on Flower',
                author: 'K. Yamamoto',
                description: 'Detailed shot of a bee collecting nectar from a blossom.',
                caption: 'Nature close-up',
            ),
        ];
    }

    /**
     * @return list<Video>
     */
    public function getVideos(): array
    {
        return [
            new Video(
                title: 'Intro to RealWorld',
                author: 'Core Team',
                length: 4.2,
                description: 'A short walkthrough covering the goals of the RealWorld project.',
                transcript: 'Welcome to RealWorld. In this video we introduce the project...',
            ),
            new Video(
                title: 'Setting Up the Dev Environment',
                author: 'Contributor Docs',
                length: 9.5,
                description: 'Guide to install dependencies and run the development server.',
                transcript: 'First, clone the repository. Then install dependencies...',
            ),
            new Video(
                title: 'API Authentication Explained',
                author: 'Auth Specialist',
                length: 12.0,
                description: 'Deep dive into token-based authentication used by the API.',
                transcript: 'Authentication relies on JWT tokens issued at login...',
            ),
            new Video(
                title: 'Testing Best Practices',
                author: 'QA Team',
                length: 15.3,
                description: 'Recommendations for writing reliable unit and integration tests.',
                transcript: 'Start by isolating units of code and mocking external services...',
            ),
            new Video(
                title: 'Deploying to Production',
                author: 'Ops Team',
                length: 11.1,
                description: 'Steps for preparing a release and deploying safely to production.',
                transcript: 'Make sure migrations are tested and backups are available...',
            ),
            new Video(
                title: 'Localisation Basics',
                author: 'I18n Lead',
                length: 7.4,
                description: 'How localization is structured in the codebase and content handling.',
                transcript: 'Use locale-specific page variants and translation keys...',
            ),
            new Video(
                title: 'Performance Tuning Tips',
                author: 'Performance Team',
                length: 10.7,
                description: 'Common optimizations for database and HTTP request handling.',
                transcript: 'Analyze slow queries and add indexes where appropriate...',
            ),
            new Video(
                title: 'Design System Overview',
                author: 'UX Team',
                length: 6.8,
                description: 'Introduction to reusable components and styling conventions.',
                transcript: 'Components are documented and versioned for consistent use...',
            ),
            new Video(
                title: 'Accessibility Considerations',
                author: 'A11y Advocate',
                length: 8.9,
                transcript: 'Use semantic HTML, proper labels and keyboard navigation...',
            ),
            new Video(
                title: 'Contributing Workflow',
                author: 'Maintainer',
                length: 5.6,
                description: 'How to prepare a contribution, from issue to pull request.',
            ),
        ];
    }
}
