# CHANGELOG for 4.x
This changelog references the relevant changes done in 4.x versions.


## v4.1.1
* Update `DynamoDbEventStore::doPipeAllEvents` to allow iteration key in piping events to be string or int.


## v4.1.0
* Require symfony 6.2.x
* Fix deprecation notice from elastica for addIndices.


## v4.0.0
__BREAKING CHANGES__

* Require php 8.1 and allow symfony 5.x|6.x.
* Require gdbots/schemas and gdbots/query-parser 3.x
