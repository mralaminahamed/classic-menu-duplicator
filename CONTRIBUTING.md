# Contributing to Swift Menu Duplicator

Bug reports, feature requests, and pull requests are all welcome.

## Before you start

For anything larger than a fix, open an issue first. It is a cheaper place to
find out an idea does not fit than a finished branch is.

Search [existing issues](https://github.com/mralaminahamed/swift-menu-duplicator/issues) before filing a new
one — someone may already have hit it.

## Local setup

```bash
git clone https://github.com/mralaminahamed/swift-menu-duplicator.git
cd swift-menu-duplicator
composer install
yarn install
```

Symlink or copy the directory into a WordPress install's `wp-content/plugins/`
and activate it.

## Working on a change

Branch off `trunk`, and name the branch for what it does:

```bash
git checkout -b fix/some-specific-thing
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):
`fix:`, `feat:`, `docs:`, `test:`, `chore:`. The subject says what changed;
the body says why, which is the half that is not recoverable from the diff.

## Quality gates

Run these before opening a pull request — CI runs them too, and a red pull
request is slower for everyone:

```bash
composer analyze       # phpcs + phpstan
composer test          # PHPUnit
yarn lint              # JS + CSS
```

> The PHPUnit configuration connects to MySQL as `root` with no password. On a
> machine set up differently the suite fails to bootstrap rather than reporting
> failures — set the credentials in `phpunit.xml` before concluding the tests
> are broken.

## Pull requests

- One concern per pull request. Two unrelated fixes are two pull requests.
- Say what you changed and why. If you decided against an obvious alternative,
  say that too — it saves the reviewer rediscovering it.
- Add or update tests for behaviour you changed. A change with no test is a
  change nobody will notice breaking.
- Update `readme.txt` and `CHANGELOG.md` for anything a user would notice.
- Do not commit `build/`, `vendor/`, or `node_modules/`.

## Reporting bugs

Include the WordPress version, PHP version, plugin version, and the steps that
reproduce it. "It does not work" cannot be acted on; a sequence that fails can.

## Security

Do not report security issues in a public issue. See [SECURITY.md](SECURITY.md).

## License

By contributing, you agree that your contributions are licensed under
GPL-2.0-or-later, the same licence as the project.
