<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\sherlock\models;

use Craft;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * SettingsModel
 */
class SettingsModel extends Model
{
    /**
     * @var bool
     */
    public $monitor = false;

    /**
     * @var mixed
     */
    public $notificationEmailAddresses;

    /**
     * @var bool
     */
    public $highSecurityLevel = false;

    /**
     * @var array
     */
    public $headerProtectionSettings = [
        'enabled' => true,
        'headers' => [
            [true, 'Strict-Transport-Security', 'max-age=31536000'],
            [true, 'X-Content-Type-Options', 'nosniff'],
            [true, 'X-Frame-Options', 'SAMEORIGIN'],
            [true, 'X-Xss-Protection', '1; mode=block'],
        ],
    ];

    /**
     * @var array
     */
    public $contentSecurityPolicySettings = [
        'enabled' => false,
        'enforce' => false,
        'header' => true,
        'directives' => [[1, 'default-src', "'self'"]],
    ];

    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var array
     */
    public $restrictControlPanelIpAddresses = [];

    /**
     * @var array
     */
    public $restrictFrontEndIpAddresses = [];

    /**
     * @var array The integration type classes to add to the plugin’s default integration types.
     */
    public $integrationTypes = [];

    /**
     * @var array The integration settings.
     */
    public $integrationSettings = [];

    /**
     * @var mixed
     */
    public $disabledTests = [];

    /**
     * Individual test settings
     */

    // Updates

    public $criticalCraftUpdates = [
        'forceFail' => true,
    ];

    public $criticalPluginUpdates = [
        'forceFail' => true,
    ];

    public $craftUpdates = [];

    public $pluginUpdates = [];

    // HTTPS

    public $httpsControlPanel = [
        'forceFail' => true,
    ];

    public $httpsFrontEnd = [
        'forceFail' => true,
    ];

    // System

    public $craftFilePermissions = [
        'canFail' => true,
    ];

    public $craftFolderPermissions = [
        'canFail' => true,
    ];

    public $craftFoldersAboveWebRoot = [
        'canFail' => true,
    ];

    public $phpVersion = [
        'canFail' => true,
        'thresholds' => [
            '7.0' => '2018-12-03',
            '7.1' => '2019-12-01',
            '7.2' => '2020-11-30',
            '7.3' => '2021-12-06',
            '7.4' => '2022-11-28',
            '8.0' => '2023-11-26',
            '8.1' => '2024-11-25',
            '8.2' => '2025-12-08',
        ],
    ];

    public $phpComposerVersion = [
        'canFail' => true,
    ];

    // Setup

    public $adminUsername = [];

    public $requireEmailVerification = [
        'canFail' => true,
    ];

    public $webAliasInSiteBaseUrl = [
        'forceFail' => true,
    ];

    public $webAliasInVolumeBaseUrl = [
        'forceFail' => true,
    ];

    // Headers

    public $contentSecurityPolicy = [
        'canFail' => true,
    ];

    public $cors = [
        'forceFail' => true,
    ];

    public $expectCT = [
        'canFail' => true,
    ];

    public $referrerPolicy = [
        'canFail' => true,
    ];

    public $strictTransportSecurity = [
        'canFail' => true,
    ];

    public $xContentTypeOptions = [
        'canFail' => true,
    ];

    public $xFrameOptions = [
        'canFail' => true,
    ];

    public $xXssProtection = [];

    // General config settings

    public $blowfishHashCost = [
        'threshold' => 13,
    ];

    public $cooldownDuration = [
        'threshold' => 300, // 5 minutes
    ];

    public $cpTrigger = [];

    public $defaultDirMode = [
        'canFail' => true,
        'threshold' => 0775,
    ];

    public $defaultFileMode = [
        'canFail' => true,
        'threshold' => 0664,
    ];

    public $defaultTokenDuration = [
        'canFail' => true,
        'threshold' => 86400, // 1 day
    ];

    public $deferPublicRegistrationPassword = [];

    public $devMode = [
        'forceFail' => true,
    ];

    public $elevatedSessionDuration = [
        'canFail' => true,
        'threshold' => 300, // 5 minutes
    ];

    public $enableCsrfProtection = [
        'canFail' => true,
    ];

    public $invalidLoginWindowDuration = [
        'threshold' => 3600, // 1 hour
    ];

    public $maxInvalidLogins = [
        'canFail' => true,
        'threshold' => 5,
    ];

    public $preventUserEnumeration = [
        'canFail' => true,
    ];

    public $rememberedUserSessionDuration = [
        'threshold' => 12096004, // 14 days
    ];

    public $requireMatchingUserAgentForSession = [];

    public $requireUserAgentAndIpForSession = [];

    public $sanitizeSvgUploads = [
        'canFail' => true,
    ];

    public $testToEmailAddress = [
        'canFail' => true,
    ];

    public $translationDebugOutput = [
        'canFail' => true,
    ];

    public $useSecureCookies = [
        'canFail' => true,
    ];

    public $userSessionDuration = [
        'canFail' => true,
        'threshold' => 3600, // 1 hour
    ];

    public $verificationCodeDuration = [
        'canFail' => true,
        'threshold' => 86400, // 1 day
    ];

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => ['apiKey'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['apiKey'], 'string', 'length' => [32]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();

        // Set the field labels
        $labels['apiKey'] = Craft::t('sherlock', 'API Key');

        return $labels;
    }
}
