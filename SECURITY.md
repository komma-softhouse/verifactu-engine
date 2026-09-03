# Security Policy

## Supported versions

Security fixes land on the latest tagged release of this package. Older
tags are not patched.

## Reporting a vulnerability

Report privately, never in a public issue: open a
[security advisory](../../security/advisories/new) on this repository, or
write to **security@kommasofthouse.com**.

Please include the affected version, the steps to reproduce it, and what
an attacker could obtain or alter. A proof of concept helps, but a clear
description is enough.

What to expect:

- Acknowledgement within 3 working days.
- An assessment, with severity and a fix window, within 10 working days.
- Credit in the release notes when the fix ships, unless you prefer not to
  be named.

Please give us a reasonable window to release a fix before disclosing
publicly.

## Scope

This package builds, signs or submits fiscal documents to a Spanish tax
administration. Reports about the following are especially welcome:

- Anything that lets a signed or chained record be altered without the
  signature or hash changing.
- Anything that exposes certificates, private keys or passphrases in
  storage, logs or responses.
- Injection or path traversal reachable from user-supplied invoice data.

Out of scope: vulnerabilities in the tax administrations' own services.
