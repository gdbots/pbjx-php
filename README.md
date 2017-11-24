pbjx-php
=============

[![Build Status](https://api.travis-ci.org/gdbots/pbjx-php.svg)](https://travis-ci.org/gdbots/pbjx-php)
[![Code Climate](https://codeclimate.com/github/gdbots/pbjx-php/badges/gpa.svg)](https://codeclimate.com/github/gdbots/pbjx-php)
[![Test Coverage](https://codeclimate.com/github/gdbots/pbjx-php/badges/coverage.svg)](https://codeclimate.com/github/gdbots/pbjx-php/coverage)

This library provides the messaging tools for [Pbj](https://github.com/gdbots/pbj-php).

> __Pbj__ stands for "Private Business Json".  
> __Pbjc__ stands for "Private Business Json Compiler", a tool that creates php classes from a schema configuration.  
> __Pbjx__ stands for "Private Business Json Exchanger", a tool that exchanges pbj through various transports and storage engines.

Using this library assumes that you've already created and compiled your own pbj classes using the
[Pbjc](https://github.com/gdbots/pbjc-php).


# Pbjx Primary Methods
+ __send:__ asynchronous message delivery to a single recipient with no return payload.
+ __publish:__ asynchronous message broadcast which can be subscribed to.
+ __request:__ synchronous message delivery to a single recipient with an expected return payload.

The strategy behind this library is similar to [GRPC](http://www.grpc.io/) and [CQRS](https://martinfowler.com/bliki/CQRS.html).

> If your project is using Symfony use the [gdbots/pbjx-bundle-php](https://github.com/gdbots/pbjx-bundle-php) to simplify the integration.


# Transports
When pbj (aka messages) are exchanged a transport is used to perform that action.  Your application/domain logic should never deal directly with the transports.

__Available transports:__

+ AWS Firehose
+ AWS Kinesis
+ Gearman
+ In Memory

## Routers
Some transports require a router to determine the delivery channel (stream name, gearman channel, etc.) to route the message through.  The router implementation can be fixed per type of message (command, event, request) or content specific as the pbj message itself is provided to the router.

For example:
```php
interface Router
{
    /**
     * @param Command $command
     *
     * @return string
     */
    public function forCommand(Command $command): string;

    ...
}
```


# Pbjx::send
Processes a command (asynchronously if transport supports it).

When using the send method it implies that there is a single handler for that command, stated another way... if a "PublishArticle" command exists, there __MUST__ be a service that handles that command.

> In the __gdbots/pbjx-bundle-php__ the `SchemaCurie` is used to derive the service id.  If not found in the Container, a guesser is used to attempt to find the handler using PSR conventions.

All command handlers MUST implement `Gdbots\Pbjx\CommandHandler`.  For convenience a `CommandHandlerTrait` is provided which implements the required method and internally calls your "handle" method which allows for explicit type hinting in your class.

__Example handler for a "PublishArticle" command:__

```php
<?php
declare(strict_types = 1);

final class PublishArticleHandler implements CommandHandler
{
    use CommandHandlerTrait;

    /**
     * @param PublishArticle $command
     * @param Pbjx           $pbjx
     */
    protected function handle(PublishArticle $command, Pbjx $pbjx): void
    {
        // handle the command here
    }
}
```
Invoking the command handler is never done directly (except in unit tests).  In this made up example, you might have a controller that creates and sends the command.

```php
<?php
declare(strict_types = 1);

final class ArticleController extends Controller
{
    /**
     * @Route("/articles/{article_id}/publish", requirements={"article_id": "^[0-9A-Fa-f]+$"})
     * @Method("POST")
     * @Security("is_granted('acme:blog:command:publish-article')")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function publishAction(Request $request): Response
    {
        $command = PublishArticleV1::create()->set('article_id', $request->attributes->get('article_id'));
        $this->getPbjx()->send($command);
        $this->addFlash('success', 'Article was published');
        return $this->redirectToRoute('app_article_index');
    }
}
```


# Pbjx::publish
Publishes events to all subscribers (asynchronously if transport supports it).  All subscribers will receive the event unless a fatal error occurs.

> [Publisher/Subscriber](https://en.wikipedia.org/wiki/Publish%E2%80%93subscribe_pattern) is the pattern used.  This is important because it may look like the Symfony EventDispatcher but a Pbjx subscriber cannot stop the propagation of events like they can in a Symfony subscriber/listener.

Pbjx published events are distinct from application or "lifecycle" events.  These are your ["domain events"](https://martinfowler.com/eaaDev/DomainEvent.html).  The names you'll use here would make sense to most people, including the non-developer folks.  MoneyDeposited, ArticlePublished, AccountClosed, UserUpgraded, etc.

Subscribing to a Pbjx published event requires that you know the `SchemaCurie` of the event or its mixins.

Continuing the example above, let's imagine that `PublishArticleHandler`  created and published an event called `ArticlePublished` and its curie was __"acme:blog:event:article-published"__.  The pbjx SimpleEventBus (default implementation) would do this:

```php
$event->freeze();
$schema = $event::schema();
$curie = $schema->getCurie();

$vendor = $curie->getVendor();
$package = $curie->getPackage();
$category = $curie->getCategory();

foreach ($schema->getMixinIds() as $mixinId) {
    $this->dispatch($mixinId, $event);
}

foreach ($schema->getMixinCuries() as $mixinCurie) {
    $this->dispatch($mixinCurie, $event);
}

$this->dispatch($schema->getCurieMajor(), $event);
$this->dispatch($curie->toString(), $event);

$this->dispatch(sprintf('%s:%s:%s:*', $vendor, $package, $category), $event);
$this->dispatch(sprintf('%s:%s:*', $vendor, $package), $event);
$this->dispatch(sprintf('%s:*', $vendor), $event);
$this->dispatch('*', $event);
```
In your subscriber you could listen to any of:

- __acme:blog:event:article-published:v1__
- __acme:blog:event:article-published__
- __acme:blog:event:*__ _all events in "acme:blog:event" namespace_
- __acme:blog:*__ _all events in "acme:blog" namespace_
- __acme:*__ _all events in "acme" namespace_
- __*__ _all events_

> And any of its mixins:
- vendor:package:mixin:some-event

__The method signature of all pbjx event subscribers should be the interface of the event and then the Pbjx service itself.__

```php
<?php
declare(strict_types = 1);

namespace Acme\Blog;

use Gdbots\Pbjx\EventSubscriber;

final class MyEventSubscriber implements EventSubscriber
{
    /**
     * @param ArticlePublished $event
     * @param Pbjx             $pbjx
     */
    public function onArticlePublished(ArticlePublished $event, Pbjx $pbjx): void
    {
        // do something with this event.
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'acme:blog:event:article-published' => 'onArticlePublished',
        ];
    }
}
```
When subscribing to multiple events you can use the convenient `EventSubscriberTrait` which will automatically call methods matching any events it receives, e.g. "onUserRegistered", "onUserUpdated", "onUserDeleted".


# Pbjx::request
Processes a request synchronously and returns the response.  If the transport supports it, it may not be running in the current process (gearman for example).  This is similar to "send" above but in this case, a response __MUST__ be returned.

All request handlers MUST implement `Gdbots\Pbjx\RequestHandler`.  For convenience a `RequestHandlerTrait` is provided which implements the required method and internally calls your "handle" method which allows for explicit type hinting in your class.

__Example handler for a "GetArticleRequest":__

```php
<?php
declare(strict_types = 1);

final class GetArticleRequestHandler implements RequestHandler
{
    use RequestHandlerTrait;

    /**
     * @param GetArticleRequest $request
     * @param Pbjx              $pbjx
     */
    protected function handle(GetArticleRequest $request, Pbjx $pbjx): GetArticleResponse
    {
        $response = GetArticleResponseV1::create();
        // imaginary repository
        $article = $this->repository->getArticle($request->get('article_id'));
        return $response->set('article', $article);
    }
}

```
Invoking the request handler is never done directly (except in unit tests).

```php
<?php
declare(strict_types = 1);

final class ArticleController extends Controller
{
    /**
     * @Route("/articles/{article_id}", requirements={"article_id": "^[0-9A-Fa-f]+$"})
     * @Method("GET")
     * @Security("is_granted('acme:blog:request:get-article-request')")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function getAction(Request $request): Response
    {
        $getArticleRequest = GetArticleRequestV1::create()->set('article_id', $request->attributes->get('article_id'));
        $getArticleResponse = $this->getPbjx()->request($getArticleRequest);
        return $this->render('article.html.twig', ['article' => $getArticleResponse->get('article')]);
    }
}
```


# Pbjx Lifecycle Events
When a message is processed (send, publish, request) it goes through a lifecycle which allows for "in process"
modification and validation.  The method of subscribing to these events is similar to how a Symfony event
subscriber/listener works and can stop propagation.

The lifecycle event names (what your subscriber/listener must be bound to) all have a standard format, _e.g: "gdbots:pbjx:mixin:command.bind"_.  These are named in the same way that the `SimpleEventBus` names them.  See `SimplePbjx::trigger` method for insight into how this is done.

__The lifecycle events, in order of occurrence are:__

### bind
Data that must be bound to the message by the environment its running in is done in the "bind" event.  This is data that generally comes from the environment variables, http request itself, request context, etc.  It's a cheap operation (in most cases).

> Binding user agent, ip address, input from a form are good examples of things to do in the "bind" event.

### validate
Before a message is allowed to be processed it should be validated.  This is where business rules are generally implemented.  This is more than just schema validation (which is done for you by pbj).

> Checking permissions is generally done in this event.  In a Symfony app this would be where you run the `AuthorizationChecker` and/or security voters.
> Additional examples:
  - Checking inventory level on "AddProductToCart"
  - Optimistic concurrency control on "UpdateArticle"
  - Check for available username on "RegisterUser"
  - Validate catchpa on "SubmitContactForm"
  - Limit uploaded file size on "UploadVideo"

### enrich
Once you've decided that a message is going to be processed you can perform additional enrichment that would have been expensive or not worth doing up until now.  The enrichment is the final phase so once this is done the message will be frozen and then transported.

> Geo2Ip enrichment, sentiment analysis, adding related data to events, etc. is a good use of the "enrich" event.


# EventStore
Publishing events and storing/retrieving them is such a common need that we added the `Pbjx::getEventStore` method to make the service available without any extra wiring of services to handlers, subscribers, etc.

The service will not be instantiated until you call the method so there's no performance penalty for having this capability built into Pbjx.  At this time the only available implementation is DynamoDb.

## Key concepts of the EventStore

### Streams
Events are appended to a stream which is identified by a `StreamId` (see below) and are ordered by the `occurred_at` field of the event within that stream.  This is not (forgive the ten dollar word) a "monotonically increasing gapless sequence number" (v1, v2, ..., v10).  You could certainly achieve that design with your own implementation but for the original design/implemenation, it was more important to be able to collect data from many different servers (including different regions) without having to coordinate with a centralized id/sequence service.

> The `occurred_at` field is 10 digits (unix timestamp) concatenated with 6 microsecond digits.

In order to mitigate collision possibilities we use more granular stream ids when the volume of data is expected to be high.  Users editing articles in a cms vs users voting on polls for example, you know the write volume of poll votes would be MUCH higher.

__EventStores can have billions of streams and have no performance issues so long as:__

+ The StreamIds you append events to are distributed enough to avoid hot keys.
+ The total volume of events on a single stream doesn't exceed 10GB (This limit comes from DynamoDb).
  _You can use date based storage to deal with long-lived streams, e.g. events-YYYYMM._
+ The underlying storage is setup to handle the data size and write/read rates of your application.

> __IMPORTANT:__ An event may exist in one or more streams.  The StreamId is NOT a property of the event itself.

### StreamId
A stream id represents a stream of events.  The parts of the id are delimited by a colon for our purposes but can easily be converted to acceptable formats for SNS, Kafka, etc.

It may also be desirable to only use parts of the stream id (e.g. topic) for broadcast.

Using a partition and optionally a sub-partition makes it possible to group all of those records together in storage and also guarantee their sequence is exactly in the order that they were added to the stream.

> StreamId Format: topic:partition:sub-partition

__Examples:__

"twitter.timeline" _(topic)_, "homer-simpson" _(partition)_, "yyyymm" _(sub-partition)_

> twitter.timeline:homer-simpson:201501  
> twitter.timeline:homer-simpson:201502  
> twitter.timeline:homer-simpson:201503

"bank-account" _(topic)_, "homer-simpson" _(partition)_

> bank-account:homer-simpson

"poll.votes" _(topic)_, "batman-vs-superman" _(partition)_, "yyyymm.[0-9a-f][0-9a-f]" _(sub-partition)_

Note the sub-partition here is two hexidecimal digits allowing for 256 separate stream ids.  Useful when you need to avoid hot keys and ordering in the overall partition isn't important.

> poll.votes:batman-vs-superman:20160301.0a  
> poll.votes:batman-vs-superman:20160301.1b  
> poll.votes:batman-vs-superman:20160301.c2

### StreamSlice
Getting data out of the EventStore is done via piping from a single stream or ALL streams or by getting a slice of a stream.  Think of the StreamSlice as the php function [array_slice](http://php.net/manual/en/function.array-slice.php).  You can get slices forward and backward from a stream and the events are ordered by the `occurred_at` field.

## EventStore::putEvents
In most cases, you'd be writing to the EventStore in a command handler.  Continuing the publish article example above, let's actually make the event.

```php
<?php
declare(strict_types = 1);

final class PublishArticleHandler implements CommandHandler
{
    use CommandHandlerTrait;

    /**
     * @param PublishArticle $command
     * @param Pbjx           $pbjx
     */
    protected function handle(PublishArticle $command, Pbjx $pbjx): void
    {
        // in this example it's ultra basic, create the event and push it to a stream
        $event = ArticlePublishedV1::create()->set('article_id', $command->get('article_id'));
        // copies contextual data from the previous message (ctx_* fields)
        $pbjx->copyContext($command, $event);

        $streamId = StreamId::fromString(sprintf('article.history:%s', $command->get('article_id')));
        $pbjx->getEventStore()->putEvents($streamId, [$event]);

        // after the event is persisted it will be published either via a
        // two phase commit or a publisher reading the EventStore streams
        // (DynamoDb streams for example)
    }
}
```
One thing to note is that the state of the article was not modified here, we just put an event on a stream.  An event subscriber would listen for those events and then update the article.  That is just one way to handle state updates.

> This design implies [eventual consistency](https://en.wikipedia.org/wiki/Eventual_consistency).


# EventSearch
Storing and retrieving events are all handled by the EventStore but often times you need to be able to search events too.  Comments, product reviews, post reactions, survey responses, etc.  Lots of use cases can benefit from indexing.

Similar to the EventStore the EventSearch service is only instantiated if requested.  The only implementation we have right now is ElasticSearch.  To use this feature, the events you want to index must be using the __"gdbots:pbjx:mixin:indexed"__ mixin.  When using the __gdbots/pbjx-bundle-php__ you can enable the indexing with a simple configuration option.

Searching events is generally done in a request handler.  Here is an example of searching events:

```php
/**
 * @param SearchCommentsRequest $request
 * @param Pbjx                  $pbjx
 *
 * @return SearchCommentsResponse
 */
protected function handle(SearchCommentsRequest $request, Pbjx $pbjx): SearchCommentsResponse
{
    $parsedQuery = ParsedQuery::fromArray(json_decode($request->get('parsed_query_json', '{}'), true));

    $response = SearchCommentsResponseV1::create();
    $pbjx->getEventSearch()->searchEvents(
        $request,
        $parsedQuery,
        $response,
        [SchemaCurie::fromString('acme:blog:event:comment-posted')]
    );

    return $response;
}
```


# PbjxDebugger
When developing applications using Pbjx you need to be able to see what's being exchanged.  The `PbjxDebugger` will push "debug" messages with all of the pbj data that is being handled.  A `Psr\Log\LoggerInterface` logger must be provided when using this service.

If you're using [monolog](https://github.com/Seldaek/monolog) you can route all of these entries to their own file in json line delimited format.  This is the recommended use because it makes it possible to use other great tools like [jq](https://stedolan.github.io/jq/).

__Example yaml config for use in Symfony.__

```yaml
services:
  monolog_json_formatter:
    class: Monolog\Formatter\JsonFormatter
    arguments: [!php/const:Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES]

monolog:
  handlers:
    pbjx_debugger:
      type: stream
      path: '%kernel.logs_dir%/pbjx-debugger.log'
      level: debug
      formatter: monolog_json_formatter
      channels: ['pbjx.debugger']
```
