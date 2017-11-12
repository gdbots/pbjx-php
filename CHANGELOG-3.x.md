# CHANGELOG for 3.x
This changelog references the relevant changes done in 3.x versions.


## v3.0.0
__BREAKING CHANGES__

* Update `Gdbots\Pbjx\EventSearch\Elastica\*` classes to use `"ruflin/elastica": "~5.3"`.
* Add `PbjxToken` which creates one time use signed tokens (JWT) that are intendeded
  to be used to secure pbjx HTTP services against XSS, CSRF, replay attacks, etc.
