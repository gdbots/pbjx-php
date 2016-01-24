# CHANGELOG for 1.x
This changelog references the relevant changes done in 1.x versions.


## v1.0.0
__BREAKING CHANGES__

* Using new `gdbots/pbj` and `gdbots/pbj-schemas-php` libraries (schemas moved, php >=5.6 required).
* Removed `ConventionalCommandHandling` and `ConventionalRequestHandling`.  Now that compiler won't be generating
  getters/setters these classes don't help much.
* `CommandHandler` and `RequestHandler` interfaces are now only used for auto discovering handlers (for now).
  The "handle" method is implicitly required but not enforced by the interface.  This allows the handler to define
  its own type hinting for the first argument (so long as it implement Command or Request).

