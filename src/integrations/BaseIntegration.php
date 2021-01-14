<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\sherlock\integrations;

use craft\base\SavableComponent;

/**
 * @property-read bool $isInstalled
 * @property-read string $warning
 */
abstract class BaseIntegration extends SavableComponent implements IntegrationInterface
{
    /**
     * @var string|null A class that the integration requires.
     */
    protected $requiredClass;

    /**
     * @var bool Whether the integration is enabled.
     */
    public $enabled = false;

    /**
     * @inheritdoc
     */
    public function getIsInstalled(): bool
    {
        if ($this->requiredClass === null) {
            return true;
        }

        return class_exists($this->requiredClass);
    }

    /**
     * @inheritdoc
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @inheritdoc
     */
    public function getWarning(): string
    {
        return '';
    }
}
