<?php

namespace Kern\Table\Abstracts\Concerns;

use Closure;
use Kern\Table\Abstracts\TableActionAbstract;
use Kern\Table\Columns\RowActionsColumn;
use LogicException;

trait HasActions
{
    /**
     * @var TableActionAbstract[]
     */
    protected array $rowActions = [];

    protected array $rowActionsEditCallbacks = [];

    protected bool $displayActionsAsDropdown = true;

    protected int $displayActionsAsDropdownWhenActionsMoresThan = 3;

    /**
     * @internal
     */
    public function getRowActions(): array
    {
        return collect($this->rowActions)
            ->filter(fn (TableActionAbstract $action) => $action->currentUserHasAnyPermissions())
            ->mapWithKeys(fn (TableActionAbstract $action, string $name) => [$name => $this->getAction($name)])
            ->sortBy(fn (TableActionAbstract $action) => $action->getPriority())
            ->all();
    }

    public function addAction(TableActionAbstract $action): static
    {
        $this->rowActions[$action->getName()] = $action;

        return $this;
    }

    /**
     * @param  TableActionAbstract[]  $actions
     */
    public function addActions(array $actions): static
    {
        foreach ($actions as $action) {
            $this->addAction($action);
        }

        return $this;
    }

    public function removeAction(string $name): static
    {
        unset($this->rowActions[$name]);

        return $this;
    }

    public function removeActions(array $name): static
    {
        foreach ($name as $key) {
            $this->removeAction($key);
        }

        return $this;
    }

    public function removeAllActions(): static
    {
        $this->rowActions = [];

        return $this;
    }

    public function hasAction(string $name): bool
    {
        return isset($this->rowActions[$name]);
    }

    public function hasActions(): bool
    {
        return ! empty($this->getRowActions());
    }

    /**
     * @param  Closure(TableActionAbstract $action): TableActionAbstract  $callback
     */
    public function editAction(string $name, Closure $callback): static
    {
        if (! $this->hasAction($name)) {
            throw new LogicException('Action not found.');
        }

        $this->rowActionsEditCallbacks[$name][] = $callback;

        return $this;
    }

    public function getAction(string $name): TableActionAbstract
    {
        if (! $this->hasAction($name)) {
            throw new LogicException('Action not found.');
        }

        $action = $this->rowActions[$name];

        if (isset($this->rowActionsEditCallbacks[$name])) {
            foreach ($this->rowActionsEditCallbacks as $callback) {
                $callback($action);
            }
        }

        return $action;
    }

    protected function getRowActionsHeading(): array
    {
        return [
            RowActionsColumn::make()->nowrap(),
        ];
    }

    public function displayActionsAsDropdown(bool $enabled = true): static
    {
        $this->displayActionsAsDropdown = $enabled;

        return $this;
    }

    public function hasDisplayActionsAsDropdown(): bool
    {
        return $this->displayActionsAsDropdown
            && count($this->getRowActions()) > $this->getDisplayActionsAsDropdownWhenActionsMoresThan();
    }

    public function displayActionsAsDropdownWhenActionsMoresThan(int $number): static
    {
        if ($number < 0) {
            throw new LogicException('Number must be greater than 0.');
        }

        $this->displayActionsAsDropdown();

        $this->displayActionsAsDropdownWhenActionsMoresThan = $number;

        return $this;
    }

    public function getDisplayActionsAsDropdownWhenActionsMoresThan(): int
    {
        return $this->displayActionsAsDropdownWhenActionsMoresThan;
    }
}
