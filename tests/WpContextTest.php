<?php

declare(strict_types=1);

namespace Inpsyde\Tests;

use Brain\Monkey;
use Inpsyde\WpContext;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @runTestsInSeparateProcesses
 */
class WpContextTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var string
     */
    private $currentPath = '/';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Monkey\Functions\expect('add_query_arg')
            ->with([])
            ->andReturnUsing(function (): string {
                return $this->currentPath;
            });
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->currentPath = '/';
        unset($GLOBALS['pagenow']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testNotCore(): void
    {
        $context = WpContext::determine();

        static::assertFalse($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());
        static::assertFalse($context->is(WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsLogin(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(true);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertTrue($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::LOGIN));
        static::assertTrue($context->is(WpContext::LOGIN, WpContext::REST));
        static::assertFalse($context->is(WpContext::FRONTOFFICE, WpContext::REST));
        static::assertTrue($context->is(WpContext::FRONTOFFICE, WpContext::REST, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsLoginLate(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $onLoginInit = null;
        Monkey\Actions\expectAdded('login_init')
            ->whenHappen(static function (callable $callback) use (&$onLoginInit) {
                $onLoginInit = $callback;
            });

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        /** @var callable $onLoginInit */
        $onLoginInit();
        static::assertTrue($context->isLogin());
    }

    /**
     * @test
     */
    public function testIsRest(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(true);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertTrue($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::REST));
        static::assertTrue($context->is(WpContext::REST, WpContext::LOGIN));
        static::assertFalse($context->is(WpContext::FRONTOFFICE, WpContext::LOGIN));
        static::assertTrue($context->is(WpContext::FRONTOFFICE, WpContext::LOGIN, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsRestLate(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $onRestInit = null;
        Monkey\Actions\expectAdded('rest_api_init')
            ->whenHappen(static function (callable $callback) use (&$onRestInit) {
                $onRestInit = $callback;
            });

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isRest());
        /** @var callable $onRestInit */
        $onRestInit();
        static::assertTrue($context->isRest());
    }

    /**
     * @test
     */
    public function testIsCron(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(true);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertTrue($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::CRON));
        static::assertTrue($context->is(WpContext::LOGIN, WpContext::CRON));
        static::assertFalse($context->is(WpContext::FRONTOFFICE, WpContext::LOGIN));
        static::assertTrue($context->is(WpContext::FRONTOFFICE, WpContext::LOGIN, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsFrontoffice(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertTrue($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::FRONTOFFICE));
        static::assertTrue($context->is(WpContext::LOGIN, WpContext::FRONTOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::LOGIN));
        static::assertTrue($context->is(WpContext::CRON, WpContext::LOGIN, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsBackoffice(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(true);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertTrue($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::BACKOFFICE));
        static::assertTrue($context->is(WpContext::LOGIN, WpContext::BACKOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::LOGIN));
        static::assertTrue($context->is(WpContext::CRON, WpContext::LOGIN, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsAjax(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(true);
        Monkey\Functions\when('is_admin')->justReturn(true);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertTrue($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::AJAX));
        static::assertTrue($context->is(WpContext::AJAX, WpContext::BACKOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::BACKOFFICE));
        static::assertTrue($context->is(WpContext::CRON, WpContext::BACKOFFICE, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsInstalling(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_INSTALLING', true);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);
        $this->mockIsActivateRequest(false);

        $context = WpContext::determine();

        static::assertFalse($context->isCore());
        static::assertFalse($context->isWpActivate());
        static::assertTrue($context->isInstalling());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());
        static::assertFalse($context->isXmlRpc());

        static::assertTrue($context->is(WpContext::INSTALLING));
        static::assertTrue($context->is(WpContext::INSTALLING, WpContext::BACKOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::BACKOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::BACKOFFICE, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsWpActivate(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_INSTALLING', true);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);
        $this->mockIsActivateRequest(true);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertTrue($context->isWpActivate());
        static::assertFalse($context->isInstalling());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertFalse($context->isWpCli());

        static::assertTrue($context->is(WpContext::WP_ACTIVATE));
        static::assertTrue($context->is(WpContext::WP_ACTIVATE, WpContext::BACKOFFICE));
        static::assertFalse($context->is(WpContext::CRON, WpContext::BACKOFFICE));
        static::assertTrue($context->is(WpContext::CRON, WpContext::BACKOFFICE, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testIsWpActivateLate(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);
        $this->mockIsActivateRequest(false);

        $onActivateHeader = null;
        Monkey\Actions\expectAdded('activate_header')
            ->whenHappen(static function (callable $callback) use (&$onActivateHeader) {
                $onActivateHeader = $callback;
            });

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isWpActivate());
        /** @var callable $onActivateHeader */
        $onActivateHeader();
        static::assertTrue($context->isWpActivate());
    }

    /**
     * @test
     */
    public function testIsCli(): void
    {
        define('ABSPATH', __DIR__);
        define('WP_CLI', 2);

        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(false);

        $context = WpContext::determine();

        static::assertTrue($context->isCore());
        static::assertFalse($context->isLogin());
        static::assertFalse($context->isRest());
        static::assertFalse($context->isCron());
        static::assertFalse($context->isFrontoffice());
        static::assertFalse($context->isBackoffice());
        static::assertFalse($context->isAjax());
        static::assertTrue($context->isWpCli());

        static::assertTrue($context->is(WpContext::CLI));
        static::assertTrue($context->is(WpContext::FRONTOFFICE, WpContext::CLI));
        static::assertFalse($context->is(WpContext::FRONTOFFICE, WpContext::CRON));
        static::assertTrue($context->is(WpContext::CRON, WpContext::BACKOFFICE, WpContext::CORE));
    }

    /**
     * @test
     */
    public function testJsonSerialize(): void
    {
        define('ABSPATH', __DIR__);
        Monkey\Functions\when('wp_doing_ajax')->justReturn(false);
        Monkey\Functions\when('is_admin')->justReturn(false);
        Monkey\Functions\when('wp_doing_cron')->justReturn(false);
        $this->mockIsRestRequest(false);
        $this->mockIsLoginRequest(true);

        $context = WpContext::determine();
        $decoded = (array)json_decode((string)json_encode($context), true);

        static::assertTrue($decoded[WpContext::CORE]);
        static::assertTrue($decoded[WpContext::LOGIN]);
        static::assertFalse($decoded[WpContext::REST]);
        static::assertFalse($decoded[WpContext::CRON]);
        static::assertFalse($decoded[WpContext::FRONTOFFICE]);
        static::assertFalse($decoded[WpContext::BACKOFFICE]);
        static::assertFalse($decoded[WpContext::AJAX]);
        static::assertFalse($decoded[WpContext::CLI]);
    }

    /**
     * @param bool $is
     */
    private function mockIsRestRequest(bool $is): void
    {
        Monkey\Functions\expect('get_option')->with('permalink_structure')->andReturn(true);
        $GLOBALS['wp_rewrite'] = \Mockery::mock('WP_Rewrite');
        Monkey\Functions\when('get_rest_url')->justReturn('https://example.com/wp-json');
        $is and $this->currentPath = '/wp-json/foo';
    }

    /**
     * @param bool $is
     */
    private function mockIsLoginRequest(bool $is): void
    {
        $is and $this->currentPath = '/wp-login.php';
        Monkey\Functions\when('is_login')->justReturn($is);
    }

    /**
     * @param bool $is
     */
    private function mockIsActivateRequest(bool $is): void
    {
        Monkey\Functions\when('is_multisite')->justReturn($is);

        $is and $this->currentPath = '/wp-activate.php';
        Monkey\Functions\when('network_site_url')->alias(static function (string $path): string {
            return 'https://example.com/' . ltrim($path, '/');
        });
    }
}
