# walled-secrets
A small password manager, supporting proper sharing. Want to discuss the project or ask Questions? Just join the [Discord](https://discord.gg/6KmbM2r8Tx).

## Features

- Organisations
  - shared passwords
  - shared notes
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
  - Notes (name, public identifier, content)
  - Logins (domain, username, password, public identifier, note)
- Imports
  - Keepass XML
  - Bitwarden JSON

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
Right now only from bitwarden's unencrypted json and Keypass's XML.

### Why does it take so long to retrieve encrypted data?
We unencrypt the data on the fly. The duration is based on the security of the private key used.
