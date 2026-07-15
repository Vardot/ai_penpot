# Installation

## Install the module

```bash
composer require drupal/ai_penpot
ddev drush en ai_penpot -y
```

This also installs the dependencies `ai`, `ai_agents`, `key` and
`easy_encryption`, and creates an empty **Penpot access token** Key for you.

## Connect your Penpot token

The token is stored in the [Key](https://www.drupal.org/project/key) module —
the same way Drupal AI stores its provider keys — so it is encrypted at rest via
`easy_encryption`.

1. Create an access token in Penpot at *Your account → Access tokens*.

    !!! note "Self-hosted Penpot"
        A self-hosted instance must have access tokens enabled — add
        `enable-access-tokens` to `PENPOT_FLAGS` and restart. Penpot Cloud
        (`https://design.penpot.app`) has them on by default.

2. Paste it into the *Penpot access token* Key at
   `/admin/config/system/keys`.
3. Clear the cache:

    ```bash
    ddev drush cr
    ```

4. Visit **Configuration → AI → AI Penpot** (`/admin/config/ai/penpot`), set the
   **Penpot instance URL**, confirm the Key is selected, optionally set a
   default file id, and click **Test Penpot connection**.

!!! tip "Check the status report"
    The status report (`/admin/reports/status`) shows an **AI Penpot** line —
    *Penpot connection configured* once the URL and token resolve, or a warning
    with a link to the settings page while they do not.

## Uninstall

```bash
ddev drush pmu ai_penpot -y
```

The Penpot access token Key is removed with the module (it is an enforced
dependency), so the stored token does not linger.
