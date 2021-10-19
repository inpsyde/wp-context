<?php

declare(strict_types=1);

namespace Inpsyde;

class WpContext implements \JsonSerializable
{
    public const AJAX = 'ajax';
    public const BACKOFFICE = 'backoffice';
    public const CLI = 'wpcli';
    public const CORE = 'core';
    public const CRON = 'cron';
    public const FRONTOFFICE = 'frontoffice';
    public const INSTALLING = 'installing';
    public const LOGIN = 'login';
    public const REST = 'rest';
    public const XML_RPC = 'xml-rpc';
    public const WP_ACTIVATE = 'wp-activate';

    private const ALL = [
        self::AJAX,
        self::BACKOFFICE,
        self::CLI,
        self::CORE,
        self::CRON,
        self::FRONTOFFICE,
        self::INSTALLING,
        self::LOGIN,
        self::REST,
        self::XML_RPC,
        self::WP_ACTIVATE,
    ];

    /**
     * @var array
     */
    private $data;

    /**
     * @var array<string, callable>
     */
    private $actionCallbacks = [];

    /**
     * @return WpContext
     */
    final public static function new(): WpContext
    {
        return new self(array_fill_keys(self::ALL, false));
    }

    /**
     * @return WpContext
     */
    final public static function determine(): WpContext
    {
        $installing = defined('WP_INSTALLING') && WP_INSTALLING;
        $xmlRpc = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
        $isCore = defined('ABSPATH');
        $isCli = defined('WP_CLI');
        $notInstalling = $isCore && !$installing;
        $isAjax = $notInstalling && wp_doing_ajax();
        $isAdmin = $notInstalling && is_admin() && !$isAjax;
        $isCron = $notInstalling && wp_doing_cron();
        $isWpActivate = $installing && is_multisite() && self::isWpActivateRequest();

        $undetermined = $notInstalling && !$isAdmin && !$isCron && !$isCli && !$xmlRpc && !$isAjax;

        $isRest = $undetermined && static::isRestRequest();
        $isLogin = $undetermined && !$isRest && static::isLoginRequest();

        // When nothing else matches, we assume it is a front-office request.
        $isFront = $undetermined && !$isRest && !$isLogin;

        /*
         * Note that when core is installing **only** `INSTALLING` will be true, not even `CORE`.
         * This is done to do as less as possible during installation, when most of WP does not act
         * as expected.
         */

        $instance = new self(
            [
                self::AJAX => $isAjax,
                self::BACKOFFICE => $isAdmin,
                self::CLI => $isCli,
                self::CORE => ($isCore || $xmlRpc) && (!$installing || $isWpActivate),
                self::CRON => $isCron,
                self::FRONTOFFICE => $isFront,
                self::INSTALLING => $installing && !$isWpActivate,
                self::LOGIN => $isLogin,
                self::REST => $isRest,
                self::XML_RPC => $xmlRpc && !$installing,
                self::WP_ACTIVATE => $isWpActivate,
            ]
        );

        $instance->addActionHooks();

        return $instance;
    }

    /**
     * @return bool
     */
    private static function isRestRequest(): bool
    {
        if (
            (defined('REST_REQUEST') && REST_REQUEST)
            || !empty($_GET['rest_route']) // phpcs:ignore
        ) {
            return true;
        }

        if (!get_option('permalink_structure')) {
            return false;
        }

        /*
         * This is needed because, if called early, global $wp_rewrite is not defined but required
         * by get_rest_url(). WP will reuse what we set here, or in worst case will replace, but no
         * consequences for us in any case.
         */
        if (empty($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new \WP_Rewrite();
        }

        $currentPath = trim((string)parse_url((string)add_query_arg([]), PHP_URL_PATH), '/') . '/';
        $restPath = trim((string)parse_url((string)get_rest_url(), PHP_URL_PATH), '/') . '/';

        return strpos($currentPath, $restPath) === 0;
    }

    /**
     * @return bool
     */
    private static function isLoginRequest(): bool
    {
        if (!empty($_REQUEST['interim-login'])) { // phpcs:ignore
            return true;
        }

        return static::isPageNow('wp-login.php', wp_login_url());
    }

    /**
     * @return bool
     */
    private static function isWpActivateRequest(): bool
    {
        return static::isPageNow('wp-activate.php', network_site_url('wp-activate.php'));
    }

    /**
     * @param string $page
     * @param string $url
     * @return bool
     */
    private static function isPageNow(string $page, string $url): bool
    {
        $pageNow = (string)($GLOBALS['pagenow'] ?? '');
        if ($pageNow && (basename($pageNow) === $page)) {
            return true;
        }

        $currentPath = (string)parse_url(add_query_arg([]), PHP_URL_PATH);
        $targetPath = (string)parse_url($url, PHP_URL_PATH);

        return trim($currentPath, '/') === trim($targetPath, '/');
    }

    /**
     * @param array $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param string $context
     * @return WpContext
     */
    final public function force(string $context): WpContext
    {
        if (!in_array($context, self::ALL, true)) {
            throw new \LogicException("'{$context}' is not a valid context.");
        }

        $this->removeActionHooks();

        $data = array_fill_keys(self::ALL, false);
        $data[$context] = true;
        if (!in_array($context, [self::INSTALLING, self::CLI, self::CORE], true)) {
            $data[self::CORE] = true;
        }

        $this->data = $data;

        return $this;
    }

    /**
     * @return WpContext
     */
    final public function withCli(): WpContext
    {
        $this->data[self::CLI] = true;

        return $this;
    }

    /**
     * @param string $context
     * @param string ...$contexts
     * @return bool
     */
    final public function is(string $context, string ...$contexts): bool
    {
        array_unshift($contexts, $context);

        foreach ($contexts as $context) {
            if (($this->data[$context] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isCore(): bool
    {
        return $this->is(self::CORE);
    }

    /**
     * @return bool
     */
    public function isFrontoffice(): bool
    {
        return $this->is(self::FRONTOFFICE);
    }

    /**
     * @return bool
     */
    public function isBackoffice(): bool
    {
        return $this->is(self::BACKOFFICE);
    }

    /**
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->is(self::AJAX);
    }

    /**
     * @return bool
     */
    public function isLogin(): bool
    {
        return $this->is(self::LOGIN);
    }

    /**
     * @return bool
     */
    public function isRest(): bool
    {
        return $this->is(self::REST);
    }

    /**
     * @return bool
     */
    public function isCron(): bool
    {
        return $this->is(self::CRON);
    }

    /**
     * @return bool
     */
    public function isWpCli(): bool
    {
        return $this->is(self::CLI);
    }

    /**
     * @return bool
     */
    public function isXmlRpc(): bool
    {
        return $this->is(self::XML_RPC);
    }

    /**
     * @return bool
     */
    public function isInstalling(): bool
    {
        return $this->is(self::INSTALLING);
    }

    /**
     * @return bool
     */
    public function isWpActivate(): bool
    {
        return $this->is(self::WP_ACTIVATE);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * When context is determined very early we do our best to understand some context like
     * login, rest and front-office even if WordPress normally would require a later hook.
     * When that later hook happen, we change what we have determined, leveraging the more
     * "core-compliant" approach.
     *
     * @return void
     */
    private function addActionHooks(): void
    {
        $this->actionCallbacks = [
            'login_init' => function (): void {
                $this->resetAndForce(self::LOGIN);
            },
            'rest_api_init' => function (): void {
                $this->resetAndForce(self::REST);
            },
            'activate_header' => function (): void {
                $this->resetAndForce(self::WP_ACTIVATE);
            },
            'template_redirect' => function (): void {
                $this->resetAndForce(self::FRONTOFFICE);
            },
            'current_screen' => function (\WP_Screen $screen): void {
                $screen->in_admin() and $this->resetAndForce(self::BACKOFFICE);
            },
        ];

        foreach ($this->actionCallbacks as $action => $callback) {
            add_action($action, $callback, PHP_INT_MIN);
        }
    }

    /**
     * When "force" is called on an instance created via `determine()` we need to remove added hooks
     * or what we are forcing might be overridden.
     *
     * @return void
     */
    private function removeActionHooks(): void
    {
        foreach ($this->actionCallbacks as $action => $callback) {
            remove_action($action, $callback, PHP_INT_MIN);
        }
        $this->actionCallbacks = [];
    }

    /**
     * @param string $context
     * @return void
     */
    private function resetAndForce(string $context): void
    {
        $cli = $this->isWpCli();
        $this->force($context);
        $cli and $this->withCli();
    }
}
