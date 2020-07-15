# The Review Machine

This is a quick and dirty PHP site to randomly assign a reviewer to GitHub pull request, and allow them to opt in to which repos they want to receive review requests to.

Reviewing PRs is a great way to get to know a codebase, so we built a tool to help out with that

## Requirements

* PHP >= 7.0 with the sqlite3 extension

## Installation

* `git clone git@github.com:UniversityRadioYork/review_machine.git` into wherever your web server stores file (often `/var/www/html` or variations)
* Configure it to serve PHP - out of the scope of this guide
  * Check that you can access it now. For the rest of this guide, replace https://example.com/review_machine with your installation's address
* Create a GitHub user for your bot, create a [personal access token](https://github.com/settings/tokens) for it
  * It must have, at minimum, `public_repo` scope. If you want to work on private repos, it will need `repo` as well.
  * Save the token in a file called `.githubtoken` in your installation directory.
    * Make sure your server is set to not serve dot-files!
* Create a webhook on GitHub - either for your entire org or for individual repos. It must receive at least pull request events, other event types will be ignored.
  * The hook's address must be https://example.com/review_machine/github-webhook.php
  * Generate a secure secret - for example, by running `php -r "echo bin2hex(random_bytes(20));"`
    * Save this secret in a file called `.githubsecret` in your installation directory.
* Figure out a way of authenticating users, and rip out the horrible mess that is `_auth.php` with your own implementation. It must set a variable called `$memberid`, unique to each user using your Review Machine.

## Copyright

Copyright &copy; 2020 Marks Polakovs / University Radio York. Licensed under the BSD 3-Clause license.
