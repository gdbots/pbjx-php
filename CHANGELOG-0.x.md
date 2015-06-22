# CHANGELOG for 0.x
This changelog references the relevant changes done in 0.x versions.


## v0.2.0
* Use psr4 autoloading.
* Update all references to Command, DomainEvent, Entity, Request and Response interfaces to new `Domain` locations and prefix.
* Remove use of react promises for request handling.  Pbjx::request now simply returns the response or throws an exception.


## v0.1.1
* Rename composer package to `gdbots/pbjx`.


## v0.1.0
* Initial version.
