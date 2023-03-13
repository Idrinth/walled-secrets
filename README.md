# walled-secrets
A small password manager, supporting proper sharing. Want to discuss the project or ask Questions? Just join the [Discord](https://discord.gg/6KmbM2r8Tx).

## Features

- Organisations
  - shared passwords
  - shared notes
  - may require their members to use 2FA
- Socials
  - notes on known members
  - create notes for a person
  - create login for a person (password reset etc.)
  - invite new person to join the app by email
  - invite new people to know them by email
- Folders
  - unlimited folders per user
  - organise your secrets by folder
- Secrets
  - Notes
    - public identifier: unencrypted
    - content: AES-encrypted, IV and Key RSA-encrypted
  - Logins
    - username: RSA-encrypted
    - password: RSA-encrypted
    - public identifier: unencrypted
    - note: AES-encrypted, IV and Key RSA-encrypted
- Imports
  - Keepass XML
  - Bitwarden JSON
  - Firefox CSV
- Security
  - Server IP Whitelist
  - Server IP Blacklist
  - Server ASN Blacklist
  - Account IP Whitelist
  - Account IP Blacklist
  - 2 Factor Authentication via Google Authenticator or similar
  - IP-Locked Sessions
  - Configurable Minimum Master-Password Length
  - Verbose account audit log
  - Verbose organisation audit log

## Installation

### Server

#### Docker

And Image is ready [here](https://hub.docker.com/r/idrinth/walled-secrets). Remember to adjust the environment variables.

#### Linux-Machine

`git clone https://github.com/idrinth/walled-secrets . && composer install`, then adjust the environment file.

### Clients

#### Browser

The website should work fine with any browser once deployed. If you find issues, please open a bug report here.

#### Firefox Browser Addon

The Firefox addon is available [here](https://addons.mozilla.org/en-US/firefox/addon/idrinth-walled-secrets/).

#### Firefox Android Browser Addon

The Firefox addon for Android is currently in review.

#### Opera Browser Addon

The Opera addon is currently in review.

#### Chrome Browser Addon

The Chrome addon is currently in review.

#### Edge Browser Addon

The Edge addon is currently in review.

## Contributing

Contributions are welcome. Please remember that any contribution done are final and may not be withdrawn at a later date. All contributions are made available under the MITlicence. Got to [the repository](https://github.com/Idrinth/walled-secrets) to contribute.

## FAQ

### How secure is my data?
We encrypt everything not explicitly labeled public. For longer texts(like notes) we use AES backed by RSA encrypting IV and Key. For shorter texts we directly use RSA. Your master password is stored encrypted(by AES and blowfish) in a Session if you log in via the website. With their secrets split between database and environment that should keep them pretty save.

### Are you making a profit?
While I accept gifts via paypal, there are no and will not be any payed for features.

### Why is this invite only?
I don't want to pay for the whole internet using this. Feel free to host your own instance if you don't know anyone using this instance. All the code is at github and the license is MIT - so very open.

### Why is Feature X missing?
I'm coding this in my free time, so it is either a ticket already or you can open one and I'll get to it.

### From what password managers can you import?
Right now only from Bitwarden's unencrypted json, Firefox's CSV and Keepass's XML.

### Why does it take so long to retrieve encrypted data?
We unencrypt the data on the fly. The duration is based on the security of the private key used.
