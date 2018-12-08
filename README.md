[![Build Status](https://travis-ci.com/anezi/preferred-locale.svg?branch=master)](https://travis-ci.com/anezi/preferred-locale)
[![codecov](https://codecov.io/gh/anezi/preferred-locale/branch/master/graph/badge.svg)](https://codecov.io/gh/anezi/preferred-locale)

# Preferred locale event subscriber for Symfony projects.

Features:
- If the locale is not defined in the URL that the subscriber checks in the session, otherwise it checks the browser languages.
- The locale is displayed in the URL in lowercase and using a hyphen.
- It converts the locale displayed in the URL into Symfony-compatible locale.
