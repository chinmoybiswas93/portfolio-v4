# Security policy

## Reporting a vulnerability

Please report security problems privately, not in a public issue.

- Use GitHub's **Report a vulnerability** button on the repository's Security tab (private vulnerability reporting).
- Include the affected version or commit, what you found, steps to reproduce it and the impact you expect.

You can expect an acknowledgement within a few days. Please give a reasonable time to fix the issue before sharing details.

## Scope

In scope: the application code in this repository, including the admin panel, invoicing, the MCP server and the OAuth flow.

Out of scope: a particular deployment's server, hosting account or third-party services, and findings that need physical access or an already-compromised admin account.

## Hardening notes

- Admin sign-in requires two-factor authentication in every environment except local development.
- The MCP server is off by default (`MCP_ENABLED`) and every assistant needs the owner's approval. See `docs/adr/0009-mcp-server.md`.
- Never commit `.env`, credentials or database dumps.
