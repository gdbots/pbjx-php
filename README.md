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

> If your project is using Symfony3 use the [gdbots/pbjx-bundle-php](https://github.com/gdbots/pbjx-bundle-php) to simplify the integration.


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

> [Publisher/Subscriber](https://en.wikipedia.org/wiki/Publish%E2%80%93subscribe_pattern) is the pattern used.  This is important because it may look like the Symfony3 EventDispatcher but a Pbjx subscriber cannot stop the propagation of events like they can in a Symfony3 subscriber/listener.

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
