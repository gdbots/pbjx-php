# CHANGELOG for 2.x
This changelog references the relevant changes done in 2.x versions.


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
