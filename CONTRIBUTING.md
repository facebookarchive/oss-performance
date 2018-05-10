# Contributing to oss-performance
We want to make contributing to this project as easy and transparent as
possible.

We would greatly appreciate pull requests that:

- Replace fake data with real-world data. Please be careful to
  anonymize/sanitize any user data before putting your pull request on github.
- Add additional targets.
- Improve the performance of any supported engine. In practice, this probably
  means configuration changes.

All targets should be representative of actual usuage, visiting a variety of
pages, with access patterns based on visitor logs. Additional dependencies/code
should also be minimized - for example, the Wordpress target does not depend
on any plugins (though one was used to generate the test data).

## Our Development Process

All development is on github; there are no Facebook-specific changes or code review
practices.

## Pull Requests
We actively welcome your pull requests.
1. Fork the repo and create your branch from `master`. 
2. If you've added code that should be tested, add tests
3. If you've changed APIs, update the documentation. 
4. Ensure the test suite passes. 
5. Make sure your code lints. 
6. If you haven't already, complete the Contributor License Agreement ("CLA").

## Contributor License Agreement ("CLA")
In order to accept your pull request, we need you to submit a CLA. You only need
to do this once to work on any of Facebook's open source projects.

Complete your CLA here: <https://code.facebook.com/cla>

## Issues  
We use GitHub issues to track public bugs. Please ensure your description is
clear and has sufficient instructions to be able to reproduce the issue.

Facebook has a [bounty program](https://www.facebook.com/whitehat/) for the safe
disclosure of security bugs. In those cases, please go through the process
outlined on that page and do not file a public issue.

## License
By contributing to oss-performance, you agree that your contributions will be
licensed under its MIT license.
