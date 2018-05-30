# CHANGELOG for 2.x
This changelog references the relevant changes done in 2.x versions.


## v2.2.5
* issue #15: Increase offset max from 1000 to 10,000 in `ElasticaEventSearch::searchEvents`.


## v2.2.4
* Adjust `PbjxToken` TTL to 10 (down from 120) and LEEWAY to 300 (from 30).  Because LEEWAY is used in both iat and exp validation we need that window to be larger, exp works within that expanded window as well.


## v2.2.3
* Adjust `PbjxToken` TTL to 120 (up from 5) and LEEWAY to 30 (from 5).


## v2.2.2
* Fix bug in `Gdbots\Pbjx\EventSearch\Elastica\IndexManager::getIndexNamesForSearch` that would not include the next index when overlapping quarters.


## v2.2.1
* Catch and log error when updating existing analyzers on index in `Gdbots\Pbjx\EventSearch\Elastica\IndexManager::updateIndex`.


## v2.2.0
__POSSIBLE BREAKING CHANGE__

If you are not using the `SimplePbjx` implementation for `Pbjx` then you'll need to add the `sendAt` and `cancelJobs` methods to your implementation.

* Add `Gdbots\Pbjx\Scheduler\Scheduler` with a `DynamoDbScheduler` implementation _(interally uses Step Functions and a DynamoDb table)_.
* Add `sendAt` and `cancelJobs` methods to `Pbjx`.
* Add `getScheduler` method to `ServiceLocator`.  The service is optional, just like `EventStore` and `EventSearch`.  Attempting to call `sendAt` or `cancelJobs` will throw an exception if you haven't configured the scheduler.


## v2.1.1
* Add `Gdbots\Pbjx\DependencyInjection\*` marker interfaces to make it possible to autowire/autoconfigure services in frameworks like Symfony.
* Update `CommandHandler` and `RequestHandler` interfaces to use new `PbjxHandler` marker interface which requires static method `handlesCuries`.  If your code is not using the `CommandHandlerTrait` or `RequestHandlerTrait` then you will need to add those methods.  At the time of this update, no code in the wild is known to exist not using the traits so this is left as a patch.  Also note, emitting an empty array is fine if you're not using Symfony or another framework that would make use of that method.


## v2.1.0
* Update `gdbots/schemas` composer constraint to allow for `^1.4.1`.
* Remove `gdbots/pbj` package from composer as `gdbots/schemas` already requires it.
* Update `symfony/event-dispatcher` composer constraint to allow for `^3.0 || ^4.0`.
* Add `PbjxToken` which creates one time use signed tokens (JWT) that are intendeded to be used to secure pbjx HTTP services against XSS, CSRF, replay attacks, etc.


## v2.0.1
* issue #8: Automatically reconnect gearman if connection is lost.  After `maxReconnects` is reached (default=3) then all further pbjx operations will be handled in memory.


## v2.0.0
__BREAKING CHANGES__

* Requires php7.1 and all classes use `declare(strict_types = 1);` and use php7 return type hints and scalar type hints.
* Renames some classes ("Default" makes no sense, "Simple" is more clear as an implementation):
  * `DefaultCommandBus` is now `SimpleCommandBus`
  * `DefaultExceptionHandler` is now `LogAndDispatchExceptionHandler`
  * `DefaultEventBus` is now `SimpleEventBus`
  * `DefaultPbjx` is now `SimplePbjx`
  * `DefaultRequestBus` is now `SimpleRequestBus`
* Most implementations are now marked as final.  Use decorator pattern or provide your own implementation to change the functionality of Pbjx components.
* Transports now use a `TransportEnvelope` so the consumers can handle multiple serializers and properly set replay flag on messages that are handled in a separate process or separate machine entirely (e.g. gearman).
* `Transport` and `Router` interfaces moved to `Gdbots\Pbjx\Transport\*`.

__NEW FEATURES__
* Adds `EventStore` and `EventSearch` with DynamoDb and Elastica implementations.
