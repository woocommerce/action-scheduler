# Security Policy

Full details of the Automattic Security Policy can be found on [automattic.com](https://automattic.com/security/).

## Supported Versions

Generally, only the latest version of the extension has continued support.  In some cases, we may opt to backport critical vulnerabilities fixes to previous versions. 

## Reporting a Vulnerability

[Action Scheduler](https://actionscheduler.org/) is an open-source library for WordPress. Our HackerOne program covers the plugin software.

**For responsible disclosure of security issues and to be eligible for our bug bounty program, please submit your report via the [HackerOne](https://hackerone.com/automattic) portal.**

The security contact for this project is security@automattic.com.

_Please note that the **WordPress software is a separate entity** from Automattic. Please report vulnerabilities for WordPress through [the WordPress Foundation's HackerOne page](https://hackerone.com/wordpress)._

## Guidelines

We're committed to working with security researchers to resolve the vulnerabilities they discover. You can help us by following these guidelines:

*   Follow [HackerOne's disclosure guidelines](https://www.hackerone.com/disclosure-guidelines).
*   Pen-testing Production:
    *   Please **setup a local environment** instead whenever possible. Most of our code is open source (see above).
    *   If that's not possible, **limit any data access/modification** to the bare minimum necessary to reproduce a PoC.
    *   **_Don't_ automate form submissions!** That's very annoying for us, because it adds extra work for the volunteers who manage those systems, and reduces the signal/noise ratio in our communication channels.
    *   To be eligible for a bounty, all of these guidelines must be followed.
*   Be Patient - Give us a reasonable time to correct the issue before you disclose the vulnerability.

We also expect you to comply with all applicable laws. You're responsible to pay any taxes associated with your bounties.
