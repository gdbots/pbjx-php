# CHANGELOG for 2.x
This changelog references the relevant changes done in 2.x versions.


## v2.1.1
* Add `Gdbots\Pbjx\DependencyInjection\*` marker interfaces to make it possible
  to autowire/autoconfigure services in frameworks like Symfony.
* Update `CommandHandler` and `RequestHandler` interfaces to use new `PbjxHandler`
  marker interface which requires static method `handlesCuries`.  If your code
  is not using the `CommandHandlerTrait` or `RequestHandlerTrait` then you will
  need to add those methods.  At the time of this update, no code in the wild
  is known to exist not using the traits so this is left as a patch.  Also note,
  emitting an empty array is fine if you're not using Symfony or another framework
  that would make use of that method.


## v2.1.0
* Update `gdbots/schemas` composer constraint to allow for `^1.4.1`.
* Remove `gdbots/pbj` package from composer as `gdbots/schemas` already requires it.
* Update `symfony/event-dispatcher` composer constraint to allow for `^3.0 || ^4.0`.
* Add `PbjxToken` which creates one time use signed tokens (JWT) that are intendeded
  to be used to secure pbjx HTTP services against XSS, CSRF, replay attacks, etc.


## v2.0.1
* issue #8: Automatically reconnect gearman if connection is lost.  After `maxReconnects` is reached (default=3)
  then all further pbjx operations will be handled in memory.


## v2.0.0
__BREAKING CHANGES__

* Requires php7.1 and all classes use `declare(strict_types = 1);` and use php7 return 
  type hints and scalar type hints.
* Renames some classes ("Default" makes no sense, "Simple" is more clear as an implementation):
  * `DefaultCommandBus` is now `SimpleCommandBus`
  * `DefaultExceptionHandler` is now `LogAndDispatchExceptionHandler`
  * `DefaultEventBus` is now `SimpleEventBus`
  * `DefaultPbjx` is now `SimplePbjx`
  * `DefaultRequestBus` is now `SimpleRequestBus`
* Most implementations are now marked as final.  Use decorator pattern or provide your own
  implementation to change the functionality of Pbjx components.
* Transports now use a `TransportEnvelope` so the consumers can handle multiple 
  serializers and properly set replay flag on messages that are handled in a separate 
  process or separate machine entirely (e.g. gearman).
* `Transport` and `Router` interfaces moved to `Gdbots\Pbjx\Transport\*`.

__NEW FEATURES__
* Adds `EventStore` and `EventSearch` with DynamoDb and Elastica implementations.
