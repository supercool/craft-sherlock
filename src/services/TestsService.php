<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\sherlock\services;

use Craft;
use craft\base\Component;
use craft\base\Plugin;
use craft\helpers\ConfigHelper;
use craft\helpers\UrlHelper;
use craft\models\Updates;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use putyourlightson\sherlock\models\TestModel;
use putyourlightson\sherlock\Sherlock;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

/**
 * Tests Service
 *
 * @property array $testNames
 */
class TestsService extends Component
{
    /**
     * @var Client|null
     */
    public $client = null;

    /**
     * @var Updates
     */
    public $updates;

    /**
     * @var string|null
     */
    public $siteUrl;

    /**
     * @var array|null
     */
    public $siteUrlResponse;

    /**
     * Get test names
     *
     * @return array
     */
    public function getTestNames(): array
    {
        $tests = [
            // Updates
            'criticalCraftUpdates',
            'criticalPluginUpdates',
            'craftUpdates',
            'pluginUpdates',

            // HTTPS
            'httpsControlPanel',
            'httpsFrontEnd',

            // System
            'craftFilePermissions',
            'craftFolderPermissions',
            'craftFoldersAboveWebRoot',
            'phpVersion',
            'phpComposerVersion',

            // Setup
            'adminUsername',
            'requireEmailVerification',
            'webAliasInSiteBaseUrl',
            'webAliasInVolumeBaseUrl',

            // Headers
            'contentSecurityPolicy',
            'cors',
            'expectCT',
            'referrerPolicy',
            'strictTransportSecurity',
            'xContentTypeOptions',
            'xFrameOptions',
            'xXssProtection',

            // General config settings
            'blowfishHashCost',
            'cooldownDuration',
            'cpTrigger',
            'defaultDirMode',
            'defaultFileMode',
            'defaultTokenDuration',
            'deferPublicRegistrationPassword',
            'devMode',
            'elevatedSessionDuration',
            'enableCsrfProtection',
            'invalidLoginWindowDuration',
            'maxInvalidLogins',
            'preventUserEnumeration',
            'rememberedUserSessionDuration',
            'requireMatchingUserAgentForSession',
            'requireUserAgentAndIpForSession',
            'sanitizeSvgUploads',
            'testToEmailAddress',
            'translationDebugOutput',
            'userSessionDuration',
            'useSecureCookies',
            'verificationCodeDuration',
        ];

        // Remove disabled tests
        $disabledTests = Sherlock::$plugin->settings->disabledTests;

        if (is_array($disabledTests)) {
            $tests = array_values(array_diff($tests, $disabledTests));
        }

        return $tests;
    }

    /**
     * Performs preps before running tests.
     *
     * @throws HttpException
     */
    public function beforeRunTests()
    {
        // Ensure we only run this method once
        if ($this->client !== null) {
            return;
        }

        $guzzleOptions = [
            'timeout' => 10,
        ];

        $configuredGuzzleOptions = Craft::$app->getConfig()->getGeneral()->sherlockGuzzleOptions;
        if ($configuredGuzzleOptions && is_array($configuredGuzzleOptions)) {
            $guzzleOptions = array_merge($guzzleOptions, $configuredGuzzleOptions);
        }

        $this->client = Craft::createGuzzleClient(
            $guzzleOptions
        );

        // Get updates, forcing a refresh
        $this->updates = Craft::$app->getUpdates()->getUpdates(true);

        // Get the current site's base URL if not already set (by unit tests)
        $this->siteUrl = $this->siteUrl ?? Craft::$app->getSites()->getCurrentSite()->getBaseUrl();

        try {
            $response = $this->client->get($this->siteUrl);
            $this->siteUrlResponse['headers'] = $response->getHeaders();
            $this->siteUrlResponse['body'] = $response->getBody()->getContents();
        }
        catch (GuzzleException $exception) {
            $message = Craft::t('sherlock', 'Unable to connect to "{url}". Please ensure that the site is reachable and that the system is turned on.', ['url' => $this->siteUrl]);

            Sherlock::$plugin->log($message);
            Sherlock::$plugin->log($exception->getMessage());

            throw new NotFoundHttpException($message);
        }
    }

    /**
     * Run test
     *
     * @param string $test
     * @return TestModel
     * @throws HttpException
     */
    public function runTest(string $test): TestModel
    {
        $this->beforeRunTests();

        $testModel = new TestModel(Sherlock::$plugin->settings->{$test});
        $testModel->highSecurityLevel = Sherlock::$plugin->settings->highSecurityLevel;

        switch ($test) {
            case 'criticalCraftUpdates':
                if ($this->updates->cms->getHasCritical()) {
                    $criticalCraftUpdates = [];

                    foreach ($this->updates->cms->releases as $release) {
                        if ($release->critical) {
                            $criticalCraftUpdates[] = '
                                <a href="https://github.com/craftcms/cms/blob/master/CHANGELOG-v3.md#'.str_replace('.', '-', $release->version).'" target="_blank">'.$release->version.'</a> 
                                <span class="info">Version '.$release->version.' is a critical update, released on '.$this->_formatDate($release->date).'.</span>
                            ';
                        }
                    }

                    $testModel->failTest();
                    $testModel->value = implode(' , ', $criticalCraftUpdates);
                }

                break;

            case 'criticalPluginUpdates':
                $criticalPluginUpdates = [];

                if (!empty($this->updates->plugins)) {
                    foreach ($this->updates->plugins as $handle => $update) {
                        if ($update->getHasCritical()) {
                            /** @var Plugin $plugin */
                            $plugin = Craft::$app->getPlugins()->getPlugin($handle);

                            foreach ($update->releases as $release) {
                                if ($release->critical) {
                                    $criticalPluginUpdates[] = '
                                        <a href="'.$plugin->changelogUrl.'" target="_blank">'.$plugin->name.'</a> 
                                        <span class="info">Version '.$release->version.' is a critical update, released on '.$this->_formatDate($release->date).'.</span>
                                    ';
                                }
                            }
                        }
                    }
                }

                if (!empty($criticalPluginUpdates)) {
                    $testModel->failTest();
                    $testModel->value = implode(' , ', $criticalPluginUpdates);
                }

                break;

            case 'craftUpdates':
                if ($this->updates->cms->getHasReleases()) {
                    $testModel->failTest();
                }

                break;

            case 'pluginUpdates':
                $pluginUpdates = [];

                if (!empty($this->updates->plugins)) {
                    foreach ($this->updates->plugins as $handle => $update) {
                        if (!empty($update->releases)) {
                            $latestRelease = $update->getLatest();

                            /** @var Plugin $plugin */
                            $plugin = Craft::$app->getPlugins()->getPlugin($handle);

                            if ($plugin !== null) {
                                $pluginUpdates[] = '
                                    <a href="'.$plugin->changelogUrl.'" target="_blank">'.$plugin->name.'</a> 
                                    <span class="info">Local version '.$plugin->version.' is '.count($update->releases).' release'.(count($update->releases) != 1 ? 's' : '').' behind latest version '.$latestRelease->version.', released on '.$this->_formatDate($latestRelease->date).'.</span>
                                ';
                            }
                        }
                    }
                }

                if (!empty($pluginUpdates)) {
                    $testModel->failTest();
                    $testModel->value = implode(' , ', $pluginUpdates);
                }

                break;

            case 'httpsControlPanel':
                // Get CP URL response
                $url = UrlHelper::baseCpUrl();

                if (strpos($url, 'http') !== 0) {
                    $url = trim($this->siteUrl, '/') . '/' . Craft::$app->getConfig()->getGeneral()->cpTrigger;
                }

                if (!$this->_redirectsToHttps($url)) {
                    $testModel->failTest();
                }

                break;

            case 'httpsFrontEnd':
                if (!$this->_redirectsToHttps($this->siteUrl)) {
                    $testModel->failTest();
                }

                break;

            case 'craftFilePermissions':
                $paths = [
                    '.env' => Craft::getAlias('@root/.env'),
                    '.env.php' => Craft::getAlias('@root/.env.php'),
                    'composer.json' => Craft::getAlias('@root/composer.json'),
                    'composer.lock' => Craft::getAlias('@root/composer.lock'),
                    'config/license.key' => Craft::getAlias('@config/license.key'),
                ];

                $pathsFailed = $this->_getPathsWritableByEveryone($paths);

                if (!empty($pathsFailed)) {
                    $testModel->failTest();

                    $testModel->value = implode(', ', array_keys($pathsFailed));
                }

                break;

            case 'craftFolderPermissions':
                $paths = [
                    'config/project' => Craft::getAlias('@config/project'),
                    'storage' => Craft::getAlias('@storage'),
                    'vendor' => Craft::getAlias('@vendor'),
                    'webroot/cpresources' => Craft::getAlias('@webroot/cpresources'),
                ];

                $pathsFailed = $this->_getPathsWritableByEveryone($paths);

                if (!empty($pathsFailed)) {
                    $testModel->failTest();

                    $testModel->value = implode(', ', array_keys($pathsFailed));
                }

                break;

            case 'craftFoldersAboveWebRoot':
                $paths = [
                    'root' => Craft::getAlias('@root'),
                    'config' => Craft::getAlias('@config'),
                    'storage' => Craft::getAlias('@storage'),
                    'templates' => Craft::getAlias('@templates'),
                ];
                $pathsFailed = [];

                $webroot = Craft::getAlias('@webroot');

                foreach ($paths as $key => $path) {
                    // If the webroot is a substring of the path
                    if (strpos($path, $webroot) !== false) {
                        $pathsFailed[] = $key;
                    }
                }

                if (!empty($pathsFailed)) {
                    $testModel->failTest();

                    $testModel->value = implode(', ', $pathsFailed);
                }

                break;

            case 'phpVersion':
                $version = PHP_VERSION;
                $value = substr($version, 0, 3);
                $eolDate = '';

                if (isset($testModel->thresholds[$value])) {
                    if (strtotime($testModel->thresholds[$value]) < time()) {
                        $testModel->failTest();
                    }

                    $eolDate = $testModel->thresholds[$value];
                }

                $testModel->value = $version.($eolDate ? ' (until '.$eolDate.')' : '');

                break;

            case 'phpComposerVersion':
                $version = PHP_VERSION;

                /** @noinspection PhpComposerExtensionStubsInspection */
                $json = json_decode(file_get_contents(Craft::getAlias('@root/composer.json')));
                $requiredVersion = $json->config->platform->php ?? null;

                if (empty($requiredVersion)) {
                    break;
                }

                $versionParts = explode('.', $version);
                $versionMinor = $versionParts[0].'.'.$versionParts[1];
                $requiredVersionParts = explode('.', $requiredVersion);
                $requiredVersionMinor = $requiredVersionParts[0].'.'.$requiredVersionParts[1];

                // Only compare minor version
                if (version_compare($requiredVersionMinor, $versionMinor, '<')) {
                    $testModel->failTest();
                    $testModel->value = PHP_VERSION;
                }

                break;

            case 'adminUsername':
                $user = Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');

                if ($user && $user->admin) {
                    $testModel->failTest();
                }

                break;

            case 'requireEmailVerification':
                if (Craft::$app->getProjectConfig()->get('users.requireEmailVerification') === false) {
                    $testModel->failTest();
                }

                break;

            case 'webAliasInSiteBaseUrl':
                if (Craft::$app->getRequest()->isWebAliasSetDynamically) {
                    $currentSite = Craft::$app->getSites()->getCurrentSite();

                    // How this works was changed in 3.6.0 (use 3.5.99 to account for 3.6.0 beta versions)
                    // https://github.com/craftcms/cms/issues/3964#issuecomment-737546660
                    if (version_compare(Craft::$app->getVersion(), '3.5.99', '>=')) {
                        $unparsedBaseUrl = $currentSite->getBaseUrl(false);
                    }
                    else {
                        $unparsedBaseUrl = $currentSite->baseUrl;
                    }

                    if (strpos($unparsedBaseUrl, '@web') !== false) {
                        $testModel->failTest();
                    }
                }

                break;

            case 'webAliasInVolumeBaseUrl':
                if (Craft::$app->getRequest()->isWebAliasSetDynamically) {
                    $volumes = Craft::$app->getVolumes()->getAllVolumes();
                    $volumesFailed = [];

                    foreach ($volumes as $volume) {
                        if ($volume->hasUrls && strpos($volume->url, '@web') !== false) {
                            $volumesFailed[] = $volume->name;
                        }
                    }

                    if (!empty($volumesFailed)) {
                        $testModel->failTest();
                        $testModel->value = implode(' , ', $volumesFailed);
                    }
                }

                break;

            case 'contentSecurityPolicy':
                $value = $this->_getHeaderValue('Content-Security-Policy');
                $headerSet = !empty($value);

                if (!$headerSet) {
                    // Look for meta tag
                    preg_match('/<meta http-equiv="Content-Security-Policy" content="(.*?)"/si', $this->siteUrlResponse['body'], $matches);
                    $value = $matches[1] ?? '';
                }

                if (empty($value)){
                    $testModel->failTest();
                    $testModel->value = 'Neither Content-Security-Policy header nor meta tag are set';
                }
                else {
                    $testModel->value = 'Content-Security-Policy '.($headerSet ? 'header' : 'meta tag').' ';

                    if (strpos($value, 'unsafe-inline') !== false || strpos($value, 'unsafe-eval') !== false) {
                        $testModel->warning = true;
                        $testModel->value .= 'contains "unsafe" values';
                    }
                    else {
                        $testModel->value .= 'is set';
                    }
                }

                break;

            case 'cors':
                $value = $this->_getHeaderValue('Access-Control-Allow-Origin');

                if ($value) {
                    if ($value == '*') {
                        $testModel->failTest();
                    }
                    else {
                        $testModel->warning = true;
                    }

                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'expectCT':
                $value = $this->_getHeaderValue('Expect-CT');

                if (empty($value)) {
                    $testModel->failTest();
                }

                break;

            case 'referrerPolicy':
                $value = $this->_getHeaderValue('Referrer-Policy');

                if (empty($value)) {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'strictTransportSecurity':
                $value = $this->_getHeaderValue('Strict-Transport-Security');

                if (empty($value)) {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'xContentTypeOptions':
                $value = $this->_getHeaderValue('X-Content-Type-Options');

                if ($value != 'nosniff') {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'xFrameOptions':
                $value = $this->_getHeaderValue('X-Frame-Options');

                if ($value != 'DENY' && $value != 'SAMEORIGIN') {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'xXssProtection':
                $value = $this->_getHeaderValue('X-Xss-Protection');

                // If not set then check alternative case
                $value = $value ?: $this->_getHeaderValue('X-XSS-Protection');

                // Remove spaces and convert to lower case for comparison
                $compareValue = strtolower(str_replace(' ', '', $value));

                if ($compareValue != '1;mode=block') {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = '"'.$value.'"';
                }

                break;

            case 'enableCsrfProtection':
            case 'useSecureCookies':
            case 'requireMatchingUserAgentForSession':
            case 'requireUserAgentAndIpForSession':
            case 'preventUserEnumeration':
            case 'sanitizeSvgUploads':
                if (!Craft::$app->getConfig()->getGeneral()->{$test}) {
                    $testModel->failTest();
                }

                break;

            case 'deferPublicRegistrationPassword':
            case 'devMode':
            case 'testToEmailAddress':
            case 'translationDebugOutput':
                if (Craft::$app->getConfig()->getGeneral()->{$test}) {
                    $testModel->failTest();
                }

                break;

            case 'defaultDirMode':
            case 'defaultFileMode':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if ($value > $testModel->threshold) {
                    $testModel->failTest();
                }

                else {
                    $testModel->value = $value ? '0'.decoct($value) : 'null';
                }

                break;

            case 'defaultTokenDuration':
            case 'verificationCodeDuration':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};
                $seconds = ConfigHelper::durationInSeconds($value);

                if ($seconds > $testModel->threshold) {
                    $testModel->failTest();
                }

                else {
                    $testModel->value = $value;
                }

                break;

            case 'cpTrigger':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if ($value == 'admin') {
                    $testModel->failTest();
                }

                break;

            case 'blowfishHashCost':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if ($value < $testModel->threshold) {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = $value;
                }

                break;

            case 'cooldownDuration':
            case 'invalidLoginWindowDuration':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};
                $seconds = ConfigHelper::durationInSeconds($value);

                if ($seconds < $testModel->threshold) {
                    $testModel->failTest();
                }
                else {
                    $testModel->value = $value;
                }

                break;

            case 'maxInvalidLogins':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if (!$value) {
                    $testModel->failTest();
                }
                elseif ($value > $testModel->threshold) {
                    $testModel->warning = true;
                }
                else {
                    $testModel->value = $value;
                }

                break;

            case 'rememberedUserSessionDuration':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if ($value) {
                    $seconds = ConfigHelper::durationInSeconds($value);

                    if ($seconds > $testModel->threshold) {
                        $testModel->failTest();
                    }
                    else {
                        $testModel->value = $value;
                    }
                }

                break;

            case 'userSessionDuration':
            case 'elevatedSessionDuration':
                $value = Craft::$app->getConfig()->getGeneral()->{$test};

                if (!$value) {
                    $testModel->failTest();
                }
                else {
                    $seconds = ConfigHelper::durationInSeconds($value);

                    if ($seconds > $testModel->threshold) {
                        $testModel->warning = true;
                    }
                    else {
                        $testModel->value = $value;
                    }
                }

                break;
        }

        return $testModel;
    }

    /**
     * Returns a header value.
     *
     * @param string $name
     *
     * @return string
     */
    private function _getHeaderValue(string $name): string
    {
        // Use lower-case name if it exists in the header
        if (!empty($this->siteUrlResponse['headers'][strtolower($name)])) {
            $name = strtolower($name);
        }

        $value = $this->siteUrlResponse['headers'][$name] ?? '';

        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        // URL decode and strip tags to make it safe to output raw
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        $value = strip_tags(urldecode($value));

        return $value;
    }

    /**
     * Returns paths that are writable by everyone.
     *
     * @param array $paths
     * @return array
     */
    private function _getPathsWritableByEveryone(array $paths): array
    {
        $writablePaths = [];

        foreach ($paths as $key => $path) {
            // If the path exists and is writable by everyone
            if ((is_file($path) || is_dir($path)) && substr(decoct(fileperms($path)), -1) >= 6) {
                $writablePaths[$key] = $path;
            }
        }

        return $writablePaths;
    }

    /**
     * Returns a formatted date.
     *
     * @param int|string|DateTime $date
     * @return string
     */
    private function _formatDate($date): string
    {
        return Craft::$app->getFormatter()->asDate($date, 'long');
    }

    /**
     * Returns whether an insecure URL redirects to HTTPS or errors.
     */
    private function _redirectsToHttps(string $url): bool
    {
        /** @noinspection HttpUrlsUsage */
        $url = str_replace('https://', 'http://', $url);
        $scheme = null;

        try {
            // Get redirect URL scheme of insecure URL
            $this->client->get($url, [
                'on_stats' => function(TransferStats $stats) use (&$scheme) {
                    $scheme = $stats->getEffectiveUri()->getScheme();
                },
            ]);

            if ($scheme != 'https') {
                return false;
            }
        }
        catch (GuzzleException $exception) {
            // An error indicates that insecure requests are blocked, so allow to pass
        }

        return true;
    }
}
