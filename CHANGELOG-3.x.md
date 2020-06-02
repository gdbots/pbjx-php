# CHANGELOG for 3.x
This changelog references the relevant changes done in 3.x versions.


## v3.0.0
__BREAKING CHANGES__

* Require php `>=7.4`
* Uses php7 type hinting throughout with `declare(strict_types=1);`
* Uses `"gdbots/pbj": "^3.0"`
* Uses `"gdbots/query-parser": "^2.0"`
* Uses `"gdbots/schemas": "^2.0"`
* Uses `"symfony/event-dispatcher": "^5.1"`
* Removes all gearman functionality.
* Renames `Gdbots\Pbjx\ShardUtils` to `Gdbots\Pbjx\ShardUtil`.
* Renames `Gdbots\Pbjx\StatusCodeConverter` to `Gdbots\Pbjx\StatusCodeUtil`.
* Changes all typehints using mixin interfaces (Command/Event/Request) to just Message since gdbots/pbjc compiler no longer generates interfaces for mixins.
* Changes `EventStore::pipeEvents` and `EventStore::pipeAllEvents` to return a generator instead of using callback style.
* Adds `EnrichContextEvent` which will be dispatched by the `DynamoDbEventStore` and `ElasticaEventSearch` so the context can be customized via event subscribers.
