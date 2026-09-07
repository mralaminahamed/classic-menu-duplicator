# Security Policy

## Supported versions

Security fixes land on the latest release. Older versions are not patched — the
directory only ever serves the current one, so upgrading is the fix.

| Version | Supported |
| ------- | --------- |
| Latest  | :white_check_mark: |
| Older   | :x: |

## Reporting a vulnerability

**Please do not open a public GitHub issue for a security problem.** A public
report tells everyone, including whoever would exploit it, before there is a fix.

Email **alamin.ahamed.dev@gmail.com** with the subject **[SECURITY] Swift Menu Duplicator**.

Useful things to include, as far as you have them:

1. What kind of issue it is — XSS, injection, privilege escalation, and so on
2. Where it lives: file path and line, if you can point at it
3. How to reproduce it, step by step
4. A proof of concept, if you have one
5. What an attacker gains
6. WordPress version, PHP version, and plugin version

## What happens next

- **Acknowledgement** within 48 hours
- **Assessment** within 7 days
- **Fix** within 30 days, sooner for anything serious
- **Disclosure** coordinated with you, after a release is out

Report privately, we fix it, we agree the timing, and it goes public once users
have had a chance to update. You will be credited in the advisory unless you
would rather not be.

## In scope

- Cross-site scripting, stored or reflected
- SQL injection
- Authentication bypass or privilege escalation
- Remote code execution
- Cross-site request forgery on state-changing operations
- Information disclosure
- Unauthorised menu modification, deletion, or export through the REST routes or
  WP-CLI commands — every one of them is capability-gated and nonce-checked, and a
  path around either gate is a vulnerability
- Import of a crafted JSON payload producing unsafe menu items

## Out of scope

- Vulnerabilities in third-party dependencies — report those to their maintainers
- WordPress core issues — report those to the WordPress security team
- Anything requiring administrator access to exploit, unless it is a privilege
  escalation
- Social engineering, physical access, or denial of service requiring
  disproportionate resources
- Self-XSS, and theoretical issues with no practical impact

## Contact

Al Amin Ahamed — alamin.ahamed.dev@gmail.com ·
[github.com/mralaminahamed/swift-menu-duplicator/security](https://github.com/mralaminahamed/swift-menu-duplicator/security)

For anything sensitive, encrypted communication is available on request.
