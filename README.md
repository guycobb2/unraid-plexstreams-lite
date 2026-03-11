# plexstreams-lite

An Unraid dashboard widget that displays active Plex streams in the left column of the Unraid dashboard.

## Features

- Live count of active streams
- Per-stream details: title, user, device
- Playback mode: Direct Play or Transcode (with codec arrows, e.g. `H265→H264 / AC3→AAC`)
- Bandwidth and LAN/Remote indicator
- Plex-style gold progress bar with current/total time
- Paused state indicator
- Auto-refreshes every 15 seconds

## Requirements

- Unraid 6.12+
- Plex Media Server accessible from your Unraid server
- A Plex token

## Installation

Paste this URL into **Plugins → Install Plugin** in the Unraid UI:

```
https://raw.githubusercontent.com/guycobb2/unraid-plexstreams-lite/main/plexstreams-lite.plg
```

## Configuration

After install, edit `/boot/config/plugins/plexstreams-lite/plexstreams-lite.cfg`:

```ini
HOST=http://YOUR-PLEX-IP:32400
TOKEN=YOUR-PLEX-TOKEN
```

**Finding your Plex token:** Sign in at plex.tv, open any media item, click ··· → Get Info → View XML — the token is the `X-Plex-Token` parameter in the URL.

## Files

| File | Purpose |
|------|---------|
| `NewDashboard.page` | Unraid dashboard widget (tile definition + JS poller) |
| `ajax.php` | Server-side Plex API proxy, returns JSON stream list |
| `includes/config.php` | Reads `plexstreams-lite.cfg` from flash drive |
