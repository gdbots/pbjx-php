<?php
declare(strict_types=1);

namespace Gdbots\Pbjx\Event;

use Gdbots\Pbj\Message;

final class EnrichContextEvent
{
    private string $service;
    private string $operation;
    private array $context;
    private ?Message $causator = null;

    public function __construct(string $service, string $operation, array $context = [])
    {
        $this->service = $service;
        $this->operation = $operation;

        if (isset($context['causator']) && $context['causator'] instanceof Message) {
            $this->causator = $context['causator'];

            if (!isset($context['tenant_id']) && $this->causator->has('ctx_tenant_id')) {
                $context['tenant_id'] = $this->causator->get('ctx_tenant_id');
            }
        }

        unset($context['causator']);
        $this->context = $context;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function hasCausator(): bool
    {
        return null !== $this->causator;
    }

    public function getCausator(): ?Message
    {
        return $this->causator;
    }

    public function hasTenantId(): bool
    {
        return $this->has('tenant_id');
    }

    public function getTenantId(): string
    {
        return (string)$this->get('tenant_id');
    }

    public function setTenantId(string $tenantId): self
    {
        return $this->set('tenant_id', $tenantId);
    }

    public function has(string $name): bool
    {
        return isset($this->context[$name]);
    }

    public function get(string $name, $default = null)
    {
        return $this->context[$name] ?? $default;
    }

    public function set(string $name, $value): self
    {
        $this->context[$name] = $value;
        return $this;
    }

    public function clear(string $name): self
    {
        unset($this->context[$name]);
        return $this;
    }

    public function all(): array
    {
        return $this->context;
    }
}
