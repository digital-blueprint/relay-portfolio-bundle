<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Handler;

class Action
{
    public const TYPE_ACTION = 'action';
    public const TYPE_URL = 'url';

    /**
     * @param string      $id    Action identifier, used in POST /portfolio/workflow-actions
     * @param string      $label Display text for the UI
     * @param string      $type  One of Action::TYPE_ACTION or Action::TYPE_URL
     * @param string|null $url   Required when type is TYPE_URL; the external URL to follow
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $type = self::TYPE_ACTION,
        private readonly ?string $url = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
