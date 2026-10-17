# Local Configuration

This folder contains environment-specific configuration, including `.htpasswd` with hashed admin passwords. `config.yml` holds the settings of the installation and is edited on the server. An optional `config.local.yml` lays settings for one machine over it, for example a local mail catcher during development. It is ignored by Git.

**Security:** This folder must not be accessible from the web and should not be readable by other users on the file system. Ensure your web server configuration prevents public access and set appropriate file permissions.
