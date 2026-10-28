<?php

namespace Kern\Table\Abstracts\Concerns;

use Kern\Table\HeaderActions\HeaderAction;

trait HasHeaderActions
{
    protected array $headerActions = [];

    public function addHeaderAction(HeaderAction $action): static
    {
        $this->headerActions[$action->getName()] = $action;

        return $this;
    }

    public function addHeaderActions(array $actions): static
    {
        foreach ($actions as $action) {
            $this->addHeaderAction($action);
        }

        $this->earlyTable = true;

        return $this;
    }

    public function removeHeaderAction(string $name): static
    {
        unset($this->headerActions[$name]);

        return $this;
    }

    public function removeHeaderActions(array $names): static
    {
        foreach ($names as $name) {
            $this->removeHeaderAction($name);
        }

        return $this;
    }

    public function disableHeaderActions(): static
    {
        $this->headerActions = [];

        return $this;
    }

    public function getHeaderActions(): array
    {
        return $this->headerActions;
    }
}
