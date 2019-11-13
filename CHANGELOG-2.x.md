# CHANGELOG for 2.x
This changelog references the relevant changes done in 2.x versions.


## v2.3.13
* Do not unset the `ctx_retries` field in `DynamoDbScheduler`.


## v2.3.12
* Remove pool delay from `DynamoDbEventStore::pipeAllEvents` altogether since concurrency and batching with symfony commands does the trick.


## v2.3.11
* Add `$context['concurrency']` check in `DynamoDbEventStore::pipeAllEvents` so that can be configured. Defaults to 25.


## v2.3.10
* Update composer constraint for `gdbots/query-parser` to `~0.2 || ^1.0`.


## v2.3.9
* In `SimplePbjx::copyContext` copy the `ctx_ipv6` field when present.


## v2.3.8
* Remove use of `Limit` in `DynamoDbEventStore` so fewer queries are made to scan the table.
* Add check for destroy in context in `ElasticaEventSearch::createStorage` to delete index before creating it.


## v2.3.7
* In `Gdbots\Pbjx\EventSearch\Elastica\IndexManager` assume date is an immutable object.


## v2.3.6
* BUG fix in `DynamoDbEventStore::optimisticCheck` to cast event id to string in both checks. 


## v2.3.5
* Add `DynamoDbEventStore::beforePutItem` hook so item can be customized, e.g. setting ttl field so DynamoDb automatically deletes it.


## v2.3.4
* In `SimplePbjx` rethrow any exceptions when response created events are triggered.
* In `ElasticaEventSearch` use response query time to determine `time_taken`.


## v2.3.3
* In `Gdbots\Pbjx\EventSearch\Elastica\IndexManager` add custom normalizers from `MappingFactory::getCustomNormalizers` if available, otherwise hardcoded. 


## v2.3.2
* Add `$config['aws_session_token'] = $this->credentials->getSecurityToken();` in `Gdbots\Pbjx\EventSearch\Elastica\AwsAuthV4ClientManager` so signatures work in AWS ECS.


## v2.3.1
* Use Throwable for all typehints instead of Exception to catch TypeError as well.


## v2.3.0
__POSSIBLE BREAKING CHANGE__

If you are not using an `EventStore` or `EventSearch` implementation from this library then you'll need to add the `getEvent`, `getEvents`, `deleteEvent` and `deleteEvents` methods to your implementation.

* Add `getEvent`, `getEvents` and `deleteEvent` methods to `EventStore`.
* Add `deleteEvents` methods to `EventSearch`. 
 

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
