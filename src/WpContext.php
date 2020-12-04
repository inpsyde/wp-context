<?php

declare(strict_types=1);

namespace Inpsyde;

final class WpContext implements \JsonSerializable
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
    ];

    /**
     * @var array<string, bool>
     */
    private $data;

    /**
     * @var array<string, callable>
     */
    private $actionCallbacks = [];

    /**
     * @return WpContext
     */
    public static function new(): WpContext
    {
        return new static(array_fill_keys(self::ALL, false));
    }

    /**
     * @return WpContext
     */
    public static function determine(): WpContext
    {
        $installing = defined('WP_INSTALLING') && WP_INSTALLING;
        $xmlRpc = defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
        $isCore = defined('ABSPATH');
        $isCli = defined('WP_CLI');
        $notInstalling = $isCore && !$installing;
        $isAjax = $notInstalling ? wp_doing_ajax() : false;
        $isAdmin = $notInstalling ? (is_admin() && !$isAjax) : false;
        $isCron = $notInstalling ? wp_doing_cron() : false;

        $undetermined = $notInstalling && !$isAdmin && !$isCron && !$isCli && !$xmlRpc && !$isAjax;

        $isRest = $undetermined ? static::isRestRequest() : false;
        $isLogin = ($undetermined && !$isRest) ? static::isLoginRequest() : false;

        // When nothing else matches, we assume it is a front-office request.
        $isFront = $undetermined && !$isRest && !$isLogin;

        // Note that when core is installing **only** `INSTALLING` will be true, not even `CORE`.
        // This is done to do as less as possible during installation, when most of WP does not act
        // as expected.

        $instance = new self(
            [
                self::CORE => ($isCore || $xmlRpc) && !$installing,
                self::FRONTOFFICE => $isFront,
                self::BACKOFFICE => $isAdmin,
                self::LOGIN => $isLogin,
                self::AJAX => $isAjax,
                self::REST => $isRest,
                self::CRON => $isCron,
                self::CLI => $isCli,
                self::XML_RPC => $xmlRpc && !$installing,
                self::INSTALLING => $installing,
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
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        // This is needed because, if called early, global $wp_rewrite is not defined but required
        // by get_rest_url(). WP will reuse what we set here, or in worst case will replace, but no
        // consequences for us in any case.
        if (get_option('permalink_structure') && empty($GLOBALS['wp_rewrite'])) {
            $GLOBALS['wp_rewrite'] = new \WP_Rewrite();
        }

        $currentUrl = set_url_scheme(add_query_arg([]));
        $restUrl = set_url_scheme(get_rest_url());
        $currentPath = trim((string)parse_url((string)$currentUrl, PHP_URL_PATH), '/') . '/';
        $restPath = trim((string)parse_url((string)$restUrl, PHP_URL_PATH), '/') . '/';

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

        $pageNow = (string)($GLOBALS['pagenow'] ?? '');
        if ($pageNow && (basename($pageNow) === 'wp-login.php')) {
            return true;
        }

        $url = home_url((string)parse_url(add_query_arg([]), PHP_URL_PATH));

        return rtrim($url, '/') === rtrim(wp_login_url(), '/');
    }

    /**
     * @param array<string, bool> $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param string $context
     * @return WpContext
     */
    public function force(string $context): WpContext
    {
        if (!in_array($context, self::ALL, true)) {
            throw new \LogicException("'{$context}' is not a valid context.");
        }

        $this->removeActionHooks();

        $data = array_fill_keys(self::ALL, false);
        $data[$context] = true;
        if ($context !== self::INSTALLING && $context !== self::CORE && $context !== self::CLI) {
            $data[self::CORE] = true;
        }

        $this->data = $data;

        return $this;
    }

    /**
     * @return WpContext
     */
    public function withCli(): WpContext
    {
        $this->data[self::CLI] = true;

        return $this;
    }

    /**
     * @param string $context
     * @param string ...$contexts
     * @return bool
     */
    public function is(string $context, string ...$contexts): bool
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
     * @return array<string, bool>
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
    }

    /**
     * @param string $context
     * @return void
     */
    private function resetAndForce(string $context): void
    {
        $cli = $this->data[self::CLI];
        $this->data = array_fill_keys(self::ALL, false);
        $this->data[self::CORE] = true;
        $this->data[self::CLI] = $cli;
        $this->data[$context] = true;
    }
}
