# Disembark Connector

Connector plugin for [Disembark](https://disembark.host).

## Usage

- Download and install [latest version of Disembark Connector](https://github.com/DisembarkHost/disembark-connector/releases) on any WordPress site.
- From `wp-admin/plugins.php` select "View details" next to Disembark Connector and select "Launch Disembark with your token"
- After backup completed, copy command into terminal or over SSH to generate the backup zip.

### Changelog

**v1.0.2**
- New advanced options. Ability to backup only files or database. Ability to include certain database tables or certain files or paths.
- New instructions for Disembark CLI
- Analyze site when pasting site URL and token
- Improve backup progress
- Split large database tables into smaller exports
- Fix endpoints `cleanup` and `download` to only respond with plain text
- Cleanup unused backup code

**v1.0.1**
- Improved database exports for [Local](https://localwp.com)
- Cleanup endpoint to purge `uploads/disembark` folder after successful download.
- One click connection string to [Disembark](https://disembark.host)

**v1.0.0**
- Intial release of Disembark. Allows for full site WordPress backups to be made from [Disembark.host](Disembark.host)
