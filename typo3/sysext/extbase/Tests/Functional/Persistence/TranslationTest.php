<?php
namespace TYPO3\CMS\Extbase\Tests\Functional\Persistence;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ExtbaseTeam\BlogExample\Domain\Model\Post;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Frontend\Page\PageRepository;

class TranslationTest extends \TYPO3\CMS\Core\Tests\FunctionalTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3/sysext/extbase/Tests/Functional/Fixtures/Extensions/blog_example'];

    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = array(
        'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/Folders/fileadmin/user_upload' => 'fileadmin/user_upload',
    );

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['extbase', 'fluid'];

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface The object manager
     */
    protected $objectManager;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Repository
     */
    protected $postRepository;

    /**
     * Sets up this test suite.
     */
    protected function setUp()
    {
        parent::setUp();
        /*
         * Posts Dataset for the tests:
         *
         * Post1
         *   -> EN: Post1
         *   -> GR: Post1
         * Post2
         *   -> EN: Post2
         * Post3
         */
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/core/Tests/Functional/Fixtures/pages.xml');
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/blogs.xml');
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/translated-posts.xml');
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/sys_file.xml');
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/sys_file_reference.xml');
        $this->importDataSet(ORIGINAL_ROOT . 'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/sys_file_storage.xml');

        $this->objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $this->postRepository = $this->objectManager->get(\ExtbaseTeam\BlogExample\Domain\Repository\PostRepository::class);

        $this->setUpBasicFrontendEnvironment();

        // todo: find way to "mock" BE_USER in StoragePermissionsAspect or un-register signal
        $GLOBALS['BE_USER'] = new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication();
        $GLOBALS['BE_USER']->user['admin'] = 1;
    }

    /**
     * Minimal frontent environment to satisfy Extbase Typo3DbBackend
     */
    protected function setUpBasicFrontendEnvironment()
    {
        $environmentServiceMock = $this->getMock(\TYPO3\CMS\Extbase\Service\EnvironmentService::class);
        $environmentServiceMock
            ->expects($this->any())
            ->method('isEnvironmentInFrontendMode')
            ->willReturn(true);
        GeneralUtility::setSingletonInstance(\TYPO3\CMS\Extbase\Service\EnvironmentService::class, $environmentServiceMock);

        $pageRepositoryFixture = new PageRepository();
        /** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $frontendControllerMock */
        $frontendControllerMock = $this->getMock(\TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::class, [], [], '', false);
        $frontendControllerMock->sys_page = $pageRepositoryFixture;
//      $frontendControllerMock->sys_language_mode = 'strict';
        $GLOBALS['TSFE'] = $frontendControllerMock;
    }

    /**
     * @test
     */
    public function countReturnsCorrectNumberOfPosts()
    {
        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(0);

        $GLOBALS['TSFE']->sys_language_content = 0;

        $postCount = $query->execute()->count();
        $this->assertSame(3, $postCount);
    }

    /**
     * @test
     */
    public function countReturnsCorrectNumberOfPostsInEnglishLanguage()
    {
        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(1);

        $GLOBALS['TSFE']->sys_language_content = 1;

        $postCount = $query->execute()->count();
        $this->assertSame(3, $postCount);
    }

    /**
     * @test
     */
    public function countReturnsCorrectNumberOfPostsInGreekLanguage()
    {
        $GLOBALS['TSFE']->sys_language_content = 2;

        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(2);
        $postCount = $query->execute()->count();

        $this->assertSame(3, $postCount);
    }

    /**
     * @test
     */
    public function fetchingPostsReturnsEnglishPostsWithFallback()
    {
        $GLOBALS['TSFE']->sys_language_content = 1;

        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(1);

        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();

        $this->assertCount(3, $posts);
        $this->assertSame('B EN:Post1', $posts[0]->getTitle());
        $this->assertSame('A EN:Post2', $posts[1]->getTitle());
        $this->assertSame('Post3', $posts[2]->getTitle());
    }

    /**
     * @test
     */
    public function fetchingPostsReturnsGreekPostsWithFallback()
    {
        $GLOBALS['TSFE']->sys_language_content = 2;

        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(2);

        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();

        $this->assertCount(3, $posts);
        $this->assertSame('GR:Post1', $posts[0]->getTitle());
        $this->assertSame('Post2', $posts[1]->getTitle());
        $this->assertSame('Post3', $posts[2]->getTitle());
    }

    /**
     * @test
     */
    public function orderingByTitleRespectsEnglishTitles()
    {
        $GLOBALS['TSFE']->sys_language_content = 1;

        $query = $this->postRepository->createQuery();

        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([1]);
        $querySettings->setRespectSysLanguage(true);
        $querySettings->setLanguageUid(1);

        $query->setOrderings(['title' => QueryInterface::ORDER_ASCENDING]);

        /** @var Post[] $posts */
        $posts = $query->execute()->toArray();

        $this->assertCount(3, $posts);
        $this->assertSame('A EN:Post2', $posts[0]->getTitle());
        $this->assertSame('B EN:Post1', $posts[1]->getTitle());
        $this->assertSame('Post3', $posts[2]->getTitle());
    }

    /**
     * @test
     */
    public function countReturningCorrectNumberOfImagesInDefaultLanguage() {
        $GLOBALS['TSFE']->sys_language_content = 0;

        /** @var Post $post */
        $post = $this->postRepository->findByUid(1);

        $this->assertSame('Post1', $post->getTitle());
        $this->assertCount(1, $post->getImages());
        /** @var \TYPO3\CMS\Extbase\Domain\Model\FileReference $image */
        foreach ($post->getImages() as $image) {
            $this->assertSame('my test image', $image->getOriginalResource()->getTitle());
        }
    }

    /**
     * @test
     */
    public function countReturningCorrectNumberOfImagesInEnglish() {

        $GLOBALS['TSFE']->sys_language_content = 1;

        /** @var Post $post */
        $post = $this->postRepository->findByUid(2);

        $this->assertSame('B EN:Post1', $post->getTitle());
        $this->assertCount(2, $post->getImages());

        $images = $post->getImages()->toArray();
        $this->assertSame('EN my test image', $images[0]->getOriginalResource()->getTitle());
        $this->assertSame(NULL, $images[1]->getOriginalResource()->getTitle());
    }
}
