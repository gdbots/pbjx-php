# CHANGELOG for 1.x
This changelog references the relevant changes done in 1.x versions.


## v1.1.0
* Adds `EventStore` and `EventSearch` with DynamoDb and Elastica implementations.
* Transports now use a `TransportEnvelope` so the consumers can handle multiple serializers and properly set replay
  flag on messages that are handled in a separate process or separate machine entirely (e.g. gearman)
* `Transport` and `Router` interfaces moved to `Gdbots\Pbjx\Transport\*`.


## v1.0.1
* Remove all references to `stream_id` as it's removed from the `gdbots/schemas` library.


## v1.0.0
__BREAKING CHANGES__

* Using new `gdbots/pbj` and `gdbots/schemas` libraries (schemas moved, php >=5.6 required).
* Renamed `ConventionalCommandHandling` and `ConventionalRequestHandling` to `CommandHandlerTrait` and `RequestHandlerTrait`.
  By default the trait will call `handle` but provides `getMethodForCommand` or `getMethodForRequest` so it can be modified.
* The `DefaultPbjx` now triggers `bind`, `validate` and `enrich` when `send` or `publish` is called and the message isn't frozen.
* `Pbjx::trigger` now supports recursive event dispatching.  Any nested messages will also be run through the same event suffix.
  This is useful for the standard set of bind, validate, enrich passes done on the root event.
* `DefaultExceptionHandler` now logs the exception and pbj message in the context array.
* Adds `KinesisTransport`... `KinesisConsumer` coming in patch rev soon.
